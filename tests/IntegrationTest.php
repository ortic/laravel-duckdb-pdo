<?php

namespace Ortic\DuckDB\Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Ortic\DuckDB\DuckDBConnection;
use Ortic\DuckDB\DuckDBConnector;
use PHPUnit\Framework\TestCase;

class TestUser extends Model
{
    protected $table = 'users';
    protected $guarded = [];
    protected $casts = ['active' => 'boolean'];
}

class IntegrationTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_duckdb')) {
            $this->markTestSkipped('pdo_duckdb extension is not loaded');
        }

        $this->capsule = new Capsule;
        Connection::resolverFor('duckdb', fn ($pdo, $db, $prefix, $config) => new DuckDBConnection($pdo, $db, $prefix, $config));
        $this->capsule->getContainer()->bind('db.connector.duckdb', DuckDBConnector::class);
        $this->capsule->addConnection(['driver' => 'duckdb', 'database' => ':memory:', 'prefix' => '']);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $this->db()->getSchemaBuilder()->create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->integer('age')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    private function db(): Connection
    {
        return $this->capsule->getConnection();
    }

    public function test_schema_introspection(): void
    {
        $schema = $this->db()->getSchemaBuilder();
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasColumn('users', 'email'));
        $this->assertSame(
            ['id', 'name', 'email', 'age', 'active', 'created_at', 'updated_at'],
            $schema->getColumnListing('users')
        );
    }

    public function test_query_builder_crud_and_aggregates(): void
    {
        $id = $this->db()->table('users')->insertGetId([
            'name' => 'Amy', 'email' => 'amy@x.io', 'age' => 30, 'active' => true,
            'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00',
        ]);
        $this->assertSame(1, $id);

        $this->db()->table('users')->insert([
            'name' => 'Ben', 'email' => 'ben@x.io', 'age' => 25, 'active' => false,
            'created_at' => '2024-01-02 00:00:00', 'updated_at' => '2024-01-02 00:00:00',
        ]);

        $this->assertSame(2, $this->db()->table('users')->count());
        $this->assertSame('Amy', $this->db()->table('users')->where('age', '>', 27)->value('name'));
        $this->assertSame(27, (int) $this->db()->table('users')->avg('age'));
        $this->assertSame(['Ben', 'Amy'], $this->db()->table('users')->orderBy('age')->pluck('name')->all());

        $this->db()->table('users')->where('name', 'Ben')->update(['age' => 26]);
        $this->assertSame(26, $this->db()->table('users')->where('name', 'Ben')->value('age'));
        $this->db()->table('users')->where('name', 'Ben')->delete();
        $this->assertSame(1, $this->db()->table('users')->count());
    }

    public function test_transaction_rollback(): void
    {
        try {
            $this->db()->transaction(function () {
                $this->db()->table('users')->insert([
                    'name' => 'X', 'email' => 'x@x.io',
                    'created_at' => '2024-01-03 00:00:00', 'updated_at' => '2024-01-03 00:00:00',
                ]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $e) {
            // expected
        }
        $this->assertSame(0, $this->db()->table('users')->count());
    }

    public function test_eloquent_crud_and_casts(): void
    {
        $u = TestUser::create(['name' => 'Cid', 'email' => 'cid@x.io', 'age' => 40, 'active' => true]);
        $this->assertGreaterThan(0, $u->id);
        $this->assertSame('Cid', TestUser::find($u->id)->name);
        $this->assertTrue(TestUser::find($u->id)->active);
        $this->assertSame('Cid', TestUser::where('age', '>', 35)->first()->name);

        $u->update(['age' => 41]);
        $this->assertSame(41, TestUser::find($u->id)->age);
        $this->assertSame(1, TestUser::count());

        $u->delete();
        $this->assertSame(0, TestUser::count());
    }
}
