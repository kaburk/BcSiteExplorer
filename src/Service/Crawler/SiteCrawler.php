<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service\Crawler;

use BaserCore\Error\BcException;
use BaserCore\Utility\BcUtil;
use BcSiteExplorer\Model\Entity\BcSiteExplorerCrawl;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

/**
 * SiteCrawler
 *
 * BFS でサイトを探査する。キューは bc_site_explorer_results テーブル
 * （crawl_state='queued'）そのもので、メモリにキューを持たない。
 */
class SiteCrawler
{

    /**
     * @var HttpFetcherInterface
     */
    protected HttpFetcherInterface $fetcher;

    /**
     * @var UrlNormalizer
     */
    protected UrlNormalizer $normalizer;

    /**
     * @var LinkExtractor
     */
    protected LinkExtractor $extractor;

    /**
     * constructor.
     *
     * @param HttpFetcherInterface $fetcher
     * @param UrlNormalizer $normalizer
     * @param LinkExtractor|null $extractor
     */
    public function __construct(HttpFetcherInterface $fetcher, UrlNormalizer $normalizer, ?LinkExtractor $extractor = null)
    {
        $this->fetcher = $fetcher;
        $this->normalizer = $normalizer;
        $this->extractor = $extractor ?? new LinkExtractor();
    }

