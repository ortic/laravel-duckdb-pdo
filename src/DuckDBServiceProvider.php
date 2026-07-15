<?php

namespace Ortic\DuckDB;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;

class DuckDBServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Connection::resolverFor('duckdb', function ($pdo, $database, $prefix, $config) {
            return new DuckDBConnection($pdo, $database, $prefix, $config);
        });

        $this->app->bind('db.connector.duckdb', DuckDBConnector::class);
    }
}
