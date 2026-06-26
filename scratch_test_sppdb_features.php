<?php

require_once 'c:/projects/apache/school1/spp/sppinit.php';

class TestUser extends \SPPMod\SPPEntity\SPPEntity
{
    public static function boot()
    {
        self::setMetadata('table', 'test_users_advanced');
        self::setMetadata('id_field', 'id');
        self::setMetadata('soft_delete', true);
        self::setMetadata('attributes', [
            'name' => 'varchar(255)',
            'role' => 'varchar(50)',
        ]);
    }
}
TestUser::boot();

class TestObserver
{
    public function creating($model)
    {
        echo "Creating {$model->get('name')}\n";
    }
    public function deleting($model)
    {
        echo "Deleting {$model->get('name')}\n";
    }
}

TestUser::observe(TestObserver::class);

TestUser::addGlobalScope('activeOnly', function ($query) {
    $query->condition('role', 'banned', '!=');
});

$db = new \SPPMod\SPPDB\SPPDB();
$db->exec('DROP TABLE IF EXISTS test_users_advanced');

TestUser::install();

$u1 = new TestUser();
$u1->set('name', 'Alice');
$u1->set('role', 'admin');
$u1->save();

$u2 = new TestUser();
$u2->set('name', 'Bob');
$u2->set('role', 'banned');
$u2->save();

echo "Testing Scopes: \n";
$users = TestUser::query()->execute();
foreach ($users as $u) {
    echo "Found user: " . $u->get('name') . "\n"; // Should only be Alice
}

echo "Testing Scopes Disabled: \n";
$all_users = TestUser::query()->withoutGlobalScopes()->execute();
foreach ($all_users as $u) {
    echo "Found user: " . $u->get('name') . "\n"; // Alice and Bob
}

echo "Testing Soft Deletes: \n";
$u1->delete(); // Soft delete Alice
$active = TestUser::query()->execute();
echo "Count active: " . count($active) . "\n"; // 0

$trashed = TestUser::query()->withTrashed()->execute();
echo "Count with trashed: " . count($trashed) . "\n"; // 1

echo "Testing Transactions: \n";
try {
    $db->transaction(function ($db) {
        $u3 = new TestUser();
        $u3->set('name', 'Charlie');
        $u3->set('role', 'admin');
        $u3->save();
        throw new \Exception("Rollback Test!");
    });
} catch (\Exception $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}

$charlie = TestUser::query()->condition('name', 'Charlie')->execute();
echo "Charlie exists? " . (count($charlie) > 0 ? 'Yes' : 'No') . "\n";

echo "DONE\n";
