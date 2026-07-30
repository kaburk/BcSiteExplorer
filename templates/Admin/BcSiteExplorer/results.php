<?php
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 *
 * [ADMIN] 結果一覧
 *
 * @var \BaserCore\View\BcAdminAppView $this
 * @var \BcSiteExplorer\Model\Entity\BcSiteExplorerCrawl $crawl
 * @var \Cake\Datasource\Paging\PaginatedInterface $results
 * @var array $kinds
 */

$this->BcAdmin->setTitle(__d('baser_core', 'サイトエクスプローラー｜結果一覧 (ID: {0})', $crawl->id));
$this->BcAdmin->setSearch('bc_site_explorer_results_index');

// ダウンロード URL に現在のフィルタ条件を引き継ぐ
$currentQuery = array_intersect_key($this->getRequest()->getQueryParams(),
  array_flip(['status_band', 'source', 'orphan', 'content_kind', 'keyword']));
$downloadUrl = function(array $params) use ($crawl, $currentQuery) {
  return ['action' => 'download', $crawl->id, '?' => array_merge($currentQuery, $params)];
};
?>

<style>
  .bca-main { min-width: 0; }
  .bc-site-explorer-results-wrap { overflow-x: auto; }
  .bc-site-explorer-results-wrap td { word-break: break-all; }
</style>

<div class="section bca-section">
  <p>
    <?php echo __d('baser_core', '開始URL: {0} ／ 取得 {1} 件 ／ エラー {2} 件 ／ 孤立 {3} 件',
      h($crawl->start_url), (int)$crawl->fetched_count, (int)$crawl->error_count, (int)$crawl->orphan_count) ?>
  </p>
  <div class="bca-actions">
    <?php echo $this->BcBaser->getLink(__d('baser_core', 'CSV ダウンロード (UTF-8)'), $downloadUrl(['format' => 'csv', 'encoding' => 'UTF-8']), ['class' => 'bca-btn', 'data-bca-btn-size' => 'sm']) ?>
    <?php echo $this->BcBaser->getLink(__d('baser_core', 'CSV ダウンロード (Shift-JIS)'), $downloadUrl(['format' => 'csv', 'encoding' => 'SJIS']), ['class' => 'bca-btn', 'data-bca-btn-size' => 'sm']) ?>
    <?php echo $this->BcBaser->getLink(__d('baser_core', 'Excel ダウンロード'), $downloadUrl(['format' => 'xlsx']), ['class' => 'bca-btn', 'data-bca-btn-size' => 'sm']) ?>
    <?php echo $this->BcBaser->getLink(__d('baser_core', '履歴一覧へ戻る'), ['action' => 'index'], ['class' => 'bca-btn', 'data-bca-btn-size' => 'sm']) ?>
  </div>
</div>

<div class="section bca-section bc-site-explorer-results-wrap">
  <?php $this->BcBaser->element('list_num') ?>
  <table class="list-table bca-table-listup">
    <thead class="bca-table-listup__thead">
    <tr>
      <th class="bca-table-listup__thead-th">No</th>
      <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('url', __d('baser_core', 'URL')) ?></th>
      <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('title', __d('baser_core', 'タイトル')) ?></th>
      <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('http_status', __d('baser_core', 'ステータス')) ?></th>
      <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', 'リダイレクト') ?></th>
      <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('depth', __d('baser_core', '深度')) ?></th>
      <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('content_kind', __d('baser_core', '種別')) ?></th>
      <th class="bca-table-listup__thead-th"><?php echo $this->Paginator->sort('source', __d('baser_core', '検出')) ?></th>
      <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', '孤立') ?></th>
    </tr>
    </thead>
    <tbody class="bca-table-listup__tbody">
    <?php $no = ($results->currentPage() - 1) * $results->perPage() ?>
    <?php foreach($results as $result): $no++ ?>
      <tr>
        <td class="bca-table-listup__tbody-td"><?php echo $no ?></td>
        <td class="bca-table-listup__tbody-td">
          <?php echo $this->BcBaser->getLink(h($result->url), $result->url, ['target' => '_blank', 'escape' => false]) ?>
          <?php if ($result->error_message): ?>
            <br><small class="bca-text-danger"><?php echo h($result->error_message) ?></small>
          <?php endif ?>
        </td>
        <td class="bca-table-listup__tbody-td">
          <?php echo h((string)$result->title) ?>
          <?php if ($result->content_title && $result->content_title !== $result->title): ?>
            <br><small>DB: <?php echo h($result->content_title) ?></small>
          <?php endif ?>
        </td>
        <td class="bca-table-listup__tbody-td"><?php echo h((string)$result->http_status) ?></td>
        <td class="bca-table-listup__tbody-td"><?php echo h($result->getRedirectChainText()) ?></td>
        <td class="bca-table-listup__tbody-td"><?php echo (int)$result->depth ?></td>
        <td class="bca-table-listup__tbody-td"><?php echo h((string)$result->content_kind) ?></td>
        <td class="bca-table-listup__tbody-td">
          <?php
          echo h(match ($result->source) {
            'both' => __d('baser_core', 'クロール+DB'),
            'crawl' => __d('baser_core', 'クロールのみ'),
            'db' => __d('baser_core', 'DBのみ'),
            default => (string)$result->source,
          });
          if ($result->crawl_state === 'external') {
            echo '<br><small>' . __d('baser_core', '外部') . '</small>';
          }
          ?>
        </td>
        <td class="bca-table-listup__tbody-td"><?php echo $result->is_orphan? __d('baser_core', '孤立') : '' ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  <?php $this->BcBaser->element('pagination') ?>
</div>
