<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Command;

use BaserCore\Utility\BcContainerTrait;
use BcSiteExplorer\Service\BcSiteExplorerServiceInterface;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * サイト探査コマンド
 *
 * bin/cake bc_site_explorer crawl --crawl-id 1     # 作成済みセッションを実行（GUI の裏実行）
 * bin/cake bc_site_explorer crawl --url https://example.com/  # セッションを作成して実行
 */
class BcSiteExplorerCrawlCommand extends Command
{

    /**
     * Trait
     */
    use BcContainerTrait;

    /**
     * デフォルトコマンド名
     *
     * @return string
     */
    public static function defaultName(): string
    {
        return 'bc_site_explorer crawl';
    }

    /**
     * buildOptionParser
     *
     * @param ConsoleOptionParser $parser
     * @return ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(__d('baser_core', 'サイトを探査して URL・タイトル一覧を作成します。'));
        $parser->addOption('crawl-id', [
            'help' => __d('baser_core', '作成済みの走査セッション ID'),
        ]);
        $parser->addOption('url', [
            'help' => __d('baser_core', '開始 URL（--crawl-id を指定しない場合に新規セッションを作成）'),
        ]);
        $parser->addOption('site-id', [
            'help' => __d('baser_core', 'DB 突き合わせの対象サイト ID（省略で全サイト）'),
        ]);
        $parser->addOption('mode', [
            'help' => __d('baser_core', 'hybrid | crawl | db'),
            'default' => 'hybrid',
        ]);
        $parser->addOption('max-pages', [
            'help' => __d('baser_core', '最大取得ページ数'),
        ]);
        $parser->addOption('max-depth', [
            'help' => __d('baser_core', '最大深度'),
        ]);
        $parser->addOption('interval-ms', [
            'help' => __d('baser_core', 'リクエスト間隔（ミリ秒）'),
        ]);
        $parser->addOption('internal-base-url', [
            'help' => __d('baser_core', '内部アクセス URL（Docker 等で公開 URL に到達できない場合）'),
        ]);
        $parser->addOption('no-verify-ssl', [
            'help' => __d('baser_core', 'SSL 証明書を検証しない（自己署名証明書の環境向け）'),
            'boolean' => true,
            'default' => false,
        ]);
        $parser->addOption('blog-extra-urls', [
            'help' => __d('baser_core', 'ブログの派生 URL（RSS・カテゴリ・タグ・日付・著者・ページ送り）も DB 側から列挙する'),
            'boolean' => true,
            'default' => false,
        ]);
        return $parser;
    }

    /**
     * execute
     *
     * @param Arguments $args
     * @param ConsoleIo $io
     * @return int|null
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        /* @var \BcSiteExplorer\Service\BcSiteExplorerService $service */
        $service = $this->getService(BcSiteExplorerServiceInterface::class);

        $crawlId = $args->getOption('crawl-id');
        if (!$crawlId) {
            $url = (string)$args->getOption('url');
            if (!$url) {
                $io->err(__d('baser_core', '--crawl-id か --url のいずれかを指定してください。'));
                return static::CODE_ERROR;
            }
            $data = ['start_url' => $url, 'mode' => (string)$args->getOption('mode')];
            if ($args->getOption('site-id') !== null) $data['site_id'] = (int)$args->getOption('site-id');
            if ($args->getOption('max-pages') !== null) $data['max_pages'] = (int)$args->getOption('max-pages');
            if ($args->getOption('max-depth') !== null) $data['max_depth'] = (int)$args->getOption('max-depth');
            if ($args->getOption('interval-ms') !== null) $data['interval_ms'] = (int)$args->getOption('interval-ms');
            if ($args->getOption('internal-base-url')) $data['internal_base_url'] = (string)$args->getOption('internal-base-url');
            if ($args->getOption('no-verify-ssl')) $data['verify_ssl'] = false;
            if ($args->getOption('blog-extra-urls')) $data['include_blog_extra_urls'] = true;
            $crawl = $service->createCrawl($data);
            if ($crawl->getErrors()) {
                foreach($crawl->getErrors() as $field => $messages) {
                    $io->err($field . ': ' . implode(' / ', (array)$messages));
                }
                return static::CODE_ERROR;
            }
            $crawlId = $crawl->id;
            $io->out(__d('baser_core', '走査セッションを作成しました。ID: {0}', $crawlId));
        }

        try {
            $service->execute((int)$crawlId, function($message) use ($io) {
                $io->out($message);
            });
            $io->out(__d('baser_core', 'サイト探査が完了しました。'));
            return static::CODE_SUCCESS;
        } catch (\Throwable $e) {
            // cron の MAILTO によるメール通知が機能するよう、stderr ＋ 非0終了とする
            $io->err(__d('baser_core', 'サイト探査に失敗しました。{0}', $e->getMessage()));
            return static::CODE_ERROR;
        }
    }

}
