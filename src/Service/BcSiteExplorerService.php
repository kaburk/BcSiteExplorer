<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service;

use BaserCore\Error\BcException;
use BaserCore\Utility\BcContainerTrait;
use BcSiteExplorer\Model\Entity\BcSiteExplorerCrawl;
use BcSiteExplorer\Service\Collector\DbUrlCollector;
use BcSiteExplorer\Service\Crawler\HttpFetcher;
use BcSiteExplorer\Service\Crawler\SiteCrawler;
use BcSiteExplorer\Service\Crawler\UrlNormalizer;
use BcSiteExplorer\Service\Exporter\CsvExporter;
use BcSiteExplorer\Service\Exporter\ExporterInterface;
use BcSiteExplorer\Service\Exporter\ExportColumns;
use BcSiteExplorer\Service\Exporter\XlsxExporter;
use BcSiteExplorer\Utility\BcSiteExplorerLock;
use BcSiteExplorer\Utility\BcSiteExplorerUtil;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\Log\LogTrait;
use Cake\ORM\TableRegistry;

/**
 * BcSiteExplorerService
 *
 * 走査全体のオーケストレーション。
 * クロール → DB 突き合わせ → カウント確定までを execute() が担い、
 * GUI からは startBackgroundCrawl() で CLI コマンドを裏起動する。
 */
class BcSiteExplorerService implements BcSiteExplorerServiceInterface
{

    use BcContainerTrait;
    use LogTrait;

    /**
     * オプションとして受け付けるキー
     * @var array
     */
    public const OPTION_KEYS = [
        'mode',
        'max_pages',
        'max_depth',
        'interval_ms',
        'timeout',
        'exclude_external',
        'record_external',
        'verify_ssl',
        'query_mode',
        'check_orphans',
        'include_blog_extra_urls',
        'internal_base_url',
        'basic_auth_user',
        'basic_auth_password',
    ];

    /**
     * 次回実行の初期値として保存するキー
     *
     * Basic 認証のパスワードは平文で DB に残さないため保存しない。
     */
    public const SAVED_OPTION_KEYS = [
        'start_url',
        'site_id',
        'mode',
        'max_pages',
        'max_depth',
        'interval_ms',
        'timeout',
        'exclude_external',
        'record_external',
        'verify_ssl',
        'query_mode',
        'check_orphans',
        'include_blog_extra_urls',
        'internal_base_url',
        'basic_auth_user',
    ];

    /**
     * @inheritDoc
     */
    public function createCrawl(array $data): BcSiteExplorerCrawl
    {
        $options = array_intersect_key($data, array_flip(self::OPTION_KEYS));
        /** @var BcSiteExplorerCrawl $crawl */
        $crawl = $this->getCrawlsTable()->newEntity([
            'site_id' => ($data['site_id'] ?? null)? (int)$data['site_id'] : null,
            'start_url' => (string)($data['start_url'] ?? ''),
            'options' => json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status' => 'waiting',
        ]);
        if (!$crawl->getErrors()) {
            $this->getCrawlsTable()->save($crawl);
            // 今回の内容を次回実行フォームの初期値として保存する
            $this->saveOptions($data);
        }
        return $crawl;
    }

    /**
     * @inheritDoc
     */
    public function getSavedOptions(): array
    {
        try {
            $saved = $this->getConfigsTable()->getKeyValue();
        } catch (\Throwable) {
            $saved = [];
        }
        if (!is_array($saved)) $saved = [];
        return array_intersect_key($saved, array_flip(self::SAVED_OPTION_KEYS));
    }

    /**
     * @inheritDoc
     */
    public function saveOptions(array $data): void
    {
        $data = array_intersect_key($data, array_flip(self::SAVED_OPTION_KEYS));
        if (!$data) return;
        try {
            $this->getConfigsTable()->saveKeyValue($data);
        } catch (\Throwable $e) {
            // オプション保存の失敗で走査自体は止めない
            $this->log(__d('baser_core', '実行オプションの保存に失敗しました。{0}', $e->getMessage()), 'warning');
        }
    }

