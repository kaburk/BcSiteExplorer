<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Model\Table;

use BcSiteExplorer\Model\Entity\BcSiteExplorerResult;
use Cake\ORM\Table;

/**
 * BcSiteExplorerResultsTable
 */
class BcSiteExplorerResultsTable extends Table
{

    /**
     * initialize
     *
     * @param array $config
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('bc_site_explorer_results');
        $this->addBehavior('Timestamp');
        $this->belongsTo('BcSiteExplorerCrawls', [
            'className' => 'BcSiteExplorer.BcSiteExplorerCrawls',
            'foreignKey' => 'crawl_id',
        ]);
    }

    /**
     * URL を登録する（重複時は登録しない）
     *
     * (crawl_id, url_hash) の unique index を正とし、
     * 既に存在する場合は null を返す。
     *
     * @param int $crawlId
     * @param string $url アクセスに使うクリーンな URL
     * @param string $urlHash UrlNormalizer::hash() による同一性ハッシュ
     * @param array $data 追加フィールド
     * @return BcSiteExplorerResult|null 新規登録した行。既存なら null
     */
    public function addUrl(int $crawlId, string $url, string $urlHash, array $data = []): ?BcSiteExplorerResult
    {
        $exists = $this->find()
            ->where(['crawl_id' => $crawlId, 'url_hash' => $urlHash])
            ->select(['id'])
            ->first();
        if ($exists) return null;
        /** @var BcSiteExplorerResult $entity */
        $entity = $this->newEntity($data + [
            'crawl_id' => $crawlId,
            'url' => $url,
            'url_hash' => $urlHash,
        ]);
        if (!$this->save($entity)) {
            // 同時実行で unique 制約に当たった場合は既存扱い
            return null;
        }
        return $entity;
    }

    /**
     * 同一性ハッシュで1件取得する
     *
     * @param int $crawlId
     * @param string $urlHash
     * @return BcSiteExplorerResult|null
     */
    public function findByHash(int $crawlId, string $urlHash): ?BcSiteExplorerResult
    {
        /** @var BcSiteExplorerResult|null $entity */
        $entity = $this->find()
            ->where(['crawl_id' => $crawlId, 'url_hash' => $urlHash])
            ->first();
        return $entity;
    }

    /**
     * 検索条件を適用したクエリを作成する（結果一覧・エクスポート共用）
     *
     * @param int $crawlId
     * @param array $params クエリパラメータ
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function createIndexQuery(int $crawlId, array $params = [])
    {
        $query = $this->find()->where(['BcSiteExplorerResults.crawl_id' => $crawlId]);

        // HTTP ステータス帯（2xx/3xx/4xx/5xx/error）
        if (!empty($params['status_band'])) {
            if ($params['status_band'] === 'error') {
                $query->where(['BcSiteExplorerResults.crawl_state' => 'error']);
            } else {
                $band = (int)$params['status_band'];
                $query->where([
                    'BcSiteExplorerResults.http_status >=' => $band * 100,
                    'BcSiteExplorerResults.http_status <' => ($band + 1) * 100,
                ]);
            }
        }
        // 検出ソース
        if (!empty($params['source'])) {
            $query->where(['BcSiteExplorerResults.source' => $params['source']]);
        }
        // 孤立のみ
        if (!empty($params['orphan'])) {
            $query->where(['BcSiteExplorerResults.is_orphan' => true]);
        }
        // コンテンツ種別
        if (!empty($params['content_kind'])) {
            $query->where(['BcSiteExplorerResults.content_kind' => $params['content_kind']]);
        }
        // URL・タイトル部分一致
        if (!empty($params['keyword'])) {
            $keyword = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $params['keyword']) . '%';
            $query->where(['OR' => [
                'BcSiteExplorerResults.url LIKE' => $keyword,
                'BcSiteExplorerResults.title LIKE' => $keyword,
                'BcSiteExplorerResults.content_title LIKE' => $keyword,
            ]]);
        }
        return $query->orderBy(['BcSiteExplorerResults.depth' => 'ASC', 'BcSiteExplorerResults.id' => 'ASC']);
    }

}
