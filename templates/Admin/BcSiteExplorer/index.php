<?php
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 *
 * [ADMIN] 実行フォーム・走査履歴一覧
 *
 * @var \BaserCore\View\BcAdminAppView $this
 * @var \Cake\Datasource\ResultSetInterface $crawls
 * @var array $status
 * @var bool $canExec
 * @var array $sites
 * @var array $defaults 前回実行時の保存値をマージ済みのフォーム初期値
 * @var string $rootPath
 */

$this->BcAdmin->setTitle(__d('baser_core', 'サイトエクスプローラー'));
?>

<!-- 実行状態 -->
<div class="section bca-section" id="BcSiteExplorerStatusSection" <?php if ($status['state'] !== 'running') echo 'style="display:none"' ?>>
  <div class="bca-panel-box">
    <h2><?php echo __d('baser_core', '実行状態') ?></h2>
    <p id="BcSiteExplorerStatusMessage"><?php echo h($status['message']) ?></p>
  </div>
</div>

<!-- 探査実行 -->
<div class="section bca-section">
  <h2><?php echo __d('baser_core', 'サイト探査を実行') ?></h2>
  <?php if ($canExec): ?>
    <p><?php echo __d('baser_core', '開始URLからリンクを辿ってサイト全体の URL・タイトル・リダイレクトを収集し、コンテンツ管理の情報と突き合わせます。探査はバックグラウンドで実行されます。') ?></p>
    <?php echo $this->BcAdminForm->create(null, ['url' => ['action' => 'execute'], 'valueSources' => ['data', 'context']]) ?>
    <table class="list-table bca-form-table">
      <tr>
        <th class="bca-form-table__label">
          <?php echo $this->BcAdminForm->label('start_url', __d('baser_core', '開始URL')) ?>
        </th>
        <td class="bca-form-table__input">
          <?php echo $this->BcAdminForm->control('start_url', ['type' => 'text', 'size' => 60, 'value' => $defaults['start_url']]) ?>
        </td>
      </tr>
      <tr>
        <th class="bca-form-table__label">
          <?php echo $this->BcAdminForm->label('mode', __d('baser_core', 'モード')) ?>
        </th>
        <td class="bca-form-table__input">
          <?php echo $this->BcAdminForm->control('mode', [
            'type' => 'radio',
            'options' => [
              'hybrid' => __d('baser_core', 'ハイブリッド（クロール＋コンテンツ管理DB突合）'),
              'crawl' => __d('baser_core', 'クロールのみ'),
              'db' => __d('baser_core', 'コンテンツ管理DBのみ（クロールしない）'),
            ],
            'value' => $defaults['mode']
          ]) ?>
        </td>
      </tr>
      <tr>
        <th class="bca-form-table__label">
          <?php echo $this->BcAdminForm->label('site_id', __d('baser_core', '対象サイト')) ?>
        </th>
        <td class="bca-form-table__input">
          <?php echo $this->BcAdminForm->control('site_id', [
            'type' => 'select',
            'options' => $sites,
            'empty' => __d('baser_core', '全サイト'),
            'value' => $defaults['site_id']
          ]) ?>
          <i class="bca-icon--question-circle bca-help"></i>
          <div class="bca-helptext"><?php echo __d('baser_core', 'コンテンツ管理DBから列挙する範囲です。クロール自体は開始URLから辿れる範囲が対象になります。') ?></div>
        </td>
      </tr>
      <tr>
        <th class="bca-form-table__label"><?php echo __d('baser_core', '上限') ?></th>
        <td class="bca-form-table__input">
          <small>[<?php echo __d('baser_core', '最大ページ数') ?>]</small>&nbsp;
          <?php echo $this->BcAdminForm->control('max_pages', ['type' => 'text', 'size' => 8, 'value' => $defaults['max_pages'] ?? 1000]) ?>
          <small>[<?php echo __d('baser_core', '最大深度') ?>]</small>&nbsp;
          <?php echo $this->BcAdminForm->control('max_depth', ['type' => 'text', 'size' => 4, 'value' => $defaults['max_depth'] ?? 10]) ?>
          <small>[<?php echo __d('baser_core', 'リクエスト間隔(ms)') ?>]</small>&nbsp;
          <?php echo $this->BcAdminForm->control('interval_ms', ['type' => 'text', 'size' => 6, 'value' => $defaults['interval_ms'] ?? 500]) ?>
        </td>
      </tr>
      <tr>
        <th class="bca-form-table__label"><?php echo __d('baser_core', '外部サイト') ?></th>
        <td class="bca-form-table__input">
          <?php echo $this->BcAdminForm->control('exclude_external', [
            'type' => 'checkbox',
            'label' => __d('baser_core', '外部サイトを探査対象外にする'),
            'checked' => !empty($defaults['exclude_external'])
          ]) ?><br>
          <?php echo $this->BcAdminForm->control('record_external', [
            'type' => 'checkbox',
            'label' => __d('baser_core', '外部サイトへのリンクを一覧に記録する'),
            'checked' => !empty($defaults['record_external'])
          ]) ?>
        </td>
      </tr>
      <tr>
        <th class="bca-form-table__label"><?php echo __d('baser_core', 'オプション') ?></th>
        <td class="bca-form-table__input">
          <?php echo $this->BcAdminForm->control('check_orphans', [
            'type' => 'checkbox',
            'label' => __d('baser_core', '孤立ページ（リンクされていないページ）の生存確認を行う'),
            'checked' => !empty($defaults['check_orphans'])
          ]) ?><br>
          <?php echo $this->BcAdminForm->control('include_blog_extra_urls', [
            'type' => 'checkbox',
            'label' => __d('baser_core', 'ブログの派生URL（RSS・カテゴリ・タグ・日付・著者・ページ送り）もDB側から列挙する'),
            'checked' => !empty($defaults['include_blog_extra_urls'])
          ]) ?><br>
          <?php echo $this->BcAdminForm->control('verify_ssl', [
            'type' => 'checkbox',
            'label' => __d('baser_core', 'SSL証明書を検証する'),
            'checked' => !empty($defaults['verify_ssl'])
          ]) ?>
          <i class="bca-icon--question-circle bca-help"></i>
          <div class="bca-helptext"><?php echo __d('baser_core', 'ローカル開発環境など自己署名証明書のサイトでは「cURL Error (60) SSL certificate problem」で取得に失敗するため、オフにしてください。本番サイトではオンのままを推奨します。') ?></div>
          <br>
          <small>[<?php echo __d('baser_core', 'クエリ付きURLの扱い') ?>]</small>&nbsp;
          <?php echo $this->BcAdminForm->control('query_mode', [
            'type' => 'select',
            'options' => [
              'whitelist' => __d('baser_core', 'ページ送り等のみ保持（推奨）'),
              'drop' => __d('baser_core', 'クエリを全て無視'),
              'keep' => __d('baser_core', 'クエリを全て保持'),
            ],
            'value' => $defaults['query_mode']
          ]) ?>
        </td>
      </tr>
      <tr>
        <th class="bca-form-table__label"><?php echo __d('baser_core', '接続設定') ?></th>
        <td class="bca-form-table__input">
          <small>[<?php echo __d('baser_core', '内部アクセスURL') ?>]</small>&nbsp;
          <?php echo $this->BcAdminForm->control('internal_base_url', ['type' => 'text', 'size' => 40, 'value' => $defaults['internal_base_url']]) ?>
          <i class="bca-icon--question-circle bca-help"></i>
          <div class="bca-helptext"><?php echo __d('baser_core', 'Docker 等でサーバ内から公開URLへ接続できない場合に指定します（例: http://localhost）。接続先だけ差し替え、Host ヘッダには公開ホスト名を送ります。') ?></div>
          <br>
          <small>[<?php echo __d('baser_core', 'Basic認証') ?>]</small>&nbsp;
          <?php echo $this->BcAdminForm->control('basic_auth_user', ['type' => 'text', 'size' => 20, 'placeholder' => __d('baser_core', 'ユーザー名'), 'value' => $defaults['basic_auth_user']]) ?>
          <?php echo $this->BcAdminForm->control('basic_auth_password', ['type' => 'password', 'size' => 20, 'placeholder' => __d('baser_core', 'パスワード'), 'autocomplete' => 'new-password']) ?>
          <i class="bca-icon--question-circle bca-help"></i>
          <div class="bca-helptext"><?php echo __d('baser_core', 'パスワードは保存されないため、Basic認証を使う場合は実行のたびに入力してください。') ?></div>
        </td>
      </tr>
    </table>
    <div class="submit bca-actions">
      <div class="bca-actions__main">
        <?php echo $this->BcAdminForm->submit(__d('baser_core', '探査開始'), [
          'div' => false,
          'class' => 'bca-btn bca-actions__item',
          'data-bca-btn-type' => 'save',
          'data-bca-btn-width' => 'lg',
          'data-bca-btn-size' => 'lg',
          'confirm' => __d('baser_core', 'サイト探査を開始します。よろしいですか？')
        ]) ?>
        <?php $this->BcAdminForm->unlockField('save_only') ?>
        <?php echo $this->BcAdminForm->submit(__d('baser_core', '設定のみ保存'), [
          'div' => false,
          'class' => 'bca-btn bca-actions__item',
          'data-bca-btn-size' => 'lg',
          'name' => 'save_only'
        ]) ?>
      </div>
    </div>
    <?php echo $this->BcAdminForm->end() ?>
  <?php else: ?>
    <p><?php echo __d('baser_core', 'この環境ではコマンド実行が許可されていないため、画面からの実行はできません。CLI をご利用ください:') ?></p>
    <pre><code>cd <?php echo h($rootPath) ?> && bin/cake bc_site_explorer crawl --url <?php echo h($defaults['start_url']) ?></code></pre>
  <?php endif ?>
