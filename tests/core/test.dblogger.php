<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPP\SPPEvent;
use SPPMod\SPPLogger\SPP_Logger;
use SPPMod\SPPLogger\DBLoggerProvider;
use SPPMod\SPPDB\SPPDB;

require_once SPP_MODULES_DIR . '/spp/spplogger/class.spplogger.php';

class DBLoggerTest extends SPPTestCase
{
    private SPPDB $db;
    private string $table;

    public function setUp(): void
    {
        $this->db = new SPPDB();
        $this->table = SPPDB::sppTable('logger');

        // Ensure clean state
        if ($this->db->tableExists($this->table)) {
            $this->db->exec_squery('DROP TABLE %tab%', $this->table);
        }
    }

    public function testDBLoggerProviderWritesSuccessfully()
    {
        $provider = new DBLoggerProvider();
        
        $metadata = [
            'uid' => 'test-uid',
            'uname' => 'test-uname',
            'ip' => '127.0.0.1',
            'timestamp' => date('Y-m-d H:i:s'),
            'sessid' => 'test-sess',
            'uri' => '/test',
            'method' => 'GET',
            'agent' => 'Parikshak'
        ];

        $result = $provider->write('Test DB Log Message', 'info', $metadata, ['key' => 'value']);
        
        $this->assertTrue($result, 'DBLoggerProvider should return true on success');
        $this->assertTrue($this->db->tableExists($this->table), 'DBLoggerProvider should create the logger table');

        $rows = $this->db->execute_query('SELECT * FROM ' . $this->table . ' WHERE descr = ?', ['Test DB Log Message']);
        $this->assertEquals(1, count($rows), 'Should have inserted one log entry');
        
        $row = $rows[0];
        $this->assertEquals('info', $row['level']);
        $this->assertEquals('test-uid', $row['uid']);
        $this->assertEquals('{"key":"value"}', $row['context']);
    }

    public function testEventBusTriggersDBLogger()
    {
        // Force SPP_Logger to use DB logging
        SPP_Logger::setTarget('db', new DBLoggerProvider());

        // In a real app, module config defines log_precedence and log_targets
        // We will just directly call SPP_Logger::write_to_db for isolation
        $metadata = [
            'uid' => '', 'uname' => '', 'ip' => '127.0.0.1', 'timestamp' => date('Y-m-d H:i:s'),
            'sessid' => '', 'uri' => '', 'method' => '', 'agent' => ''
        ];
        SPP_Logger::write_to_db('Event Bus DB Log', 'warning', $metadata, []);

        $rows = $this->db->execute_query('SELECT * FROM ' . $this->table . ' WHERE descr = ?', ['Event Bus DB Log']);
        $this->assertEquals(1, count($rows), 'Should have inserted via SPP_Logger::write_to_db');
        $this->assertEquals('warning', $rows[0]['level']);
    }
}
