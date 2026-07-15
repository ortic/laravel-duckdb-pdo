<?php

namespace Ortic\DuckDB;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use Ortic\DuckDB\Console\InstallDdevCommand;

class DuckDBServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Connection::resolverFor('duckdb', function ($pdo, $database, $prefix, $config) {
            return new DuckDBConnection($pdo, $database, $prefix, $config);
        });

        $this->app->bind('db.connector.duckdb', DuckDBConnector::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallDdevCommand::class,
            ]);
        }
    }
}
