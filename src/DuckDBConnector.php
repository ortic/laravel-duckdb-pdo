<?php

namespace Ortic\DuckDB;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;

class DuckDBConnector extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection.
     */
    public function connect(array $config)
    {
        return $this->createConnection($this->getDsn($config), $config, $this->getOptions($config));
    }

    /**
     * Build the pdo_duckdb DSN from the connection config.
     */
    protected function getDsn(array $config): string
    {
        $database = $config['database'] ?? ':memory:';

        if ($database === '' || $database === ':memory:' || ! empty($config['memory'])) {
            return 'duckdb::memory:';
        }

        $params = ['dbname=' . $database];

        if (! empty($config['read_only'])) {
            $params[] = 'access_mode=READ_ONLY';
        }
        foreach (['threads', 'max_memory'] as $key) {
            if (! empty($config[$key])) {
                $params[] = $key . '=' . $config[$key];
            }
        }

        return 'duckdb:' . implode(';', $params);
    }
}
