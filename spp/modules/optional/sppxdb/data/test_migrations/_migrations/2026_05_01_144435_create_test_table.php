<?php

use SPPMod\SPPXDB\SPP_XDB;

return new class () {
    public function up(SPP_XDB $db)
    {
        $db->querySQL("CREATE TABLE migration_test (id int, name varchar)");
    }
    public function down(SPP_XDB $db)
    {
        $db->querySQL("DROP TABLE migration_test");
    }
};
