<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Test\TestCase\Service\Crawler;

use BcSiteExplorer\Service\Crawler\LinkExtractor;
use PHPUnit\Framework\TestCase;

/**
 * LinkExtractorTest
 */
class LinkExtractorTest extends TestCase
{

    /**
     * タイトル・meta robots・リンクの抽出
     *
     * @return void
     */
    public function testExtract(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<title>テストページ｜My Site</title>
<meta name="robots" content="NOINDEX, NOFOLLOW">
</head>
<body>
<a href="/about">会社案内</a>
<a href="/about">重複リンク</a>
<a href="https://example.com/news/">ニュース</a>
<a href="#section">ページ内</a>
<map><area href="/map-link" alt=""></map>
<a>href なし</a>
</body>
</html>
HTML;
        $extractor = new LinkExtractor();
        $result = $extractor->extract($html, 'https://example.com/');

        $this->assertSame('テストページ｜My Site', $result['title']);
        $this->assertSame('noindex, nofollow', $result['meta_robots']);
        $this->assertTrue($extractor->hasNofollow($result['meta_robots']));
        $this->assertSame('https://example.com/', $result['base']);
        $this->assertSame(['/about', 'https://example.com/news/', '#section', '/map-link'], $result['links']);
    }

    /**
     * base href の解決
     *
     * @return void
     */
    public function testExtractBaseHref(): void
    {
        $html = '<html><head><base href="https://example.com/sub/"></head><body><a href="page">x</a></body></html>';
        $extractor = new LinkExtractor();
        $result = $extractor->extract($html, 'https://example.com/');
        $this->assertSame('https://example.com/sub/', $result['base']);
    }

    /**
     * 空 HTML・robots なし
     *
     * @return void
     */
    public function testExtractEmpty(): void
    {
        $extractor = new LinkExtractor();
        $result = $extractor->extract('', 'https://example.com/');
        $this->assertNull($result['title']);
        $this->assertNull($result['meta_robots']);
        $this->assertSame([], $result['links']);
        $this->assertFalse($extractor->hasNofollow($result['meta_robots']));
        $this->assertFalse($extractor->hasNofollow('noindex'));
    }

}
