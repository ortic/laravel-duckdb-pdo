<?php

namespace Ortic\DuckDB\Query;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

class DuckDBGrammar extends PostgresGrammar
{
    /**
     * DuckDB does not support explicit row-level locking (SELECT ... FOR UPDATE);
     * it uses optimistic MVCC, so compile locks to nothing.
     */
    protected function compileLock(Builder $query, $value)
    {
        return '';
    }
}
