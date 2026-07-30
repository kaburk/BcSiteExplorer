<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Model\Entity;

use Cake\ORM\Entity;

/**
 * BcSiteExplorerCrawl
 *
 * 走査セッション
 *
 * @property int $id
 * @property int|null $site_id
 * @property string $start_url
 * @property string|null $options 実行オプションの JSON スナップショット
 * @property string $status waiting | running | completed | failed | canceled
 * @property int $queued_count
 * @property int $fetched_count
 * @property int $error_count
 * @property int $db_matched_count
 * @property int $orphan_count
 * @property string|null $error_message
 * @property \Cake\I18n\DateTime|null $started
 * @property \Cake\I18n\DateTime|null $finished
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class BcSiteExplorerCrawl extends Entity
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
     * オプションを配列で取得する
     *
     * @return array
     */
    public function getOptionsArray(): array
    {
        if (!$this->options) return [];
        $options = json_decode($this->options, true);
        return is_array($options)? $options : [];
    }

}
