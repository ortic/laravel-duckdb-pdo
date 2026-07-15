# laravel-duckdb-pdo

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
composer require ortic/laravel-duckdb-pdo
```

The service provider is auto-discovered. It registers the `duckdb` database
driver.

> This package **requires** the `pdo_duckdb` extension (declared as
> `ext-pdo_duckdb`), so `composer require` will refuse to install until the
> extension is present in your runtime. See the DDEV section below to build it
> into a container.

## DDEV

DDEV's web image doesn't ship `pdo_duckdb`, so it has to be compiled into the
container. Because a Composer package cannot install a PHP extension (and this
package won't even install without it), setting up the image is a **bootstrap
step that runs before `composer require`**.

Fetch the web-build Dockerfile straight from the extension repo and rebuild:

```bash
mkdir -p .ddev/web-build
curl -fsSL https://raw.githubusercontent.com/ortic/php-pdo-duckdb/main/examples/ddev/web-build/Dockerfile \
  -o .ddev/web-build/Dockerfile
ddev restart          # builds pdo_duckdb into the web image
ddev composer require ortic/laravel-duckdb-pdo
```

Once the package is installed, you can regenerate that Dockerfile any time with:

```bash
php artisan duckdb:install-ddev          # --force to overwrite an existing one
```

(The artisan command is a convenience for re-scaffolding; the `curl` step above
is what bootstraps a project from scratch, since the extension must exist before
the package can be installed.)

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
