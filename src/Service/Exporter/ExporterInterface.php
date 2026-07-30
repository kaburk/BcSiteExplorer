<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service\Exporter;

/**
 * ExporterInterface
 *
 * 結果一覧の書き出し。ファイルを生成しそのパスを返す。
 * （Google Sheets 等、ファイル以外へ書き出す実装は URL を返してもよい。
 * Response 生成は Controller 側の責務とする）
 */
interface ExporterInterface
{

    /**
     * 拡張子（ダウンロードファイル名の組み立てに使う）
     *
     * @return string
     */
    public function getExtension(): string;

    /**
     * エクスポートを実行する
     *
     * @param iterable $rows BcSiteExplorerResult の反復
     * @param array $columns 列キー => ラベル（ExportColumns::resolve() の結果）
     * @param array $options encoding 等
     * @return string 生成したファイルのパス
     */
    public function export(iterable $rows, array $columns, array $options = []): string;

}
