<?php

require_once 'c:/projects/apache/school1/spp/sppinit.php';

class AdvancedUser extends \SPPMod\SPPEntity\SPPEntity
{
    public static function boot()
    {
        self::setMetadata('table', 'adv_users');
        self::setMetadata('id_field', 'uuid');
        self::setMetadata('key_type', 'uuid'); // Generate UUIDs
        self::setMetadata('casts', [
            'settings' => 'json'
        ]);
        self::setMetadata('attributes', [
            'name' => 'varchar(255)',
            'settings' => 'text',
        ]);
    }
}
AdvancedUser::boot();

$db = new \SPPMod\SPPDB\SPPDB();
$db->exec('DROP TABLE IF EXISTS lek_adv_users');
AdvancedUser::install();

// 1. Test UUID and JSON Casting (Insert)
$u1 = new AdvancedUser();
$u1->set('name', 'Alice');
$u1->set('settings', ['theme' => 'dark', 'notifications' => true]); // array assigned directly
$u1->save();

echo "User 1 ID (UUID): " . $u1->getId() . "\n";
if (strlen($u1->getId()) !== 36) {
    echo "ERROR: UUID format incorrect!\n";
}

// 2. Test fetching and decoding JSON (Load)
$fetchedUser = AdvancedUser::query()->condition('uuid', $u1->getId())->execute()[0];
$settings = $fetchedUser->get('settings');
if (is_array($settings) && $settings['theme'] === 'dark') {
    echo "JSON Casting: SUCCESS\n";
} else {
    echo "JSON Casting: FAILED. Settings: " . print_r($settings, true) . "\n";
}

// 3. Test Pagination
for ($i = 0; $i < 25; $i++) {
    $u = new AdvancedUser();
    $u->set('name', "User $i");
    $u->set('settings', ['index' => $i]);
    $u->save();
}

$paginated = AdvancedUser::query()->paginate(10, 2); // 10 per page, page 2

echo "Pagination Total Records: " . $paginated['total'] . " (Expected 26)\n";
echo "Pagination Last Page: " . $paginated['last_page'] . " (Expected 3)\n";
echo "Pagination Current Page Items: " . count($paginated['data']) . " (Expected 10)\n";

if ($paginated['total'] == 26 && count($paginated['data']) == 10) {
    echo "Pagination: SUCCESS\n";
} else {
    echo "Pagination: FAILED\n";
}

echo "DONE\n";
