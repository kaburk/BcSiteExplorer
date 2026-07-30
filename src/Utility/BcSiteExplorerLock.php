<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Utility;

/**
 * BcSiteExplorerLock
 *
 * flock によるロックファイルで多重実行を防止する。
 * GUI と CLI（cron）の同時実行事故を防ぐ。
 */
class BcSiteExplorerLock
{

    /**
     * ロックファイルのハンドル
     * @var resource|null
     */
    protected $handle = null;

    /**
     * ロックファイルのパス
     * @var string
     */
    protected string $path;

    /**
     * constructor.
     */
    public function __construct()
    {
        $this->path = BcSiteExplorerUtil::getWorkDir() . 'bc_site_explorer.lock';
    }

    /**
     * ロックを取得する（ノンブロッキング）
     *
     * @return bool 取得できなければ false
     */
    public function acquire(): bool
    {
        $handle = fopen($this->path, 'c');
        if (!$handle) return false;
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }
        ftruncate($handle, 0);
        fwrite($handle, (string)getmypid());
        fflush($handle);
        $this->handle = $handle;
        return true;
    }

    /**
     * ロック中かどうか（自プロセス以外がロックを保持しているか）
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        if ($this->handle) return true;
        if (!file_exists($this->path)) return false;
        $handle = fopen($this->path, 'c');
        if (!$handle) return true;
        $acquired = flock($handle, LOCK_EX | LOCK_NB);
        if ($acquired) flock($handle, LOCK_UN);
        fclose($handle);
        return !$acquired;
    }

    /**
     * ロックを解放する
     *
     * @return void
     */
    public function release(): void
    {
        if ($this->handle) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }

}
