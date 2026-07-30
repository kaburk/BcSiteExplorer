<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Model\Entity;

use Cake\ORM\Entity;

/**
 * BcSiteExplorerResult
 *
 * URL 単位の結果（クロール中は BFS キューを兼ねる）
 *
 * @property int $id
 * @property int $crawl_id
 * @property string $url 正規化済みフル URL
 * @property string $url_hash sha1(url)
 * @property string $crawl_state queued | fetched | error | external | skipped
 * @property int $depth
 * @property string|null $parent_url 最初に発見したページの URL
 * @property int|null $http_status
 * @property string|null $content_type_header
 * @property string|null $title HTML の title
 * @property string|null $redirect_chain JSON [{"from","to","status"},...]
 * @property string|null $final_url リダイレクト最終到達 URL
 * @property string|null $meta_robots
 * @property string $source crawl | db | both
 * @property int|null $content_id
 * @property string|null $content_kind
 * @property string|null $content_title
 * @property string|null $content_description BcSeo の meta description
 * @property bool|null $content_status
 * @property bool $is_orphan
 * @property string|null $error_message
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class BcSiteExplorerResult extends Entity
{

    /**
     * accessible
     * @var array
     */
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];

    /**
     * リダイレクトチェーンを「A → B → C」表記で取得する
     *
     * @return string
     */
    public function getRedirectChainText(): string
    {
        if (!$this->redirect_chain) return '';
        $chain = json_decode($this->redirect_chain, true);
        if (!is_array($chain) || !$chain) return '';
        $urls = [$chain[0]['from'] ?? ''];
        foreach($chain as $hop) {
            $urls[] = $hop['to'] ?? '';
        }
        return implode(' → ', array_filter($urls, 'strlen'));
    }

}
