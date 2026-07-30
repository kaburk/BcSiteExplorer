<?php
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 *
 * デフォルト設定。環境ごとの上書きは config/setting_customize.php で行う
 * （setting_customize.php.default をリネームして使用。Git 管理対象外）。
 */

use Cake\Utility\Hash;

$config = [
    'BcApp' => [
        /**
         * システムナビ
         */
        'adminNavigation' => [
            'Plugins' => [
                'menus' => [
                    'BcSiteExplorer' => [
                        'title' => __d('baser_core', 'サイトエクスプローラー'),
                        'url' => [
                            'prefix' => 'Admin',
                            'plugin' => 'BcSiteExplorer',
                            'controller' => 'BcSiteExplorer',
                            'action' => 'index'
                        ]
                    ]
                ]
            ],
        ]
    ],
    'BcSiteExplorer' => [
        /**
         * エクスポート列
         *
         * 配列の順序がそのまま CSV / Excel の列順になる。
         * 不要な列はキーを削除する。config/setting_customize.php で
         * 同キーを定義すると上書きできる（リストはカスタマイズ側で丸ごと置換される）。
         */
        'exportColumns' => [
            'no',
            'url',
            'title',
            'content_title',
            'content_description',
            'content_kind',
            'content_status',
            'final_url',
            'redirect_chain',
            'http_status',
            'depth',
            'parent_url',
            'source',
            'is_orphan',
            'meta_robots',
            'error_message',
        ],
        /**
         * クロールの既定オプション
         */
        'crawlDefaults' => [
            'max_pages' => 1000,
            'max_depth' => 10,
            'interval_ms' => 500,
            'timeout' => 10,
            'exclude_external' => true,
            'record_external' => true,
            'verify_ssl' => true,
            'check_orphans' => true,
            'include_blog_extra_urls' => false,
        ],
    ],
];

// setting_customize.php が存在すれば深いマージで上書き
if (file_exists(__DIR__ . DS . 'setting_customize.php')) {
    include __DIR__ . DS . 'setting_customize.php';
    if (!empty($customize_config) && is_array($customize_config)) {
        $config = Hash::merge($config, $customize_config);
        // Hash::merge はインデックス配列（exportColumns 等のリスト）を追記してしまうため、
        // リスト型の設定はカスタマイズ側の値で丸ごと置換する
        foreach($customize_config as $sectionKey => $section) {
            if (!is_array($section)) continue;
            foreach($section as $key => $value) {
                if (is_array($value) && array_is_list($value)) {
                    $config[$sectionKey][$key] = $value;
                }
            }
        }
    }
}

return $config;
