<?php

namespace Ortic\DuckDB;

use Illuminate\Database\Connection;
use Ortic\DuckDB\Query\DuckDBGrammar as QueryGrammar;
use Ortic\DuckDB\Query\DuckDBProcessor as PostProcessor;
use Ortic\DuckDB\Schema\DuckDBSchemaBuilder as SchemaBuilder;
use Ortic\DuckDB\Schema\DuckDBSchemaGrammar as SchemaGrammar;

class DuckDBConnection extends Connection
{
    public function getDriverTitle()
    {
        return 'DuckDB';
    }

    protected function getDefaultQueryGrammar()
    {
        return new QueryGrammar($this);
    }

    protected function getDefaultSchemaGrammar()
    {
        return new SchemaGrammar($this);
    }

    protected function getDefaultPostProcessor()
    {
        return new PostProcessor;
    }

    public function getSchemaBuilder()
    {
        if ($this->schemaGrammar === null) {
            $this->useDefaultSchemaGrammar();
        }

        return new SchemaBuilder($this);
    }
}
