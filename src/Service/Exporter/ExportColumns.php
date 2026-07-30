<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service\Exporter;

use BcSiteExplorer\Model\Entity\BcSiteExplorerResult;
use Cake\Core\Configure;

/**
 * ExportColumns
 *
 * エクスポート列の定義（列キー → ラベル・値の取り出し方）を一元管理する。
 * CSV / Excel の両エクスポーターがここを参照する。
 * 出力する列と順序は設定 `BcSiteExplorer.exportColumns` で制御できる。
 */
class ExportColumns
{

    /**
     * 全列の定義を取得する
     *
     * @return array キー => ラベル
     */
    public static function definitions(): array
    {
        return [
            'no' => __d('baser_core', 'No'),
            'url' => __d('baser_core', 'URL'),
            'title' => __d('baser_core', 'ページタイトル'),
            'content_title' => __d('baser_core', 'タイトル(コンテンツ管理)'),
            'content_description' => __d('baser_core', 'ディスクリプション(コンテンツ管理)'),
            'content_kind' => __d('baser_core', 'コンテンツ種別'),
            'content_status' => __d('baser_core', '公開状態'),
            'final_url' => __d('baser_core', '最終URL'),
            'redirect_chain' => __d('baser_core', 'リダイレクト'),
            'http_status' => __d('baser_core', 'HTTPステータス'),
            'depth' => __d('baser_core', '深度'),
            'parent_url' => __d('baser_core', '発見元'),
            'source' => __d('baser_core', '検出ソース'),
            'is_orphan' => __d('baser_core', '孤立'),
            'meta_robots' => __d('baser_core', 'meta robots'),
            'error_message' => __d('baser_core', 'エラー'),
        ];
    }

    /**
     * 設定で有効になっている列を順序どおりに取得する
     *
     * @return array キー => ラベル
     */
    public static function resolve(): array
    {
        $definitions = self::definitions();
        $keys = Configure::read('BcSiteExplorer.exportColumns');
        if (!is_array($keys) || !$keys) {
            return $definitions;
        }
        $columns = [];
        foreach($keys as $key) {
            if (!isset($definitions[$key])) {
                \Cake\Log\Log::warning('BcSiteExplorer: 不明なエクスポート列キーをスキップしました: ' . $key);
                continue;
            }
            $columns[$key] = $definitions[$key];
        }
        return $columns ?: $definitions;
    }

    /**
     * 結果行から列の値を取り出す
     *
     * @param BcSiteExplorerResult $row
     * @param string $key 列キー
     * @param int $no 行番号
     * @return string|int
     */
    public static function extract(BcSiteExplorerResult $row, string $key, int $no): string|int
    {
        switch($key) {
            case 'no':
                return $no;
            case 'redirect_chain':
                return $row->getRedirectChainText();
            case 'content_status':
                if ($row->content_status === null) return '';
                return $row->content_status? __d('baser_core', '公開') : __d('baser_core', '非公開');
            case 'source':
                return match ($row->source) {
                    'both' => __d('baser_core', 'クロール+DB'),
                    'crawl' => __d('baser_core', 'クロールのみ'),
                    'db' => __d('baser_core', 'DBのみ'),
                    default => (string)$row->source,
                };
            case 'is_orphan':
                return $row->is_orphan? __d('baser_core', '孤立') : '';
            default:
                $value = $row->get($key);
                return ($value === null)? '' : (string)$value;
        }
    }

}
