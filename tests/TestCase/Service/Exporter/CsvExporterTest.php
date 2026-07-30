<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Test\TestCase\Service\Exporter;

use BcSiteExplorer\Model\Entity\BcSiteExplorerResult;
use BcSiteExplorer\Service\Exporter\CsvExporter;
use BcSiteExplorer\Service\Exporter\ExportColumns;
use Cake\Core\Configure;
use PHPUnit\Framework\TestCase;

/**
 * CsvExporterTest
 */
class CsvExporterTest extends TestCase
{

    /**
     * tearDown
     *
     * @return void
     */
    public function tearDown(): void
    {
        Configure::delete('BcSiteExplorer.exportColumns');
        parent::tearDown();
    }

    /**
     * テスト用の結果行
     *
     * @return BcSiteExplorerResult
     */
    protected function createRow(): BcSiteExplorerResult
    {
        return new BcSiteExplorerResult([
            'url' => 'https://example.com/news/',
            'title' => 'ニュース｜My Site',
            'content_title' => 'NEWS',
            'content_kind' => 'BlogContent',
            'content_status' => true,
            'http_status' => 200,
            'redirect_chain' => json_encode([
                ['from' => 'http://example.com/news/', 'to' => 'https://example.com/news/', 'status' => 301],
            ]),
            'depth' => 1,
            'source' => 'both',
            'is_orphan' => false,
        ]);
    }

    /**
     * UTF-8 (BOM付き) で書き出す
     *
     * @return void
     */
    public function testExportUtf8(): void
    {
        $path = (new CsvExporter())->export([$this->createRow()], ExportColumns::resolve(), ['encoding' => 'UTF-8']);
        try {
            $content = (string)file_get_contents($path);
            $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
            $lines = explode("\n", trim(substr($content, 3)));
            $this->assertCount(2, $lines);
            // 既定列順: no, url, title, content_title, content_kind, content_status, ...
            $this->assertStringStartsWith('No,URL,', $lines[0]);
            $this->assertStringContainsString('https://example.com/news/', $lines[1]);
            $this->assertStringContainsString('ニュース｜My Site', $lines[1]);
            // リダイレクトチェーンは A → B 表記
            $this->assertStringContainsString('http://example.com/news/ → https://example.com/news/', $lines[1]);
            $this->assertStringContainsString('公開', $lines[1]);
        } finally {
            unlink($path);
        }
    }

    /**
     * 設定による列の並び替え・除外
     *
     * @return void
     */
    public function testExportCustomColumns(): void
    {
        Configure::write('BcSiteExplorer.exportColumns', ['url', 'http_status', 'no', 'unknown_key']);
        $columns = ExportColumns::resolve();
        // 不明キーはスキップされ、指定順が維持される
        $this->assertSame(['url', 'http_status', 'no'], array_keys($columns));

        $path = (new CsvExporter())->export([$this->createRow()], $columns, ['encoding' => 'UTF-8']);
        try {
            $content = substr((string)file_get_contents($path), 3);
            $lines = explode("\n", trim($content));
            $this->assertSame('URL,HTTPステータス,No', $lines[0]);
            $this->assertSame('https://example.com/news/,200,1', $lines[1]);
        } finally {
            unlink($path);
        }
    }

    /**
     * SJIS で書き出す
     *
     * @return void
     */
    public function testExportSjis(): void
    {
        $path = (new CsvExporter())->export([$this->createRow()], ExportColumns::resolve(), ['encoding' => 'SJIS']);
        try {
            $content = (string)file_get_contents($path);
            $this->assertStringNotContainsString("\xEF\xBB\xBF", $content);
            $this->assertTrue(mb_check_encoding($content, 'SJIS-win'));
            $utf8 = mb_convert_encoding($content, 'UTF-8', 'SJIS-win');
            $this->assertStringContainsString('ニュース｜My Site', $utf8);
        } finally {
            unlink($path);
        }
    }

}
