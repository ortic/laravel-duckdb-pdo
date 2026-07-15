<?php

namespace Ortic\DuckDB\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Fluent;

class DuckDBSchemaGrammar extends PostgresGrammar
{
    /**
     * DuckDB has no SERIAL / IDENTITY columns; auto-increment is emulated with
     * a sequence plus a DEFAULT nextval(...). Create those sequences before the
     * table that references them.
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        $statements = [];

        foreach ($blueprint->getColumns() as $column) {
            if ($this->isAutoIncrement($column)) {
                $statements[] = 'create sequence if not exists '
                    . $this->wrapValue($this->sequenceName($blueprint, $column));
            }
        }

        $statements[] = parent::compileCreate($blueprint, $command);

        return $statements;
    }

    protected function isAutoIncrement(Fluent $column): bool
    {
        return $column->autoIncrement
            && ! $column->change
            && in_array($column->type, $this->serials, true);
    }

    protected function sequenceName(Blueprint $blueprint, Fluent $column): string
    {
        return $blueprint->getTable() . '_' . $column->name . '_seq';
    }

    /**
     * Replace Postgres' serial/identity handling with a DuckDB sequence default.
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (! $this->isAutoIncrement($column)) {
            return null;
        }

        $sql = " default nextval('" . $this->sequenceName($blueprint, $column) . "')";

        if (! $this->hasCommand($blueprint, 'primary')) {
            $sql .= ' primary key';
        }

        return $sql;
    }

    /*
     * DuckDB does not support ALTER TABLE ADD CONSTRAINT ... UNIQUE, but it does
     * support CREATE [UNIQUE] INDEX. Compile unique/index accordingly, and drop
     * them with DROP INDEX.
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('create unique index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return sprintf('create index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        return 'drop index ' . $this->wrap($command->index);
    }

    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        return 'drop index ' . $this->wrap($command->index);
    }

    /* DuckDB rejects serial/bigserial; always emit plain integer types. */
    protected function typeInteger(Fluent $column)       { return 'integer'; }
    protected function typeBigInteger(Fluent $column)    { return 'bigint'; }
    protected function typeMediumInteger(Fluent $column) { return 'integer'; }
    protected function typeSmallInteger(Fluent $column)  { return 'smallint'; }
    protected function typeTinyInteger(Fluent $column)   { return 'tinyint'; }
}
