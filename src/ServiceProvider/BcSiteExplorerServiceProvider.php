<?php
declare(strict_types=1);
/**
 * BcSiteExplorer : baserCMS 5 サイト構造解析・サイトマップ作成プラグイン
 */

namespace BcSiteExplorer\ServiceProvider;

use BcSiteExplorer\Service\BcSiteExplorerService;
use BcSiteExplorer\Service\BcSiteExplorerServiceInterface;
use Cake\Core\ServiceProvider;

/**
 * BcSiteExplorerServiceProvider
 */
class BcSiteExplorerServiceProvider extends ServiceProvider
{

    /**
     * Provides
     * @var string[]
     */
    protected array $provides = [
        BcSiteExplorerServiceInterface::class,
    ];

    /**
     * Services
     * @param \Cake\Core\ContainerInterface $container
     */
    public function services($container): void
    {
        $container->defaultToShared(true);
        $container->add(BcSiteExplorerServiceInterface::class, BcSiteExplorerService::class);
    }

}
