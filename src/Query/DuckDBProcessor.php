<?php

namespace Ortic\DuckDB\Query;

use Illuminate\Database\Query\Processors\PostgresProcessor;

/**
 * DuckDB supports INSERT ... RETURNING, so the Postgres processor's
 * RETURNING-based processInsertGetId() works unchanged.
 */
class DuckDBProcessor extends PostgresProcessor
{
}