    /**
     * @inheritDoc
     */
    public function execute(int $crawlId, ?callable $logger = null): void
    {
        $lock = new BcSiteExplorerLock();
        if (!$lock->acquire()) {
            throw new BcException(__d('baser_core', '他のサイト探査が実行中です。'));
        }
        set_time_limit(0);
        $crawlsTable = $this->getCrawlsTable();
        /** @var BcSiteExplorerCrawl $crawl */
        $crawl = $crawlsTable->get($crawlId);
        $options = array_merge(
            (array)Configure::read('BcSiteExplorer.crawlDefaults'),
            $crawl->getOptionsArray()
        );
        $mode = $options['mode'] ?? 'hybrid';

        $crawl->status = 'running';
        $crawl->started = DateTime::now();
        $crawl->error_message = null;
        $crawlsTable->save($crawl);
        $this->updateStatus([
            'state' => 'running',
            'crawl_id' => $crawl->id,
            'message' => __d('baser_core', 'サイト探査を開始しました。'),
            'started' => date('Y-m-d H:i:s'),
            'finished' => null,
        ]);

        $normalizer = new UrlNormalizer((string)($options['query_mode'] ?? 'whitelist'));
        try {
            $fetcher = new HttpFetcher($options);

            if ($mode !== 'db') {
                $crawler = new SiteCrawler($fetcher, $normalizer);
                $crawler->crawl($crawl, $options, function(array $progress) use ($crawlsTable, $crawl, $logger) {
                    $crawl->fetched_count = $progress['fetched'];
                    $crawl->queued_count = $progress['queued'];
                    $crawl->error_count = $progress['errors'];
                    $crawlsTable->save($crawl);
                    $message = __d('baser_core', '取得済み {0} 件 / キュー {1} 件（深度 {2}）', $progress['fetched'], $progress['queued'], $progress['depth']);
                    $this->updateStatus(['message' => $message]);
                    $this->notify($logger, $message . ' ' . ($progress['url'] ?? ''));
                });
            }

            if ($mode !== 'crawl') {
                $message = __d('baser_core', 'コンテンツ管理DBと突き合わせています...');
                $this->updateStatus(['message' => $message]);
                $this->notify($logger, $message);
                $collector = new DbUrlCollector();
                $merger = new ResultMerger();
                $stats = $merger->merge(
                    $crawl->id,
                    $collector->collect($crawl->site_id, $options, $logger),
                    $normalizer,
                    ($mode !== 'db'),
                    !empty($options['check_orphans'])? $fetcher : null,
                    $logger
                );
                $crawl->db_matched_count = $stats['matched'];
                $crawl->orphan_count = $stats['orphans'];
            }

            $this->refreshCounts($crawl);
            $crawl->status = 'completed';
            $crawl->finished = DateTime::now();
            $crawlsTable->save($crawl);

            $message = __d('baser_core', 'サイト探査が完了しました。取得 {0} 件 / エラー {1} 件 / 孤立 {2} 件', $crawl->fetched_count, $crawl->error_count, $crawl->orphan_count);
            $this->updateStatus(['state' => 'success', 'message' => $message, 'finished' => date('Y-m-d H:i:s')]);
            $this->notify($logger, $message);
            $this->log($message, 'info');
        } catch (\Throwable $e) {
            $crawl->status = 'failed';
            $crawl->error_message = $e->getMessage();
            $crawl->finished = DateTime::now();
            $crawlsTable->save($crawl);
            $this->updateStatus(['state' => 'error', 'message' => $e->getMessage(), 'finished' => date('Y-m-d H:i:s')]);
            $this->log(__d('baser_core', 'サイト探査に失敗しました。{0}', $e->getMessage()), 'error');
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * @inheritDoc
     */
    public function startBackgroundCrawl(int $crawlId): void
    {
        if (!BcSiteExplorerUtil::canExec() || !function_exists('exec')) {
            throw new BcException(__d('baser_core', 'この環境ではコマンド実行が許可されていないため、画面からの実行はできません。CLI をご利用ください。'));
        }
        $lock = new BcSiteExplorerLock();
        if ($lock->isLocked()) {
            throw new BcException(__d('baser_core', '他のサイト探査が実行中です。'));
        }
        $cake = ROOT . DS . 'bin' . DS . 'cake.php';
        if (!is_file($cake)) {
            throw new BcException(__d('baser_core', 'CLI コマンドが見つかりません。{0}', $cake));
        }
        // 起動直後のポーリングが直前の結果を拾わないよう、先に running へ更新しておく
        $this->updateStatus([
            'state' => 'running',
            'crawl_id' => $crawlId,
            'message' => __d('baser_core', 'サイト探査を起動しています...'),
            'started' => date('Y-m-d H:i:s'),
            'finished' => null,
        ]);
        $logFile = BcSiteExplorerUtil::getWorkDir() . 'gui_run.log';
        $command = sprintf(
            'nohup %s %s bc_site_explorer crawl --crawl-id %d >> %s 2>&1 &',
            escapeshellarg($this->getPhpCliBinary()),
            escapeshellarg($cake),
            $crawlId,
            escapeshellarg($logFile)
        );
        exec($command);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): array
    {
        $statusFile = BcSiteExplorerUtil::getWorkDir() . 'status.json';
        $status = ['state' => 'idle', 'message' => '', 'crawl_id' => null, 'started' => null, 'finished' => null];
        if (is_file($statusFile)) {
            $saved = json_decode((string)file_get_contents($statusFile), true);
            if (is_array($saved)) $status = array_merge($status, $saved);
        }
        // プロセスがクラッシュした場合に running のまま残らないよう、ロック実態を優先する
        $lock = new BcSiteExplorerLock();
        if ($status['state'] === 'running' && !$lock->isLocked()) {
            // バックグラウンド起動直後（コマンドがまだロックを取っていない）を考慮して短時間は許容する
            $startedAt = $status['started']? strtotime((string)$status['started']) : 0;
            if ($startedAt && (time() - $startedAt) > 30) {
                $status['state'] = 'error';
                $status['message'] = __d('baser_core', 'サイト探査が異常終了した可能性があります。ログを確認してください。');
            }
        }
        // 実行中は進捗カウントを DB から付加する
        if ($status['state'] === 'running' && $status['crawl_id']) {
            try {
                $crawl = $this->getCrawlsTable()->get($status['crawl_id']);
                $status['fetched_count'] = $crawl->fetched_count;
                $status['queued_count'] = $crawl->queued_count;
                $status['error_count'] = $crawl->error_count;
            } catch (\Throwable) {
            }
        }
        return $status;
    }

    /**
     * @inheritDoc
     */
    public function getCrawls()
    {
        return $this->getCrawlsTable()->find()
            ->orderBy(['BcSiteExplorerCrawls.id' => 'DESC'])
            ->limit(50)
            ->all();
    }

    /**
     * @inheritDoc
     */
    public function deleteCrawl(int $crawlId): void
    {
        $crawlsTable = $this->getCrawlsTable();
        $crawl = $crawlsTable->get($crawlId);
        // 結果は件数が多くなり得るため deleteAll で削除する（子テーブルに更なる子は無い）
        $this->getResultsTable()->deleteAll(['crawl_id' => $crawl->id]);
        $crawlsTable->delete($crawl);
    }

    /**
     * @inheritDoc
     */
    public function export(int $crawlId, array $filters, string $format = 'csv', array $options = []): string
    {
        // 存在確認（RecordNotFoundException を上位へ）
        $this->getCrawlsTable()->get($crawlId);
        $query = $this->getResultsTable()->createIndexQuery($crawlId, $filters);
        return $this->createExporter($format)->export(
            $this->iterateResults($query),
            ExportColumns::resolve(),
            $options
        );
    }

    /**
     * フォーマットに応じたエクスポーターを生成する
     *
     * @param string $format
     * @return ExporterInterface
     */
    protected function createExporter(string $format): ExporterInterface
    {
        switch($format) {
            case 'csv':
                return new CsvExporter();
            case 'xlsx':
                if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                    throw new BcException(__d('baser_core', 'Excel 出力には phpoffice/phpspreadsheet が必要です。プラグインディレクトリで composer install を実行してください。'));
                }
                return new XlsxExporter();
        }
        throw new BcException(__d('baser_core', '不明なエクスポート形式です。{0}', $format));
    }

    /**
     * 結果をチャンク単位で反復する
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @return \Generator
     */
    protected function iterateResults($query): \Generator
    {
        $page = 1;
        $limit = 1000;
        while(true) {
            $rows = (clone $query)->limit($limit)->page($page)->all();
            if (!$rows->count()) break;
            foreach($rows as $row) {
                yield $row;
            }
            if ($rows->count() < $limit) break;
            $page++;
        }
    }

    /**
     * クロール状態別の件数を確定する
     *
     * @param BcSiteExplorerCrawl $crawl
     * @return void
     */
    protected function refreshCounts(BcSiteExplorerCrawl $crawl): void
    {
        $resultsTable = $this->getResultsTable();
        $count = function(array $conditions) use ($resultsTable, $crawl) {
            return $resultsTable->find()
                ->where(['crawl_id' => $crawl->id] + $conditions)
                ->count();
        };
        $crawl->fetched_count = $count(['crawl_state' => 'fetched']);
        $crawl->error_count = $count(['crawl_state' => 'error']);
        $crawl->queued_count = $count(['crawl_state' => 'queued']);
    }

    /**
     * 実行状態を保存する
     *
     * @param array $status
     * @return void
     */
    protected function updateStatus(array $status): void
    {
        $statusFile = BcSiteExplorerUtil::getWorkDir() . 'status.json';
        $current = [];
        if (is_file($statusFile)) {
            $saved = json_decode((string)file_get_contents($statusFile), true);
            if (is_array($saved)) $current = $saved;
        }
        file_put_contents($statusFile, json_encode(array_merge($current, $status), JSON_UNESCAPED_UNICODE));
    }

    /**
     * 進捗を通知する
     *
     * @param callable|null $logger
     * @param string $message
     * @return void
     */
    protected function notify(?callable $logger, string $message): void
    {
        if ($logger) $logger($message);
    }

    /**
     * PHP CLI バイナリのパスを解決する
     *
     * Web 実行時の PHP_BINARY は php-fpm 等を指すため、CLI バイナリを優先して探す。
     *
     * @return string
     */
    protected function getPhpCliBinary(): string
    {
        if (PHP_SAPI === 'cli') return PHP_BINARY;
        foreach([PHP_BINDIR . DS . 'php', '/usr/local/bin/php', '/usr/bin/php'] as $candidate) {
            if (is_executable($candidate)) return $candidate;
        }
        return 'php';
    }

    /**
     * Crawls テーブルを取得する
     *
     * @return \BcSiteExplorer\Model\Table\BcSiteExplorerCrawlsTable
     */
    protected function getCrawlsTable()
    {
        return TableRegistry::getTableLocator()->get('BcSiteExplorer.BcSiteExplorerCrawls');
    }

    /**
     * Results テーブルを取得する
     *
     * @return \BcSiteExplorer\Model\Table\BcSiteExplorerResultsTable
     */
    protected function getResultsTable()
    {
        return TableRegistry::getTableLocator()->get('BcSiteExplorer.BcSiteExplorerResults');
    }

    /**
     * Configs テーブルを取得する
     *
     * @return \BcSiteExplorer\Model\Table\BcSiteExplorerConfigsTable
     */
    protected function getConfigsTable()
    {
        return TableRegistry::getTableLocator()->get('BcSiteExplorer.BcSiteExplorerConfigs');
    }

}
