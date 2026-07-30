<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service\Crawler;

use Cake\Http\Client;

/**
 * HttpFetcher
 *
 * Cake\Http\Client のラッパー。リダイレクト（3xx + Location）を
 * 自前で追跡してチェーンを記録する。
 *
 * オプション:
 * - timeout: 接続タイムアウト秒（既定 10）
 * - basic_auth_user / basic_auth_password: Basic 認証
 * - internal_base_url: Docker 等で公開 URL に到達できない場合の内部アクセス URL
 *   （例 http://bc-php）。接続先だけ差し替え、Host ヘッダには公開ホスト名を送る
 */
class HttpFetcher implements HttpFetcherInterface
{

    /**
     * リダイレクト追跡の最大ホップ数
     */
    public const MAX_REDIRECTS = 10;

    /**
     * リトライ回数（タイムアウト・5xx のみ）
     */
    public const RETRY = 1;

    /**
     * @var array
     */
    protected array $options;

    /**
     * constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * @inheritDoc
     */
    public function fetch(string $url): array
    {
        $result = [
            'status' => null,
            'body' => null,
            'content_type' => null,
            'redirect_chain' => [],
            'final_url' => $url,
            'error' => null,
        ];

        $current = $url;
        $visited = [sha1($url) => true];
        for($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            try {
                $response = $this->request($current);
            } catch (\Throwable $e) {
                $result['error'] = $e->getMessage();
                return $result;
            }
            $status = $response->getStatusCode();
            $result['status'] = $status;
            $result['final_url'] = $current;
            $result['content_type'] = $response->getHeaderLine('Content-Type') ?: null;

            if ($status >= 300 && $status < 400) {
                $location = $response->getHeaderLine('Location');
                if (!$location) {
                    return $result;
                }
                $next = $this->resolveLocation($location, $current);
                $result['redirect_chain'][] = ['from' => $current, 'to' => $next, 'status' => $status];
                if (isset($visited[sha1($next)])) {
                    $result['error'] = __d('baser_core', 'リダイレクトがループしています。');
                    return $result;
                }
                if ($hop === self::MAX_REDIRECTS) {
                    $result['error'] = __d('baser_core', 'リダイレクトが多すぎます。');
                    return $result;
                }
                $visited[sha1($next)] = true;
                $current = $next;
                continue;
            }

            // HTML のみ本文を保持する（PDF 等はステータスと Content-Type のみ）
            if (str_contains((string)$result['content_type'], 'text/html')) {
                $result['body'] = (string)$response->getBody();
            }
            return $result;
        }
        return $result;
    }

    /**
     * 1リクエストを実行する（リトライ込み・リダイレクト追跡なし）
     *
     * @param string $url
     * @return \Cake\Http\Client\Response
     */
    protected function request(string $url)
    {
        $config = [
            'redirect' => 0,
            'timeout' => (int)($this->options['timeout'] ?? 10),
        ];
        if (array_key_exists('verify_ssl', $this->options) && !$this->options['verify_ssl']) {
            // 自己署名証明書のローカル環境向け
            $config['ssl_verify_peer'] = false;
            $config['ssl_verify_peer_name'] = false;
            $config['ssl_verify_host'] = false;
        }
        if (!empty($this->options['basic_auth_user'])) {
            $config['auth'] = [
                'username' => (string)$this->options['basic_auth_user'],
                'password' => (string)($this->options['basic_auth_password'] ?? ''),
            ];
        }

        $requestUrl = $url;
        $headers = [];
        if (!empty($this->options['internal_base_url'])) {
            $parts = parse_url($url);
            $internal = rtrim((string)$this->options['internal_base_url'], '/');
            $publicHost = ($parts['host'] ?? '') . (isset($parts['port'])? ':' . $parts['port'] : '');
            $requestUrl = $internal . ($parts['path'] ?? '/') . (isset($parts['query'])? '?' . $parts['query'] : '');
            $headers['Host'] = $publicHost;
            // 内部アクセスでは TLS 証明書のホスト名不一致が起き得るため検証を無効化する
            $config['ssl_verify_peer'] = false;
            $config['ssl_verify_peer_name'] = false;
            $config['ssl_verify_host'] = false;
        }

        $lastException = null;
        for($attempt = 0; $attempt <= self::RETRY; $attempt++) {
            try {
                $client = new Client($config);
                $response = $client->get($requestUrl, [], ['headers' => $headers]);
                if ($response->getStatusCode() >= 500 && $attempt < self::RETRY) {
                    continue;
                }
                return $response;
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($attempt >= self::RETRY) break;
            }
        }
        throw $lastException ?? new \RuntimeException('HTTP request failed.');
    }

    /**
     * Location ヘッダを絶対 URL に解決する
     *
     * @param string $location
     * @param string $base
     * @return string
     */
    protected function resolveLocation(string $location, string $base): string
    {
        if (preg_match('~^https?://~i', $location)) return $location;
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '')
            . (isset($parts['port'])? ':' . $parts['port'] : '');
        if (str_starts_with($location, '//')) {
            return ($parts['scheme'] ?? 'http') . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $path = $parts['path'] ?? '/';
        $dir = str_ends_with($path, '/')? $path : (dirname($path) === '/'? '/' : dirname($path) . '/');
        return $origin . str_replace('\\', '/', $dir) . $location;
    }

}
