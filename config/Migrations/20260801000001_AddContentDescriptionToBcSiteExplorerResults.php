<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class AddContentDescriptionToBcSiteExplorerResults extends BcMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up()
    {
        $this->table('bc_site_explorer_results')
            ->addColumn('content_description', 'string', [
                'default' => null,
                'limit' => 500,
                'null' => true,
                'after' => 'content_title',
            ])
            ->update();
    }

    /**
     * Down Method.
     *
     * @return void
     */
    public function down()
    {
        $this->table('bc_site_explorer_results')
            ->removeColumn('content_description')
            ->update();
    }
}
