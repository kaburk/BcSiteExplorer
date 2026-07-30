<?php
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 *
 * [ADMIN] 結果一覧 検索フォーム
 *
 * @var \BaserCore\View\BcAdminAppView $this
 * @var array $kinds
 */
$kinds = $this->get('kinds') ?: [];
?>
<?php echo $this->BcAdminForm->create(null, [
  'type' => 'get',
  'url' => ['action' => 'results', $this->getRequest()->getParam('pass.0')],
  'valueSources' => ['query', 'context']
]) ?>
<p class="bca-search__input-list">
  <span class="bca-search__input-item">
    <?php echo $this->BcAdminForm->label('keyword', __d('baser_core', 'URL・タイトル')) ?>
    <?php echo $this->BcAdminForm->control('keyword', ['type' => 'text', 'size' => 30]) ?>
  </span>
  <span class="bca-search__input-item">
    <?php echo $this->BcAdminForm->label('status_band', __d('baser_core', 'HTTPステータス')) ?>
    <?php echo $this->BcAdminForm->control('status_band', [
      'type' => 'select',
      'options' => [
        '2' => '2xx',
        '3' => '3xx',
        '4' => '4xx',
        '5' => '5xx',
        'error' => __d('baser_core', '取得エラー'),
      ],
      'empty' => __d('baser_core', '指定なし')
    ]) ?>
  </span>
  <span class="bca-search__input-item">
    <?php echo $this->BcAdminForm->label('source', __d('baser_core', '検出ソース')) ?>
    <?php echo $this->BcAdminForm->control('source', [
      'type' => 'select',
      'options' => [
        'both' => __d('baser_core', 'クロール+DB'),
        'crawl' => __d('baser_core', 'クロールのみ'),
        'db' => __d('baser_core', 'DBのみ'),
      ],
      'empty' => __d('baser_core', '指定なし')
    ]) ?>
  </span>
  <span class="bca-search__input-item">
    <?php echo $this->BcAdminForm->label('content_kind', __d('baser_core', 'コンテンツ種別')) ?>
    <?php echo $this->BcAdminForm->control('content_kind', [
      'type' => 'select',
      'options' => array_combine($kinds, $kinds),
      'empty' => __d('baser_core', '指定なし')
    ]) ?>
  </span>
  <span class="bca-search__input-item">
    <?php echo $this->BcAdminForm->control('orphan', [
      'type' => 'checkbox',
      'label' => __d('baser_core', '孤立ページのみ')
    ]) ?>
  </span>
</p>
<div class="bca-search__btns">
  <div class="bca-search__btns-item">
    <?php echo $this->BcAdminForm->submit(__d('baser_core', '検索'), [
      'div' => false,
      'class' => 'bca-btn',
      'data-bca-btn-type' => 'search'
    ]) ?>
  </div>
</div>
<?php echo $this->BcAdminForm->end() ?>