</div>

<!-- 走査履歴 -->
<div class="section bca-section">
  <h2><?php echo __d('baser_core', '走査履歴') ?></h2>
  <?php if (!$crawls->count()): ?>
    <p><?php echo __d('baser_core', '走査履歴はまだありません。') ?></p>
  <?php else: ?>
    <?php echo $this->BcAdminForm->create(null, ['url' => ['action' => 'batch_delete'], 'id' => 'BatchDeleteForm']) ?>
    <?php $this->BcAdminForm->unlockField('batch_targets') ?>
    <?php $this->BcAdminForm->unlockField('checkall') ?>
    <table class="list-table bca-table-listup">
      <thead class="bca-table-listup__thead">
      <tr>
        <th class="list-tool bca-table-listup__thead-th bca-table-listup__thead-th--select" title="<?php echo __d('baser_core', '一括選択') ?>">
          <?php echo $this->BcAdminForm->control('checkall', ['type' => 'checkbox', 'label' => ' ', 'hiddenField' => false, 'title' => __d('baser_core', '一括選択')]) ?>
        </th>
        <th class="bca-table-listup__thead-th">ID</th>
        <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', '開始URL') ?></th>
        <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', '状態') ?></th>
        <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', '取得') ?></th>
        <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', 'エラー') ?></th>
        <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', '孤立') ?></th>
        <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', '実行日時') ?></th>
        <th class="bca-table-listup__thead-th"><?php echo __d('baser_core', '操作') ?></th>
      </tr>
      </thead>
      <tbody class="bca-table-listup__tbody">
      <?php foreach($crawls as $crawl): ?>
        <tr>
          <td class="bca-table-listup__tbody-td bca-table-listup__tbody-td--select">
            <?php echo $this->BcAdminForm->control('batch_targets[]', [
              'type' => 'checkbox',
              'value' => $crawl->id,
              'id' => 'BatchTarget' . $crawl->id,
              'label' => ' ',
              'hiddenField' => false
            ]) ?>
          </td>
          <td class="bca-table-listup__tbody-td"><?php echo (int)$crawl->id ?></td>
          <td class="bca-table-listup__tbody-td"><?php echo h($crawl->start_url) ?></td>
          <td class="bca-table-listup__tbody-td">
            <?php
            echo h(match ($crawl->status) {
              'waiting' => __d('baser_core', '待機中'),
              'running' => __d('baser_core', '実行中'),
              'completed' => __d('baser_core', '完了'),
              'failed' => __d('baser_core', '失敗'),
              'canceled' => __d('baser_core', '中止'),
              default => $crawl->status,
            });
            if ($crawl->status === 'failed' && $crawl->error_message) {
              echo '<br><small>' . h($crawl->error_message) . '</small>';
            }
            ?>
          </td>
          <td class="bca-table-listup__tbody-td"><?php echo (int)$crawl->fetched_count ?></td>
          <td class="bca-table-listup__tbody-td"><?php echo (int)$crawl->error_count ?></td>
          <td class="bca-table-listup__tbody-td"><?php echo (int)$crawl->orphan_count ?></td>
          <td class="bca-table-listup__tbody-td"><?php echo $crawl->started? $this->BcTime->format($crawl->started, 'yyyy-MM-dd HH:mm') : '' ?></td>
          <td class="bca-table-listup__tbody-td">
            <?php echo $this->BcBaser->getLink(__d('baser_core', '結果を見る'), [
              'action' => 'results', $crawl->id
            ], ['class' => 'bca-btn', 'data-bca-btn-size' => 'sm']) ?>
            <?php // フォームのネストを避けるため postLink は block でフォーム外に描画する ?>
            <?php echo $this->BcAdminForm->postLink(__d('baser_core', '削除'), [
              'action' => 'delete', $crawl->id
            ], [
              'block' => true,
              'class' => 'bca-btn',
              'data-bca-btn-type' => 'delete',
              'data-bca-btn-size' => 'sm',
              'confirm' => __d('baser_core', '走査結果を削除します。よろしいですか？')
            ]) ?>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <div class="bca-actions">
      <?php echo $this->BcAdminForm->submit(__d('baser_core', 'チェックした履歴を削除'), [
        'div' => false,
        'class' => 'bca-btn',
        'data-bca-btn-type' => 'delete',
        'data-bca-btn-size' => 'sm',
        'id' => 'BtnBatchDelete'
      ]) ?>
    </div>
    <?php echo $this->BcAdminForm->end() ?>
    <?php echo $this->fetch('postLink') ?>
  <?php endif ?>
