<?php
// The mbstring extension is a standard Laravel requirement. This tiny polyfill
// only exists so the integration test runs in a minimal CI image without it.
if (! function_exists('mb_split')) {
    function mb_split($pattern, $string, $limit = -1) {
        return preg_split('/' . $pattern . '/u', $string, $limit <= 0 ? -1 : $limit);
    }
}

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Ortic\DuckDB\DuckDBConnection;
use Ortic\DuckDB\DuckDBConnector;

$capsule = new Capsule;
Connection::resolverFor('duckdb', fn ($pdo, $db, $prefix, $config) => new DuckDBConnection($pdo, $db, $prefix, $config));
$capsule->getContainer()->bind('db.connector.duckdb', DuckDBConnector::class);
$capsule->addConnection(['driver' => 'duckdb', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$db = $capsule->getConnection();
$schema = $db->getSchemaBuilder();

function ok($label, $cond) { echo ($cond ? 'PASS ' : 'FAIL ') . $label . "\n"; }

// --- Schema ---
$schema->create('users', function ($t) {
    $t->id();
    $t->string('name');
    $t->string('email')->unique();
    $t->integer('age')->nullable();
    $t->boolean('active')->default(true);
    $t->timestamps();
});
ok('hasTable(users)', $schema->hasTable('users'));
ok('hasColumn(users,email)', $schema->hasColumn('users', 'email'));
ok('columns', $schema->getColumnListing('users') === ['id','name','email','age','active','created_at','updated_at']);

// --- Query builder insert + insertGetId ---
$id = $db->table('users')->insertGetId(['name' => 'Amy', 'email' => 'amy@x.io', 'age' => 30, 'active' => true, 'created_at' => '2024-01-01 00:00:00', 'updated_at' => '2024-01-01 00:00:00']);
ok('insertGetId == 1', $id === 1);
$db->table('users')->insert(['name' => 'Ben', 'email' => 'ben@x.io', 'age' => 25, 'active' => false, 'created_at' => '2024-01-02 00:00:00', 'updated_at' => '2024-01-02 00:00:00']);

// --- Select / where / aggregate ---
ok('count == 2', $db->table('users')->count() === 2);
ok('where age>27 -> Amy', $db->table('users')->where('age', '>', 27)->value('name') === 'Amy');
ok('avg age', (int) $db->table('users')->avg('age') === 27);
ok('orderBy+pluck', $db->table('users')->orderBy('age')->pluck('name')->all() === ['Ben','Amy']);

// --- Update / delete ---
$db->table('users')->where('name', 'Ben')->update(['age' => 26]);
ok('update', $db->table('users')->where('name','Ben')->value('age') === 26);
$db->table('users')->where('name', 'Ben')->delete();
ok('delete', $db->table('users')->count() === 1);

// --- Transactions ---
try { $db->transaction(function () use ($db) {
    $db->table('users')->insert(['name'=>'X','email'=>'x@x.io','created_at'=>'2024-01-03 00:00:00','updated_at'=>'2024-01-03 00:00:00']);
    throw new RuntimeException('rollback');
}); } catch (RuntimeException $e) {}
ok('transaction rollback', $db->table('users')->count() === 1);

// --- Eloquent ---
class User extends Model { public $timestamps = true; protected $guarded = []; protected $casts = ['active' => 'boolean']; }
$u = User::create(['name' => 'Cid', 'email' => 'cid@x.io', 'age' => 40, 'active' => true]);
ok('eloquent create id', $u->id > 0);
ok('eloquent find', User::find($u->id)->name === 'Cid');
ok('eloquent where', User::where('age','>',35)->first()->name === 'Cid');
ok('eloquent cast bool', User::find($u->id)->active === true);
$u->update(['age' => 41]);
ok('eloquent update', User::find($u->id)->age === 41);
ok('eloquent count', User::count() === 2);
ok('eloquent delete', tap(User::find($u->id))->delete() && User::count() === 1);

echo "ALL DONE\n";
