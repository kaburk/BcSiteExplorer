<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Utility;

/**
 * BcSiteExplorerUtil
 *
 * 環境検出・作業ディレクトリなどの共通処理
 */
class BcSiteExplorerUtil
{

    /**
     * プラグインの作業ディレクトリ（ロック・ステータス・一時ファイル置き場）を取得する
     *
     * @return string 末尾 DS 付きのパス
     */
    public static function getWorkDir(): string
    {
        $dir = TMP . 'bc_site_explorer' . DS;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * 外部コマンドが実行可能な環境かどうか
     *
     * @return bool
     */
    public static function canExec(): bool
    {
        if (!function_exists('proc_open') || !function_exists('proc_close')) return false;
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return !in_array('proc_open', $disabled, true);
    }

}