</div>

<script>
$(function() {
  // 走査履歴の一括選択・一括削除
  $('#checkall').on('change', function() {
    $('#BatchDeleteForm input[name="batch_targets[]"]').prop('checked', this.checked);
  });
  $('#BatchDeleteForm').on('submit', function(e) {
    var checked = $(this).find('input[name="batch_targets[]"]:checked').length;
    if (!checked) {
      alert('<?php echo __d('baser_core', '削除する走査履歴を選択してください。') ?>');
      e.preventDefault();
      return false;
    }
    if (!confirm(checked + '<?php echo __d('baser_core', ' 件の走査履歴を削除します。よろしいですか？') ?>')) {
      e.preventDefault();
      return false;
    }
  });

  var statusUrl = $.bcUtil.adminBaseUrl + 'bc-site-explorer/bc_site_explorer/status';
  var running = <?php echo ($status['state'] === 'running')? 'true' : 'false' ?>;
  if (!running) return;
  var timer = setInterval(function() {
    $.getJSON(statusUrl).done(function(status) {
      var message = status.message || '';
      if (typeof status.fetched_count !== 'undefined') {
        message += ' [' + status.fetched_count + '<?php echo __d('baser_core', '件取得') ?>'
          + ' / <?php echo __d('baser_core', 'キュー') ?> ' + status.queued_count + ']';
      }
      $('#BcSiteExplorerStatusMessage').text(message);
      if (status.state !== 'running') {
        clearInterval(timer);
        location.reload();
      }
    });
  }, 3000);
});
</script>
