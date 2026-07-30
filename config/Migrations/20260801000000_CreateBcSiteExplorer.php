<?php
declare(strict_types=1);

use BaserCore\Database\Migration\BcMigration;

class CreateBcSiteExplorer extends BcMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up()
    {
        $this->table('bc_site_explorer_crawls', [
            'collation' => 'utf8mb4_general_ci'
        ])
            ->addColumn('site_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('start_url', 'string', ['limit' => 500, 'null' => false])
            ->addColumn('options', 'text', ['default' => null, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'waiting', 'null' => false])
            ->addColumn('queued_count', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('fetched_count', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('error_count', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('db_matched_count', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('orphan_count', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('error_message', 'text', ['default' => null, 'null' => true])
            ->addColumn('started', 'datetime', ['default' => null, 'null' => true])
            ->addColumn('finished', 'datetime', ['default' => null, 'null' => true])
            ->addColumn('created', 'datetime', ['default' => null, 'null' => true])
            ->addColumn('modified', 'datetime', ['default' => null, 'null' => true])
            ->create();

        $this->table('bc_site_explorer_results', [
            'collation' => 'utf8mb4_general_ci'
        ])
            ->addColumn('crawl_id', 'integer', ['null' => false])
            ->addColumn('url', 'text', ['null' => false])
            ->addColumn('url_hash', 'char', ['limit' => 40, 'null' => false])
            ->addColumn('crawl_state', 'string', ['limit' => 20, 'default' => 'queued', 'null' => false])
            ->addColumn('depth', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('parent_url', 'text', ['default' => null, 'null' => true])
            ->addColumn('http_status', 'integer', ['default' => null, 'null' => true])
            ->addColumn('content_type_header', 'string', ['limit' => 100, 'default' => null, 'null' => true])
            ->addColumn('title', 'string', ['limit' => 500, 'default' => null, 'null' => true])
            ->addColumn('redirect_chain', 'text', ['default' => null, 'null' => true])
            ->addColumn('final_url', 'text', ['default' => null, 'null' => true])
            ->addColumn('meta_robots', 'string', ['limit' => 50, 'default' => null, 'null' => true])
            ->addColumn('source', 'string', ['limit' => 10, 'default' => 'crawl', 'null' => false])
            ->addColumn('content_id', 'integer', ['default' => null, 'null' => true])
            ->addColumn('content_kind', 'string', ['limit' => 50, 'default' => null, 'null' => true])
            ->addColumn('content_title', 'string', ['limit' => 500, 'default' => null, 'null' => true])
            ->addColumn('content_status', 'boolean', ['default' => null, 'null' => true])
            ->addColumn('is_orphan', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('error_message', 'text', ['default' => null, 'null' => true])
            ->addColumn('created', 'datetime', ['default' => null, 'null' => true])
            ->addColumn('modified', 'datetime', ['default' => null, 'null' => true])
            ->addIndex(['crawl_id', 'url_hash'], ['unique' => true, 'name' => 'ux_bc_site_explorer_results_crawl_hash'])
            ->addIndex(['crawl_id', 'crawl_state'], ['name' => 'ix_bc_site_explorer_results_state'])
            ->addIndex(['crawl_id', 'source'], ['name' => 'ix_bc_site_explorer_results_source'])
            ->addIndex(['crawl_id', 'is_orphan'], ['name' => 'ix_bc_site_explorer_results_orphan'])
            ->create();
    }

    /**
     * Down Method.
     *
     * @return void
     */
    public function down()
    {
        $this->table('bc_site_explorer_results')->drop()->save();
        $this->table('bc_site_explorer_crawls')->drop()->save();
    }
}
