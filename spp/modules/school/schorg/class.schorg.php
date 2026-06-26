<?php

require_once('entityexceptions.php');

/**
 * class Ajax
 * extends \SPP\SPPObject
 * Deals with ajax calls
 */
class Organiusation extends \SPPMod\SPPDB\SPPEntity
{
    protected $enttab;
    protected $props = [
        ['pname', 'varchar(40)'],
        ['pval', 'varchar(30)']
    ];
    public function __construct($ename)
    {
        parent::__construct();
        $this->enttab = 'spp_entity_' . $ename;
    }

    public function getTable()
    {
        return $this->enttab;
    }

    public function installEntity()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        if ($db->tableExists($this->enttab)) {
            $query = 'create table ' . $this->enttab . '(entid  varchar(20))';
            $db->execute_query($query);
        }
        foreach ($this->props as $prop) {
            if (!$db->columnExists($this->enttab, $prop[0])) {
                $query = 'alter table ' . $this->enttab . ' add column ' . $prop[0] . '  ' . $prop[1];
                $db->execute_query($query);
            }

        }
    }


}
