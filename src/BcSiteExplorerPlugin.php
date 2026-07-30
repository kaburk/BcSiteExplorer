<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer;

use BaserCore\BcPlugin;
use BcSiteExplorer\ServiceProvider\BcSiteExplorerServiceProvider;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;

/**
 * plugin for BcSiteExplorer
 */
class BcSiteExplorerPlugin extends BcPlugin
{

    /**
     * bootstrap
     *
     * プラグイン単体で導入したライブラリ（phpoffice/phpspreadsheet 等）を
     * 読み込むため、プラグイン直下の vendor があれば autoload する。
     *
     * @param PluginApplicationInterface $app
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        $autoload = dirname(__DIR__) . DS . 'vendor' . DS . 'autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
        parent::bootstrap($app);
    }

    /**
     * services
     *
     * @param ContainerInterface $container
     */
    public function services(ContainerInterface $container): void
    {
        $container->addServiceProvider(new BcSiteExplorerServiceProvider());
    }

}
