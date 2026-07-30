<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service\Exporter;

use BcSiteExplorer\Utility\BcSiteExplorerUtil;

/**
 * CsvExporter
 *
 * CSV へ書き出す。UTF-8（BOM付き）と SJIS（SJIS-win）に対応。
 */
class CsvExporter implements ExporterInterface
{

    /**
     * @inheritDoc
     */
    public function getExtension(): string
    {
        return 'csv';
    }

    /**
     * @inheritDoc
     */
    public function export(iterable $rows, array $columns, array $options = []): string
    {
        $encoding = strtoupper((string)($options['encoding'] ?? 'UTF-8'));
        $path = BcSiteExplorerUtil::getWorkDir() . 'export_' . getmypid() . '_' . str_replace('.', '', uniqid('', true)) . '.csv';
        $fp = fopen($path, 'w');
        if (!$fp) {
            throw new \RuntimeException('エクスポートファイルを作成できません。' . $path);
        }
        try {
            if ($encoding === 'UTF-8') {
                fwrite($fp, "\xEF\xBB\xBF");
            }
            $this->putRow($fp, array_values($columns), $encoding);
            $no = 0;
            foreach($rows as $row) {
                $no++;
                $fields = [];
                foreach(array_keys($columns) as $key) {
                    $fields[] = ExportColumns::extract($row, $key, $no);
                }
                $this->putRow($fp, $fields, $encoding);
            }
        } finally {
            fclose($fp);
        }
        return $path;
    }

    /**
     * 1行書き出す（必要なら文字コード変換する）
     *
     * @param resource $fp
     * @param array $fields
     * @param string $encoding
     * @return void
     */
    protected function putRow($fp, array $fields, string $encoding): void
    {
        if ($encoding === 'UTF-8') {
            fputcsv($fp, $fields, ',', '"', '');
            return;
        }
        $mem = fopen('php://temp', 'r+');
        fputcsv($mem, $fields, ',', '"', '');
        rewind($mem);
        $line = (string)stream_get_contents($mem);
        fclose($mem);
        fwrite($fp, mb_convert_encoding($line, 'SJIS-win', 'UTF-8'));
    }

}
