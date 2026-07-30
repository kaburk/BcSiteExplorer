<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * BcSiteExplorerCrawlsTable
 */
class BcSiteExplorerCrawlsTable extends Table
{

    /**
     * initialize
     *
     * @param array $config
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('bc_site_explorer_crawls');
        $this->addBehavior('Timestamp');
        $this->hasMany('BcSiteExplorerResults', [
            'className' => 'BcSiteExplorer.BcSiteExplorerResults',
            'foreignKey' => 'crawl_id',
        ]);
    }

    /**
     * validationDefault
     *
     * @param Validator $validator
     * @return Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator->notEmptyString('start_url', __d('baser_core', '開始URLを入力してください。'));
        $validator->add('start_url', 'validUrl', [
            'rule' => function($value) {
                return (bool)filter_var($value, FILTER_VALIDATE_URL)
                    && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
            },
            'message' => __d('baser_core', '開始URLは http(s):// から始まるURLを入力してください。')
        ]);
        $validator->maxLength('start_url', 500, __d('baser_core', '開始URLは500文字以内で入力してください。'));
        return $validator;
    }

}
