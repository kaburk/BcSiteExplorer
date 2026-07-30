# BcSiteExplorer

baserCMS 5 用のサイト構造解析・サイトマップ作成プラグインです。
サイトをクロールして URL・ページタイトル・リダイレクトの一覧を作成し、CSV / Excel に書き出せます。

## 主な機能

- **HTTP クロール**: 開始 URL からリンク（`a[href]` / `area[href]`）を辿って BFS でサイトを探査
  - URL / ページタイトル / HTTP ステータス / Content-Type / meta robots を記録
  - リダイレクトはチェーン（`A → B → C`）として記録
  - 外部サイトを対象外にするオプション（記録のみ / 完全除外を選択可）
  - 最大ページ数・最大深度・リクエスト間隔で負荷を制御
- **コンテンツ管理 DB との突き合わせ（ハイブリッド）**
  - 固定ページ・フォルダ・ブログ記事・カスタムエントリー等の公開 URL を DB から列挙
  - クロール結果と突合し、コンテンツ種別・DB タイトル・公開状態を付加
  - クロールで到達できない**孤立ページ**（どこからもリンクされていないページ）を検出
  - クロールでのみ見つかる URL（テーマ内のハードコードリンク・リンク切れ）も浮き上がる
  - オプションでブログの派生 URL（RSS・カテゴリ・タグ・日付・著者・ページ送り）も DB 側から列挙可能
  - BcSeo 有効時は meta description をエクスポート列に付加
- **エクスポート**: CSV（UTF-8 BOM 付き / Shift-JIS）、Excel (xlsx)
  - 列の並び順・出力する列は設定でカスタマイズ可能
- **実行方式**: 管理画面からバックグラウンド実行（進捗をリアルタイム表示）、または CLI

## インストール

1. `plugins/BcSiteExplorer` に配置
2. 管理画面 > プラグイン管理 から有効化（マイグレーションが実行されます）
3. Excel 出力を使う場合はプラグインディレクトリで依存をインストール

```
cd plugins/BcSiteExplorer
composer require phpoffice/phpspreadsheet
```

## 使い方

### 管理画面

管理画面 > サイトエクスプローラー から開始 URL とオプションを指定して「探査開始」。
完了後に「結果を見る」から一覧の閲覧・フィルタ・ダウンロードができます。

実行オプションは「探査開始」時に自動保存され、次回フォームの初期値になります。
実行せずに設定だけ変えたい場合は「設定のみ保存」ボタンを使用してください。
Basic 認証のパスワードのみ、平文で DB に残さないため保存されません（実行のたびに入力）。

走査履歴は行のチェックボックス（ヘッダーで一括選択）＋「チェックした履歴を削除」で
まとめて削除できます。

### CLI

```
# 新規に探査を実行
bin/cake bc_site_explorer crawl --url https://example.com/

# オプション指定
bin/cake bc_site_explorer crawl --url https://example.com/ --mode hybrid --max-pages 2000 --interval-ms 300

# Docker 等でサーバ内から公開 URL に接続できない場合
bin/cake bc_site_explorer crawl --url https://example.com/ --internal-base-url http://localhost
```

## 設定のカスタマイズ（setting_customize.php）

`config/setting.php` はデフォルト設定として Git 管理し、環境ごとの上書きは
`config/setting_customize.php` で行います（`.gitignore` 対象のためコミットされません）。

1. `config/setting_customize.php.default` を `config/setting_customize.php` にコピー
2. 上書きしたい項目のコメントを外して編集
3. `bin/cake cache clear_all` を実行して反映

```php
<?php
$customize_config = [
    'BcSiteExplorer' => [
        // エクスポート列（配列の順序 = 列の出力順。不要な列はキーを削除）
        'exportColumns' => [
            'no', 'url', 'title', 'http_status', 'redirect_chain',
        ],
        // クロールの既定オプション（フォーム初期値）
        'crawlDefaults' => [
            'max_pages' => 2000,
            'verify_ssl' => false,
        ],
    ],
];
```

利用できる列キー: `no` `url` `title` `content_title` `content_description` `content_kind`
`content_status` `final_url` `redirect_chain` `http_status` `depth` `parent_url` `source`
`is_orphan` `meta_robots` `error_message`

※ `exportColumns` のようなリスト型の設定は追記マージではなく丸ごと置換されるため、
必要な列をすべて列挙してください。

## 今後の予定（未実装）

- Google スプレッドシートへの直接書き出し（Sheets API 連携。`ExporterInterface` の実装として追加予定）

## 制限事項

- robots.txt には対応していません（自サイトの解析を想定しているため）。他社サイトへの実行は行わないでください
- `meta robots` の `nofollow` はリンク抽出のスキップとして尊重します。`noindex` は記録のみ行います
- フロントのページキャッシュが有効な場合、キャッシュされた内容を収集します

## ライセンス

MIT License
