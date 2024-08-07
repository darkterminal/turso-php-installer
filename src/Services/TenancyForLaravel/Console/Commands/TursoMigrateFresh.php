<?php

declare(strict_types=1);

namespace Stancl\Tenancy\Commands;

use Illuminate\Console\Command;
use Stancl\Tenancy\Concerns\DealsWithMigrations;
use Stancl\Tenancy\Concerns\HasATenantsOption;
use Symfony\Component\Console\Input\InputOption;

final class MigrateFresh extends Command
{
    use HasATenantsOption, DealsWithMigrations;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop all tables and re-run all migrations for tenant(s)';

    public function __construct()
    {
        parent::__construct();

        $this->addOption('--drop-views', null, InputOption::VALUE_NONE, 'Drop views along with tenant tables.', null);
        $this->addOption('--step', null, InputOption::VALUE_NONE, 'Force the migrations to be run so they can be rolled back individually.');

        $this->setName('tenants:migrate-fresh');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        tenancy()->runForMultiple($this->option('tenants'), function ($tenant) {
            $this->line("Tenant: {$tenant->getTenantKey()}");
            $this->info('Dropping tables.');
            $this->call('tenants:rollback', config('tenancy.migration_parameters'));

            $this->info('Migrating.');
            $this->call('tenants:migrate', config('tenancy.migration_parameters'));
        });

        $this->info('Done.');
    }
}
