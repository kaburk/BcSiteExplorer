<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service\Exporter;

use BaserCore\Error\BcException;
use BcSiteExplorer\Utility\BcSiteExplorerUtil;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * XlsxExporter
 *
 * Excel (xlsx) へ書き出す。phpoffice/phpspreadsheet が必要。
 */
class XlsxExporter implements ExporterInterface
{

    /**
     * xlsx で扱う最大行数（超過時は CSV を案内する）
     */
    public const MAX_ROWS = 50000;

    /**
     * @inheritDoc
     */
    public function getExtension(): string
    {
        return 'xlsx';
    }

    /**
     * @inheritDoc
     */
    public function export(iterable $rows, array $columns, array $options = []): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('sitemap');

        $keys = array_keys($columns);
        $sheet->fromArray(array_values($columns), null, 'A1');
        $sheet->getStyle('A1:' . $sheet->getCell([count($keys), 1])->getColumn() . '1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $rowIndex = 2;
        $no = 0;
        foreach($rows as $row) {
            $no++;
            if ($no > self::MAX_ROWS) {
                throw new BcException(__d('baser_core', '結果が {0} 行を超えるため Excel 出力できません。CSV をご利用ください。', number_format(self::MAX_ROWS)));
            }
            $fields = [];
            foreach($keys as $key) {
                $fields[] = ExportColumns::extract($row, $key, $no);
            }
            $sheet->fromArray($fields, null, 'A' . $rowIndex, true);
            $rowIndex++;
        }

        foreach(range(1, count($keys)) as $columnIndex) {
            $sheet->getColumnDimension($sheet->getCell([$columnIndex, 1])->getColumn())->setWidth(24);
        }

        $path = BcSiteExplorerUtil::getWorkDir() . 'export_' . getmypid() . '_' . str_replace('.', '', uniqid('', true)) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        return $path;
    }

}
