<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 *
 * ユニットテスト用の軽量 bootstrap
 *
 * 純粋ロジック（UrlNormalizer / LinkExtractor / Exporter 等）のテスト向け。
 * アプリ本体の bootstrap・DB は使わない。
 * monorepo ルート（basercms 本体）の vendor を利用する。
 */

$root = dirname(__DIR__, 3);
require $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
if (!defined('TMP')) define('TMP', sys_get_temp_dir() . DS);

// CakePHP のグローバル関数（__d, h, env 等）
require_once $root . '/vendor/cakephp/cakephp/src/Core/functions_global.php';
require_once $root . '/vendor/cakephp/cakephp/src/I18n/functions_global.php';

// __d() の翻訳ローダーが参照するキャッシュ設定（テストでは無効化）
foreach(['_cake_core_', '_cake_translations_', '_cake_model_'] as $cacheConfig) {
    if (!\Cake\Cache\Cache::getConfig($cacheConfig)) {
        \Cake\Cache\Cache::setConfig($cacheConfig, ['className' => 'Null']);
    }
}

// プラグインの src をオートロード（ルート composer.json には未登録のため）
spl_autoload_register(function($class) {
    $prefix = 'BcSiteExplorer\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = dirname(__DIR__) . DS . 'src' . DS
        . str_replace('\\', DS, substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require $path;
});