    /**
     * クロールを実行する
     *
     * @param BcSiteExplorerCrawl $crawl
     * @param array $options max_pages / max_depth / interval_ms / exclude_external / record_external
     * @param callable|null $progress 進捗コールバック
     *   （['fetched','queued','errors','depth','url'] の配列を受け取る）
     * @return void
     */
    public function crawl(BcSiteExplorerCrawl $crawl, array $options, ?callable $progress = null): void
    {
        $resultsTable = TableRegistry::getTableLocator()->get('BcSiteExplorer.BcSiteExplorerResults');

        $startUrl = $this->normalizer->normalize($crawl->start_url);
        if (!$startUrl) {
            throw new BcException(__d('baser_core', '開始URLが不正です。{0}', $crawl->start_url));
        }
        $allowedHosts = $this->buildAllowedHosts($startUrl);
        $maxPages = (int)($options['max_pages'] ?? 1000);
        $maxDepth = (int)($options['max_depth'] ?? 10);
        $intervalMs = (int)($options['interval_ms'] ?? 500);
        $excludeExternal = !empty($options['exclude_external']);
        $recordExternal = !empty($options['record_external']);

        $resultsTable->addUrl($crawl->id, $startUrl, $this->normalizer->hash($startUrl), [
            'depth' => 0,
            'crawl_state' => 'queued',
            'source' => 'crawl',
        ]);

        $fetched = 0;
        $errors = 0;
        while(true) {
            if ($maxPages && ($fetched + $errors) >= $maxPages) break;
            /** @var \BcSiteExplorer\Model\Entity\BcSiteExplorerResult|null $row */
            $row = $resultsTable->find()
                ->where(['crawl_id' => $crawl->id, 'crawl_state' => 'queued'])
                ->orderBy(['depth' => 'ASC', 'id' => 'ASC'])
                ->first();
            if (!$row) break;

            $response = $this->fetcher->fetch($row->url);
            $row->http_status = $response['status'];
            $row->content_type_header = $response['content_type']? mb_substr($response['content_type'], 0, 100) : null;
            $row->final_url = ($response['final_url'] !== $row->url)? $response['final_url'] : null;
            $row->error_message = $response['error'];
            if ($response['redirect_chain']) {
                $row->redirect_chain = json_encode($response['redirect_chain'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            if ($response['status'] === null) {
                $row->crawl_state = 'error';
                $errors++;
                // 最初のリクエスト（開始 URL）で失敗した場合は、全ページが孤立扱いに
                // なる誤解を招く結果を残さないよう、走査自体を中断する
                if ($fetched === 0 && $errors === 1 && $row->depth === 0) {
                    $resultsTable->save($row);
                    $hint = (str_contains((string)$response['error'], 'SSL certificate'))
                        ? ' ' . __d('baser_core', '自己署名証明書の環境では「SSL証明書を検証する」をオフにしてください。')
                        : '';
                    throw new BcException(__d('baser_core', '開始URLを取得できませんでした。{0}{1}', (string)$response['error'], $hint));
                }
            } else {
                $row->crawl_state = 'fetched';
                $fetched++;
            }

            // リンク抽出は内部ホストの HTML のみ
            $isInternal = $this->isAllowedHost($row->url, $allowedHosts)
                && ($row->final_url === null || $this->isAllowedHost((string)$row->final_url, $allowedHosts));
            if ($response['body'] !== null && $isInternal) {
                $extracted = $this->extractor->extract($response['body'], $response['final_url']);
                $row->title = $extracted['title'];
                $row->meta_robots = $extracted['meta_robots'];
                if ($row->depth < $maxDepth && !$this->extractor->hasNofollow($extracted['meta_robots'])) {
                    $this->enqueueLinks($resultsTable, $crawl->id, $row, $extracted, $allowedHosts, $excludeExternal, $recordExternal);
                }
            }
            $resultsTable->save($row);

            if ($progress && ($fetched + $errors) % 10 === 0) {
                $progress($this->buildProgress($resultsTable, $crawl->id, $fetched, $errors, $row->depth, $row->url));
            }
            if ($intervalMs > 0) usleep($intervalMs * 1000);
        }
        if ($progress) {
            $progress($this->buildProgress($resultsTable, $crawl->id, $fetched, $errors, 0, null));
        }
    }

    /**
     * 抽出したリンクをキューに登録する
     *
     * @param \Cake\ORM\Table $resultsTable
     * @param int $crawlId
     * @param \BcSiteExplorer\Model\Entity\BcSiteExplorerResult $row
     * @param array $extracted
     * @param array $allowedHosts
     * @param bool $excludeExternal
     * @param bool $recordExternal
     * @return void
     */
    protected function enqueueLinks($resultsTable, int $crawlId, $row, array $extracted, array $allowedHosts, bool $excludeExternal, bool $recordExternal): void
    {
        $adminPrefix = '/' . trim((string)(Configure::read('BcApp.baserCorePrefix') ?: 'baser'), '/') . '/';
        foreach($extracted['links'] as $href) {
            $url = $this->normalizer->normalize($href, $extracted['base']);
            if (!$url) continue;
            // 管理画面配下は対象外
            if (str_starts_with((string)parse_url($url, PHP_URL_PATH), $adminPrefix)) continue;
            $isInternal = $this->isAllowedHost($url, $allowedHosts);
            if (!$isInternal && $excludeExternal && !$recordExternal) continue;
            $resultsTable->addUrl($crawlId, $url, $this->normalizer->hash($url), [
                'depth' => $row->depth + 1,
                'parent_url' => $row->url,
                'source' => 'crawl',
                // 外部除外オプション有効時は取得せず記録のみ。無効時は外部もステータス取得
                // する（ただしリンク抽出は内部のみ。crawl() 側で制御）
                'crawl_state' => (!$isInternal && $excludeExternal)? 'external' : 'queued',
            ]);
        }
    }

    /**
     * 進捗情報を組み立てる
     *
     * @param \Cake\ORM\Table $resultsTable
     * @param int $crawlId
     * @param int $fetched
     * @param int $errors
     * @param int $depth
     * @param string|null $url
     * @return array
     */
    protected function buildProgress($resultsTable, int $crawlId, int $fetched, int $errors, int $depth, ?string $url): array
    {
        $queued = $resultsTable->find()
            ->where(['crawl_id' => $crawlId, 'crawl_state' => 'queued'])
            ->count();
        return ['fetched' => $fetched, 'queued' => $queued, 'errors' => $errors, 'depth' => $depth, 'url' => $url];
    }

    /**
     * URL のホストが許可ホスト集合に含まれるか
     *
     * @param string $url
     * @param array $allowedHosts
     * @return bool
     */
    protected function isAllowedHost(string $url, array $allowedHosts): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        return $host !== '' && in_array($host, $allowedHosts, true);
    }

    /**
     * 許可ホスト集合（開始 URL ＋ 全公開サイトのホスト）を組み立てる
     *
     * サブドメイン型・別ドメイン型のマルチサイトを内部として扱う。
     *
     * @param string $startUrl
     * @return array
     */
    protected function buildAllowedHosts(string $startUrl): array
    {
        $hosts = [];
        $add = function(?string $host) use (&$hosts) {
            $host = strtolower(trim((string)$host));
            if ($host !== '') $hosts[$host] = true;
        };
        $add((string)parse_url($startUrl, PHP_URL_HOST));
        $add((string)parse_url((string)Configure::read('BcEnv.siteUrl'), PHP_URL_HOST));

        try {
            $mainDomain = (string)BcUtil::getMainDomain();
            $sites = TableRegistry::getTableLocator()->get('BaserCore.Sites')->find()
                ->where(['status' => true])
                ->all();
            foreach($sites as $site) {
                if (empty($site->use_subdomain) || empty($site->alias)) continue;
                if ((int)$site->domain_type === 1 && $mainDomain) {
                    $add($site->alias . '.' . $mainDomain);
                } elseif ((int)$site->domain_type === 2) {
                    $add($site->alias);
                }
            }
        } catch (\Throwable) {
            // サイトテーブルが引けない場合は開始 URL のホストのみで判定する
        }
        return array_keys($hosts);
    }

}
