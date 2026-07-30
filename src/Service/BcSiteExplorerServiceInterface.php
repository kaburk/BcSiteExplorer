<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service;

use BcSiteExplorer\Model\Entity\BcSiteExplorerCrawl;

/**
 * BcSiteExplorerServiceInterface
 */
interface BcSiteExplorerServiceInterface
{

    /**
     * 走査セッションを作成する
     *
     * @param array $data start_url / site_id / 各オプション
     * @return BcSiteExplorerCrawl 保存済みエンティティ（エラー時は getErrors() に格納）
     */
    public function createCrawl(array $data): BcSiteExplorerCrawl;

    /**
     * 走査を実行する（クロール → DB 突き合わせ）
     *
     * @param int $crawlId
     * @param callable|null $logger 進捗メッセージを受け取るコールバック
     * @return void
     */
    public function execute(int $crawlId, ?callable $logger = null): void;

    /**
     * 走査をバックグラウンド実行する（GUI 用）
     *
     * @param int $crawlId
     * @return void
     */
    public function startBackgroundCrawl(int $crawlId): void;

    /**
     * 実行状態を取得する
     *
     * @return array
     */
    public function getStatus(): array;

    /**
     * 保存済みの実行オプション（前回実行時の内容）を取得する
     *
     * @return array
     */
    public function getSavedOptions(): array;

    /**
     * 実行オプションを保存する（次回実行フォームの初期値になる）
     *
     * @param array $data
     * @return void
     */
    public function saveOptions(array $data): void;

    /**
     * 走査履歴を取得する
     *
     * @return \Cake\Datasource\ResultSetInterface
     */
    public function getCrawls();

    /**
     * 走査セッションと結果を削除する
     *
     * @param int $crawlId
     * @return void
     */
    public function deleteCrawl(int $crawlId): void;

    /**
     * 結果をエクスポートしてファイルパスを返す
     *
     * @param int $crawlId
     * @param array $filters 結果一覧と同じフィルタ条件
     * @param string $format csv | xlsx
     * @param array $options encoding 等
     * @return string 生成したファイルのパス
     */
    public function export(int $crawlId, array $filters, string $format = 'csv', array $options = []): string;

}
