<?php
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 *
 * アクセスルール初期値
 */

return [
    'permission' => [

        /**
         * 管理画面
         */
        'BcSiteExplorerAdmin' => [
            'title' => __d('baser_core', 'サイトエクスプローラー管理'),
            'plugin' => 'BcSiteExplorer',
            'type' => 'Admin',
            'items' => [
                'Index' => ['title' => __d('baser_core', '実行・履歴一覧'), 'url' => '/baser/admin/bc-site-explorer/bc_site_explorer/index', 'method' => 'GET', 'auth' => false],
                'Execute' => ['title' => __d('baser_core', 'クロール実行'), 'url' => '/baser/admin/bc-site-explorer/bc_site_explorer/execute', 'method' => 'POST', 'auth' => false],
                'Status' => ['title' => __d('baser_core', '実行状態取得'), 'url' => '/baser/admin/bc-site-explorer/bc_site_explorer/status', 'method' => 'GET', 'auth' => false],
                'Results' => ['title' => __d('baser_core', '結果一覧'), 'url' => '/baser/admin/bc-site-explorer/bc_site_explorer/results/*', 'method' => 'GET', 'auth' => false],
                'Download' => ['title' => __d('baser_core', 'ダウンロード'), 'url' => '/baser/admin/bc-site-explorer/bc_site_explorer/download/*', 'method' => 'GET', 'auth' => false],
                'Delete' => ['title' => __d('baser_core', '削除'), 'url' => '/baser/admin/bc-site-explorer/bc_site_explorer/delete/*', 'method' => 'POST', 'auth' => false],
                'BatchDelete' => ['title' => __d('baser_core', '一括削除'), 'url' => '/baser/admin/bc-site-explorer/bc_site_explorer/batch_delete', 'method' => 'POST', 'auth' => false],
            ]
        ],
    ]
];
