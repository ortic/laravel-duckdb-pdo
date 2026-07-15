<?php

namespace Ortic\DuckDB\Schema;

use Illuminate\Database\Schema\Builder;

class DuckDBSchemaBuilder extends Builder
{
    /**
     * DuckDB exposes its catalog through information_schema (there is no
     * pg_catalog), so answer introspection queries from there.
     */
    public function hasTable($table)
    {
        [$schema, $table] = $this->splitTableRef($table);

        $table = $this->connection->getTablePrefix() . $table;

        return (bool) $this->connection->scalar(
            'select count(*) from information_schema.tables where table_name = ? and table_schema = ?',
            [$table, $schema ?: 'main']
        );
    }

    public function getColumnListing($table)
    {
        $table = $this->connection->getTablePrefix() . $table;

        $results = $this->connection->select(
            'select column_name from information_schema.columns where table_name = ? order by ordinal_position',
            [$table]
        );

        return array_map(fn ($r) => $r->column_name, $results);
    }

    public function hasColumn($table, $column)
    {
        return in_array(
            strtolower($column),
            array_map('strtolower', $this->getColumnListing($table)),
            true
        );
    }

    public function dropAllTables()
    {
        $tables = $this->connection->select(
            "select table_name from information_schema.tables where table_schema = 'main' and table_type = 'BASE TABLE'"
        );

        foreach ($tables as $table) {
            $this->connection->statement(
                'drop table if exists ' . $this->grammar->wrapTable($table->table_name) . ' cascade'
            );
        }
    }

    protected function splitTableRef($reference): array
    {
        $parts = explode('.', $reference, 2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : [null, $parts[0]];
    }
}
