<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Controller\Admin;

use BaserCore\Controller\Admin\BcAdminAppController;
use BaserCore\Error\BcException;
use BaserCore\Utility\BcSiteConfig;
use BcSiteExplorer\Service\BcSiteExplorerServiceInterface;
use BcSiteExplorer\Utility\BcSiteExplorerUtil;
use Cake\Core\Configure;
use Cake\Datasource\Exception\RecordNotFoundException;

/**
 * サイトエクスプローラー管理コントローラー
 */
class BcSiteExplorerController extends BcAdminAppController
{

    /**
     * [ADMIN] 実行フォーム・走査履歴一覧
     *
     * @param BcSiteExplorerServiceInterface $service
     * @return void
     */
    public function index(BcSiteExplorerServiceInterface $service)
    {
        $sites = $this->fetchTable('BaserCore.Sites')->find('list', keyField: 'id', valueField: 'display_name')->toArray();
        // フォーム初期値: 基本値 ← 設定ファイル ← 前回実行時の保存値 の順に上書き
        $defaults = array_merge(
            [
                'start_url' => (string)Configure::read('BcEnv.siteUrl'),
                'site_id' => '',
                'mode' => 'hybrid',
                'query_mode' => 'whitelist',
                'internal_base_url' => '',
                'basic_auth_user' => '',
            ],
            (array)Configure::read('BcSiteExplorer.crawlDefaults'),
            $service->getSavedOptions()
        );
        $this->set([
            'crawls' => $service->getCrawls(),
            'status' => $service->getStatus(),
            'canExec' => BcSiteExplorerUtil::canExec(),
            'sites' => $sites,
            'defaults' => $defaults,
            'rootPath' => ROOT,
        ]);
    }

