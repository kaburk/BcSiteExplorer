<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Service\Crawler;

/**
 * LinkExtractor
 *
 * HTML からタイトル・meta robots・リンク（a[href]・area[href]）を抽出する。
 * DOMDocument を使用し、外部ライブラリには依存しない。
 */
class LinkExtractor
{

    /**
     * HTML を解析する
     *
     * @param string $html
     * @param string $baseUrl このページの URL（<base href> があればそちらを優先）
     * @return array{title: string|null, meta_robots: string|null, base: string, links: string[]}
     */
    public function extract(string $html, string $baseUrl): array
    {
        $result = ['title' => null, 'meta_robots' => null, 'base' => $baseUrl, 'links' => []];
        if (trim($html) === '') return $result;

        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        try {
            // 文字化け防止のため encoding 宣言を補う
            $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
        $xpath = new \DOMXPath($doc);

        $titleNode = $xpath->query('//title')->item(0);
        if ($titleNode) {
            $result['title'] = mb_substr(trim($titleNode->textContent), 0, 500);
        }

        foreach($xpath->query('//meta[@name]') as $meta) {
            /** @var \DOMElement $meta */
            if (strtolower($meta->getAttribute('name')) === 'robots') {
                $result['meta_robots'] = mb_substr(strtolower(trim($meta->getAttribute('content'))), 0, 50);
                break;
            }
        }

        $baseNode = $xpath->query('//base[@href]')->item(0);
        if ($baseNode) {
            /** @var \DOMElement $baseNode */
            $href = trim($baseNode->getAttribute('href'));
            if ($href !== '') $result['base'] = $href;
        }

        $links = [];
        foreach($xpath->query('//a[@href] | //area[@href]') as $node) {
            /** @var \DOMElement $node */
            $href = trim($node->getAttribute('href'));
            if ($href === '') continue;
            $links[$href] = true;
        }
        $result['links'] = array_keys($links);
        return $result;
    }

    /**
     * meta robots に nofollow が含まれるか
     *
     * @param string|null $metaRobots
     * @return bool
     */
    public function hasNofollow(?string $metaRobots): bool
    {
        return $metaRobots !== null && str_contains($metaRobots, 'nofollow');
    }

}
