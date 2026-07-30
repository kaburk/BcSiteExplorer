<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service\Crawler;

/**
 * UrlNormalizer
 *
 * URL の正規化を一手に引き受ける。クロール側と DB 側の両方が
 * 必ずこのクラスを通ることで、同一ページが別 URL 扱いになる
 * （偽孤立）のを防ぐ。
 *
 * 2つの役割を持つ:
 *
 * - normalize(): アクセスに使うクリーンな絶対 URL を作る
 *   （相対解決・フラグメント除去・クエリ整理。パス表記は変えない —
 *   baserCMS はフォルダ `/news/` とページ `/about` で末尾スラッシュの
 *   有無が意味を持つため、アクセス URL の書き換えは 404 を生む）
 *
 * - hash(): 同一性判定用のハッシュを作る
 *   （スキーム除外・/index 統一・末尾スラッシュ除去。`http://a/news/` と
 *   `https://a/news` と `https://a/news/index` を同一ページとして扱う）
 */
class UrlNormalizer
{

    /**
     * クエリの扱い whitelist | keep | drop
     * @var string
     */
    protected string $queryMode;

    /**
     * whitelist モードで保持するクエリキー
     * @var array
     */
    protected array $queryWhitelist;

    /**
     * constructor.
     *
     * @param string $queryMode
     * @param array|null $queryWhitelist
     */
    public function __construct(string $queryMode = 'whitelist', ?array $queryWhitelist = null)
    {
        $this->queryMode = in_array($queryMode, ['whitelist', 'keep', 'drop'], true)? $queryMode : 'whitelist';
        $this->queryWhitelist = $queryWhitelist ?? ['page', 'year', 'month', 'day'];
    }

    /**
     * アクセスに使うクリーンな絶対 URL を作る
     *
     * @param string $url 対象 URL（$base 指定時は相対 URL 可）
     * @param string|null $base 相対 URL の解決に使うベース URL
     * @return string|null クロール対象にならない URL（別スキーム・フラグメントのみ等）は null
     */
    public function normalize(string $url, ?string $base = null): ?string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#')) return null;
        if (preg_match('/^(mailto|tel|sms|javascript|data|ftp|file):/i', $url)) return null;

        if (str_starts_with($url, '//')) {
            $scheme = ($base)? (parse_url($base, PHP_URL_SCHEME) ?: 'http') : 'http';
            $url = $scheme . ':' . $url;
        } elseif (!preg_match('~^[a-z][a-z0-9+.\-]*://~i', $url)) {
            if ($base === null) return null;
            $url = $this->resolveRelative($url, $base);
            if ($url === null) return null;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) return null;

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) return null;
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        $path = $this->cleanPath($parts['path'] ?? '/');
        $query = $this->normalizeQuery($parts['query'] ?? '');

        return $scheme . '://' . $host . ($port? ':' . $port : '') . $path . (($query !== '')? '?' . $query : '');
    }

    /**
     * 同一性判定用のハッシュを作る
     *
     * スキームを除外し、/index・末尾スラッシュを統一したキーで計算する。
     * 引数には normalize() 済みの URL を渡すこと。
     *
     * @param string $url
     * @return string sha1 ハッシュ
     */
    public function hash(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $port = isset($parts['port'])? ':' . $parts['port'] : '';
        $path = $this->cleanPath($parts['path'] ?? '/');
        // /index・/index.html 等を親パスへ統一
        $path = preg_replace('~/index(\.html?|\.php)?/?$~', '/', $path);
        // 末尾スラッシュ除去（ルートを除く）
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        $query = $this->normalizeQuery($parts['query'] ?? '');
        return sha1($host . $port . $path . (($query !== '')? '?' . $query : ''));
    }

    /**
     * 相対 URL をベース URL で絶対化する
     *
     * @param string $rel
     * @param string $base
     * @return string|null
     */
    protected function resolveRelative(string $rel, string $base): ?string
    {
        $baseParts = parse_url($base);
        if ($baseParts === false || empty($baseParts['scheme']) || empty($baseParts['host'])) return null;
        $origin = $baseParts['scheme'] . '://' . $baseParts['host']
            . (isset($baseParts['port'])? ':' . $baseParts['port'] : '');

        if (str_starts_with($rel, '/')) {
            return $origin . $rel;
        }
        if (str_starts_with($rel, '?')) {
            return $origin . ($baseParts['path'] ?? '/') . $rel;
        }
        $basePath = $baseParts['path'] ?? '/';
        // ベースパスの最終セグメントを除いたディレクトリに連結する
        $dir = (str_ends_with($basePath, '/'))? $basePath : (dirname($basePath) === '/' ? '/' : dirname($basePath) . '/');
        // Windows 形式の dirname 対策
        $dir = str_replace('\\', '/', $dir);
        return $origin . $dir . $rel;
    }

    /**
     * パスのクリーニング（アクセス互換を保つ範囲のみ）
     *
     * ドットセグメント解決とパーセントエンコードの大文字化のみ行い、
     * 末尾スラッシュ・/index はそのまま維持する。
     *
     * @param string $path
     * @return string
     */
    protected function cleanPath(string $path): string
    {
        if ($path === '') $path = '/';
        if (!str_starts_with($path, '/')) $path = '/' . $path;
        $hadTrailingSlash = str_ends_with($path, '/');

        // ドットセグメント解決
        $segments = explode('/', $path);
        $resolved = [];
        foreach($segments as $segment) {
            if ($segment === '.' || $segment === '') continue;
            if ($segment === '..') {
                array_pop($resolved);
                continue;
            }
            $resolved[] = $segment;
        }
        $path = '/' . implode('/', $resolved);
        if ($hadTrailingSlash && $path !== '/') $path .= '/';

        // パーセントエンコードの大文字化
        return preg_replace_callback('/%[0-9a-f]{2}/', fn($m) => strtoupper($m[0]), $path);
    }

    /**
     * クエリ文字列を正規化する
     *
     * @param string $query
     * @return string
     */
    protected function normalizeQuery(string $query): string
    {
        if ($query === '' || $this->queryMode === 'drop') return '';
        parse_str($query, $params);
        if (!$params) return '';
        if ($this->queryMode === 'whitelist') {
            $params = array_intersect_key($params, array_flip($this->queryWhitelist));
            if (!$params) return '';
        }
        ksort($params);
        return http_build_query($params);
    }

}
