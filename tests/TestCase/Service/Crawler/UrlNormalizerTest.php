<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Test\TestCase\Service\Crawler;

use BcSiteExplorer\Service\Crawler\UrlNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * UrlNormalizerTest
 */
class UrlNormalizerTest extends TestCase
{

    /**
     * 対象外 URL は null
     *
     * @return void
     */
    public function testNormalizeSkips(): void
    {
        $normalizer = new UrlNormalizer();
        $base = 'https://example.com/page';
        $this->assertNull($normalizer->normalize('', $base));
        $this->assertNull($normalizer->normalize('#section', $base));
        $this->assertNull($normalizer->normalize('mailto:info@example.com', $base));
        $this->assertNull($normalizer->normalize('tel:0312345678', $base));
        $this->assertNull($normalizer->normalize('javascript:void(0)', $base));
        $this->assertNull($normalizer->normalize('ftp://example.com/file', $base));
        // ベース無しの相対 URL は解決できない
        $this->assertNull($normalizer->normalize('sub/page'));
    }

    /**
     * 相対 URL の解決
     *
     * @return void
     */
    public function testNormalizeResolvesRelative(): void
    {
        $normalizer = new UrlNormalizer();
        $base = 'https://example.com/dir/page';
        $this->assertSame('https://example.com/sub', $normalizer->normalize('/sub', $base));
        $this->assertSame('https://example.com/dir/sub', $normalizer->normalize('sub', $base));
        $this->assertSame('https://example.com/sub', $normalizer->normalize('../sub', $base));
        // ディレクトリベース
        $this->assertSame('https://example.com/dir/sub', $normalizer->normalize('sub', 'https://example.com/dir/'));
        // スキーム相対
        $this->assertSame('https://other.com/x', $normalizer->normalize('//other.com/x', $base));
        // クエリのみ（page はホワイトリスト対象）
        $this->assertSame('https://example.com/dir/page?page=2', $normalizer->normalize('?page=2', $base));
    }

    /**
     * ホスト・ポート・パスのクリーニング
     *
     * @return void
     */
    public function testNormalizeCleans(): void
    {
        $normalizer = new UrlNormalizer();
        // ホスト小文字化・デフォルトポート除去
        $this->assertSame('https://example.com/x', $normalizer->normalize('HTTPS://EXAMPLE.COM:443/x'));
        $this->assertSame('http://example.com/x', $normalizer->normalize('http://example.com:80/x'));
        // 非デフォルトポートは維持
        $this->assertSame('http://example.com:8080/x', $normalizer->normalize('http://example.com:8080/x'));
        // ドットセグメント解決
        $this->assertSame('https://example.com/a/c', $normalizer->normalize('https://example.com/a/b/../c'));
        // パーセントエンコード大文字化
        $this->assertSame('https://example.com/%E3%81%82', $normalizer->normalize('https://example.com/%e3%81%82'));
        // フラグメント除去
        $this->assertSame('https://example.com/x', $normalizer->normalize('https://example.com/x#top'));
    }

    /**
     * アクセス URL のパス表記（末尾スラッシュ・/index）は維持される
     *
     * baserCMS はフォルダ /news/ とページ /about で末尾スラッシュが意味を持つため
     *
     * @return void
     */
    public function testNormalizeKeepsPathShape(): void
    {
        $normalizer = new UrlNormalizer();
        $this->assertSame('https://example.com/news/', $normalizer->normalize('https://example.com/news/'));
        $this->assertSame('https://example.com/news', $normalizer->normalize('https://example.com/news'));
        $this->assertSame('https://example.com/news/index', $normalizer->normalize('https://example.com/news/index'));
    }

    /**
     * クエリモード
     *
     * @return void
     */
    public function testNormalizeQueryModes(): void
    {
        $whitelist = new UrlNormalizer('whitelist');
        // ホワイトリスト外は除去、対象キーは保持しキー順ソート
        $this->assertSame('https://example.com/x?page=2&year=2026',
            $whitelist->normalize('https://example.com/x?year=2026&utm_source=a&page=2'));
        $this->assertSame('https://example.com/x',
            $whitelist->normalize('https://example.com/x?utm_source=a'));

        $drop = new UrlNormalizer('drop');
        $this->assertSame('https://example.com/x', $drop->normalize('https://example.com/x?page=2'));

        $keep = new UrlNormalizer('keep');
        $this->assertSame('https://example.com/x?a=1&page=2',
            $keep->normalize('https://example.com/x?page=2&a=1'));
    }

    /**
     * 同一性ハッシュ: 同一視すべき URL
     *
     * @return void
     */
    public function testHashEquivalence(): void
    {
        $normalizer = new UrlNormalizer();
        // スキーム違いは同一
        $this->assertSame(
            $normalizer->hash('http://example.com/news/'),
            $normalizer->hash('https://example.com/news/')
        );
        // 末尾スラッシュの有無は同一
        $this->assertSame(
            $normalizer->hash('https://example.com/news/'),
            $normalizer->hash('https://example.com/news')
        );
        // /index は親と同一
        $this->assertSame(
            $normalizer->hash('https://example.com/news/'),
            $normalizer->hash('https://example.com/news/index')
        );
        $this->assertSame(
            $normalizer->hash('https://example.com/'),
            $normalizer->hash('https://example.com/index')
        );
        // クエリのキー順は同一
        $this->assertSame(
            $normalizer->hash('https://example.com/x?page=2&year=2026'),
            $normalizer->hash('https://example.com/x?year=2026&page=2')
        );
    }

    /**
     * 同一性ハッシュ: 区別すべき URL
     *
     * @return void
     */
    public function testHashDifference(): void
    {
        $normalizer = new UrlNormalizer();
        // パス違い
        $this->assertNotSame(
            $normalizer->hash('https://example.com/news'),
            $normalizer->hash('https://example.com/blog')
        );
        // ホスト違い
        $this->assertNotSame(
            $normalizer->hash('https://example.com/news'),
            $normalizer->hash('https://sub.example.com/news')
        );
        // ポート違い
        $this->assertNotSame(
            $normalizer->hash('http://example.com/news'),
            $normalizer->hash('http://example.com:8080/news')
        );
        // ページ送りクエリ違い
        $this->assertNotSame(
            $normalizer->hash('https://example.com/news?page=1'),
            $normalizer->hash('https://example.com/news?page=2')
        );
    }

}