    /**
     * [ADMIN] 走査実行（バックグラウンド起動）
     *
     * @param BcSiteExplorerServiceInterface $service
     * @return \Cake\Http\Response
     */
    public function execute(BcSiteExplorerServiceInterface $service)
    {
        $this->getRequest()->allowMethod(['post']);
        // 「設定のみ保存」ボタンからの送信は走査せず保存だけ行う
        if ($this->getRequest()->getData('save_only')) {
            $service->saveOptions($this->getRequest()->getData());
            $this->BcMessage->setSuccess(__d('baser_core', '実行オプションを保存しました。'));
            return $this->redirect(['action' => 'index']);
        }
        $crawl = $service->createCrawl($this->getRequest()->getData());
        if ($crawl->getErrors()) {
            $messages = [];
            foreach($crawl->getErrors() as $errors) {
                $messages = array_merge($messages, array_values((array)$errors));
            }
            $this->BcMessage->setError(implode(' ', $messages));
            return $this->redirect(['action' => 'index']);
        }
        try {
            $service->startBackgroundCrawl($crawl->id);
            $this->BcMessage->setSuccess(__d('baser_core', 'サイト探査を開始しました。完了までしばらくお待ちください。'));
        } catch (BcException $e) {
            $this->BcMessage->setError($e->getMessage());
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * [ADMIN] 実行状態の取得（ポーリング用）
     *
     * @param BcSiteExplorerServiceInterface $service
     * @return \Cake\Http\Response
     */
    public function status(BcSiteExplorerServiceInterface $service)
    {
        $this->getRequest()->allowMethod(['get']);
        return $this->getResponse()
            ->withType('application/json')
            ->withStringBody((string)json_encode($service->getStatus(), JSON_UNESCAPED_UNICODE));
    }

    /**
     * [ADMIN] 結果一覧
     *
     * @param int $crawlId
     * @return \Cake\Http\Response|void
     */
    public function results(int $crawlId)
    {
        $crawlsTable = $this->fetchTable('BcSiteExplorer.BcSiteExplorerCrawls');
        try {
            $crawl = $crawlsTable->get($crawlId);
        } catch (RecordNotFoundException) {
            $this->BcMessage->setError(__d('baser_core', '無効な処理です。'));
            return $this->redirect(['action' => 'index']);
        }
        $resultsTable = $this->fetchTable('BcSiteExplorer.BcSiteExplorerResults');
        $filters = $this->getRequest()->getQueryParams();
        $query = $resultsTable->createIndexQuery($crawlId, $filters);

        $results = $this->paginate($query, [
            'limit' => (int)(BcSiteConfig::get('admin_list_num') ?: 30),
        ]);

        $kinds = $resultsTable->find()
            ->where(['crawl_id' => $crawlId, 'content_kind IS NOT' => null])
            ->select(['content_kind'])
            ->distinct(['content_kind'])
            ->all()
            ->extract('content_kind')
            ->toList();

        $this->set([
            'crawl' => $crawl,
            'results' => $results,
            'kinds' => $kinds,
        ]);
    }

    /**
     * [ADMIN] エクスポートダウンロード
     *
     * 現在のフィルタ条件（クエリパラメータ）を引き継いで書き出す。
     *
     * @param BcSiteExplorerServiceInterface $service
     * @param int $crawlId
     * @return \Cake\Http\Response
     */
    public function download(BcSiteExplorerServiceInterface $service, int $crawlId)
    {
        $this->getRequest()->allowMethod(['get']);
        $params = $this->getRequest()->getQueryParams();
        $format = (($params['format'] ?? '') === 'xlsx')? 'xlsx' : 'csv';
        $encoding = (strtoupper($params['encoding'] ?? '') === 'SJIS')? 'SJIS' : 'UTF-8';
        try {
            $path = $service->export($crawlId, $params, $format, ['encoding' => $encoding]);
        } catch (RecordNotFoundException) {
            $this->BcMessage->setError(__d('baser_core', '無効な処理です。'));
            return $this->redirect(['action' => 'index']);
        } catch (BcException $e) {
            $this->BcMessage->setError($e->getMessage());
            return $this->redirect(['action' => 'results', $crawlId]);
        }
        $name = sprintf('sitemap_%d_%s.%s', $crawlId, date('Ymd_His'), $format);
        return $this->getResponse()->withFile($path, ['download' => true, 'name' => $name]);
    }

    /**
     * [ADMIN] 走査セッションの一括削除
     *
     * @param BcSiteExplorerServiceInterface $service
     * @return \Cake\Http\Response
     */
    public function batch_delete(BcSiteExplorerServiceInterface $service)
    {
        $this->getRequest()->allowMethod(['post']);
        $ids = array_filter((array)$this->getRequest()->getData('batch_targets'), 'is_numeric');
        if (!$ids) {
            $this->BcMessage->setError(__d('baser_core', '削除する走査履歴を選択してください。'));
            return $this->redirect(['action' => 'index']);
        }
        $deleted = 0;
        foreach($ids as $id) {
            try {
                $service->deleteCrawl((int)$id);
                $deleted++;
            } catch (RecordNotFoundException) {
                // 既に削除済みの行はスキップする
            } catch (\Throwable $e) {
                $this->BcMessage->setError(__d('baser_core', '走査履歴 (ID: {0}) の削除に失敗しました。{1}', $id, $e->getMessage()));
            }
        }
        if ($deleted) {
            $this->BcMessage->setSuccess(__d('baser_core', '{0} 件の走査履歴を削除しました。', $deleted));
        }
        return $this->redirect(['action' => 'index']);
    }

    /**
     * [ADMIN] 走査セッションの削除
     *
     * @param BcSiteExplorerServiceInterface $service
     * @param int $crawlId
     * @return \Cake\Http\Response
     */
    public function delete(BcSiteExplorerServiceInterface $service, int $crawlId)
    {
        $this->getRequest()->allowMethod(['post', 'delete']);
        try {
            $service->deleteCrawl($crawlId);
            $this->BcMessage->setSuccess(__d('baser_core', '走査結果を削除しました。'));
        } catch (RecordNotFoundException) {
            $this->BcMessage->setError(__d('baser_core', '無効な処理です。'));
        } catch (\Throwable $e) {
            $this->BcMessage->setError(__d('baser_core', '走査結果の削除に失敗しました。{0}', $e->getMessage()));
        }
        return $this->redirect(['action' => 'index']);
    }

}
