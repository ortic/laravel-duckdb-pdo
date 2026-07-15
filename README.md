# laravel-duckdb

A [DuckDB](https://duckdb.org) database driver for Laravel, backed by the native
[`pdo_duckdb`](https://github.com/ortic/php-pdo-duckdb) PHP extension.

DuckDB's SQL dialect is largely PostgreSQL-compatible, so this package extends
Laravel's Postgres query and schema grammars and adjusts the few places DuckDB
differs (auto-increment via sequences, `CREATE [UNIQUE] INDEX` instead of
`ALTER TABLE ADD CONSTRAINT`, no row-level locking, and `information_schema`
introspection).

An in-memory DuckDB is a fast, isolated database for tests; a file-backed
DuckDB is a capable analytical store.

> **Status:** early development, tracking the `pdo_duckdb` extension.

## Requirements

- PHP 8.2+ with the [`pdo_duckdb`](https://github.com/ortic/php-pdo-duckdb)
  extension installed and enabled.
- Laravel 11 or 12 (`illuminate/database` `^11 | ^12`).

## Installation

```bash
composer require ortic/laravel-duckdb
```

The service provider is auto-discovered. It registers the `duckdb` database
driver.

## Configuration

Add a connection to `config/database.php`:

```php
'connections' => [
    'duckdb' => [
        'driver'   => 'duckdb',
        'database' => database_path('analytics.duckdb'), // or ':memory:'
        'prefix'   => '',
        // optional DuckDB config:
        // 'read_only'  => false,
        // 'threads'    => 4,
        // 'max_memory' => '4GB',
    ],
],
```

For an in-memory database use `'database' => ':memory:'` (handy for the testing
connection in `phpunit.xml`).

## Usage

Everything goes through the standard Laravel APIs:

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Schema::connection('duckdb')->create('events', function ($table) {
    $table->id();
    $table->string('name');
    $table->timestamp('occurred_at');
});

DB::connection('duckdb')->table('events')->insert([
    'name' => 'signup', 'occurred_at' => now(),
]);

$count = DB::connection('duckdb')->table('events')->count();
```

Eloquent works too — point a model at the connection:

```php
class Event extends Model
{
    protected $connection = 'duckdb';
}
```

## What works

- Migrations & the schema builder: create/drop tables, columns, indexes and
  unique indexes; `hasTable`/`hasColumn`/`getColumnListing`.
- Query builder: inserts (incl. `insertGetId` via `RETURNING`), selects,
  wheres, ordering, aggregates, updates, deletes.
- Transactions (`DB::transaction`, `beginTransaction`/`commit`/`rollBack`).
- Eloquent: create/find/update/delete, casts, query scopes.

## DuckDB-specific notes

- **Auto-increment** (`$table->id()`, `increments()`) is implemented with a
  DuckDB sequence plus `DEFAULT nextval(...)`. As with Postgres sequences, ids
  consumed inside a rolled-back transaction are **not** reused.
- **Row locking** (`lockForUpdate`, `sharedLock`) compiles to nothing — DuckDB
  uses optimistic MVCC and has no `SELECT ... FOR UPDATE`.
- **Unique/index** definitions become `CREATE [UNIQUE] INDEX`; DuckDB does not
  support adding these via `ALTER TABLE`.
- Booleans bound through untyped bindings follow the extension's rules — see the
  [`pdo_duckdb` notes on parameter binding](https://github.com/ortic/php-pdo-duckdb#parameter-binding).

## Running the tests

The suite spins up an in-memory DuckDB via `Illuminate\Database\Capsule`:

```bash
composer install
php -d extension=pdo_duckdb vendor/bin/phpunit
```

(`mbstring` must be enabled — a standard Laravel requirement.)

## License

MIT.
