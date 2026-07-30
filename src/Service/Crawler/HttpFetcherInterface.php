<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service\Crawler;

/**
 * HttpFetcherInterface
 *
 * 1 URL の取得（リダイレクトチェーン追跡込み）。
 * テストではスタブに差し替える。
 */
interface HttpFetcherInterface
{

    /**
     * URL を取得する
     *
     * リダイレクト（3xx + Location）は自前で追跡し、チェーンを記録して返す。
     *
     * @param string $url
     * @return array{
     *     status: int|null,
     *     body: string|null,
     *     content_type: string|null,
     *     redirect_chain: array<array{from: string, to: string, status: int}>,
     *     final_url: string,
     *     error: string|null
     * }
     */
    public function fetch(string $url): array;

}
