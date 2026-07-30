<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service;

use BcSiteExplorer\Service\Crawler\HttpFetcherInterface;
use BcSiteExplorer\Service\Crawler\UrlNormalizer;
use Cake\ORM\TableRegistry;

/**
 * ResultMerger
 *
 * DB 由来の URL 一覧をクロール結果へ突き合わせる。
 * - クロールで到達済み → source='both' + コンテンツ情報を付加
 * - クロール未到達 → source='db' + is_orphan（孤立ページ）として登録
 * - クロールのみの行はそのまま（テーマ内ハードコードリンク等が浮き上がる）
 */
class ResultMerger
{

    /**
     * 突き合わせを実行する
     *
     * @param int $crawlId
     * @param iterable $dbRows DbUrlCollector::collect() が yield する配列
     * @param UrlNormalizer $normalizer クロール側と同一インスタンス設定の正規化器
     * @param bool $crawlPerformed クロールを実施したかどうか（false なら孤立判定しない）
     * @param HttpFetcherInterface|null $fetcher 孤立ページの生存確認用（null で確認しない）
     * @param callable|null $logger
     * @return array{matched: int, orphans: int, total: int}
     */
    public function merge(
        int $crawlId,
        iterable $dbRows,
        UrlNormalizer $normalizer,
        bool $crawlPerformed,
        ?HttpFetcherInterface $fetcher = null,
        ?callable $logger = null
    ): array
    {
        $resultsTable = TableRegistry::getTableLocator()->get('BcSiteExplorer.BcSiteExplorerResults');
        $matched = 0;
        $orphans = 0;
        $total = 0;

        foreach($dbRows as $row) {
            $url = $normalizer->normalize($row['url']);
            if (!$url) continue;
            $total++;

            $contentFields = [
                'content_id' => $row['content_id'] ?? null,
                'content_kind' => $row['content_kind'] ?? null,
                'content_title' => $row['content_title'] ?? null,
                'content_description' => $row['content_description'] ?? null,
                'content_status' => $row['content_status'] ?? null,
            ];

            $existing = $resultsTable->findByHash($crawlId, $normalizer->hash($url));
            if ($existing) {
                // クロール到達済み、または DB 側の重複（エイリアス等）
                if ($existing->source === 'crawl') {
                    $existing->source = 'both';
                    $matched++;
                }
                // 先勝ち: 既に別コンテンツが紐付いている場合は上書きしない
                if (!$existing->content_id) {
                    $resultsTable->patchEntity($existing, $contentFields);
                }
                $resultsTable->save($existing);
                continue;
            }

            $isOrphan = $crawlPerformed;
            $data = $contentFields + [
                'source' => 'db',
                'crawl_state' => 'skipped',
                'depth' => 0,
                'is_orphan' => $isOrphan,
            ];
            if ($isOrphan && $fetcher) {
                // 孤立ページの生存確認（1回だけアクセスして状態を記録する）
                $response = $fetcher->fetch($url);
                $data['http_status'] = $response['status'];
                $data['content_type_header'] = $response['content_type'];
                $data['final_url'] = $response['final_url'];
                $data['error_message'] = $response['error'];
                if ($response['redirect_chain']) {
                    $data['redirect_chain'] = json_encode($response['redirect_chain'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
                if (!empty($response['body']) && preg_match('/<title[^>]*>(.*?)<\/title>/si', $response['body'], $m)) {
                    $data['title'] = mb_substr(trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5)), 0, 500);
                }
            }
            $resultsTable->addUrl($crawlId, $url, $normalizer->hash($url), $data);
            if ($isOrphan) {
                $orphans++;
                if ($logger) $logger(__d('baser_core', '孤立ページを検出しました: {0}', $url));
            }
        }
        return ['matched' => $matched, 'orphans' => $orphans, 'total' => $total];
    }

}
