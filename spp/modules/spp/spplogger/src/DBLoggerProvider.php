<?php
namespace SPPMod\SPPLogger;

class DBLoggerProvider implements LoggerTargetInterface
{
    public function write(string $message, string $level, array $metadata, array $context = []): bool
    {
        try {
            if (!class_exists('\SPPMod\SPPDB\SPPDB')) {
                return false;
            }

            $db = new \SPPMod\SPPDB\SPPDB();
            $tableName = \SPPMod\SPPDB\SPPDB::sppTable('logger');

            // Ensure table and columns exist
            if (!$db->tableExists($tableName)) {
                $db->exec_squery('create table %tab% (loggerid varchar(40))', $tableName);
            }

            if (!\SPPMod\SPPDB\SPPSequence::sequenceExists('loggerid')) {
                \SPPMod\SPPDB\SPPSequence::createSequence('loggerid', 1, 1);
            }

            $requiredCols = [
                'uid' => 'varchar(50)',
                'uname' => 'varchar(100)',
                'ip' => 'varchar(50)',
                'logtime' => 'datetime',
                'sessid' => 'varchar(100)',
                'level' => 'varchar(20)',
                'descr' => 'text',
                'context' => 'text',
                'request_uri' => 'text',
                'method' => 'varchar(10)',
                'agent' => 'text'
            ];
            $db->add_columns($tableName, $requiredCols);

            $sql = 'insert into %tab%(loggerid,uid,uname,ip,logtime,sessid,level,descr,context,request_uri,method,agent) values(?,?,?,?,?,?,?,?,?,?,?,?)';

            $values = [
                date('Ymd', time()) . \SPPMod\SPPDB\SPPSequence::next('loggerid', true),
                $metadata['uid'] ?? '',
                $metadata['uname'] ?? '',
                $metadata['ip'] ?? '',
                $metadata['timestamp'] ?? '',
                $metadata['sessid'] ?? '',
                $level,
                $message,
                json_encode($context),
                $metadata['uri'] ?? '',
                $metadata['method'] ?? '',
                $metadata['agent'] ?? ''
            ];

            $db->exec_squery($sql, $tableName, $values);
            return true;
        } catch (\Exception $e) {
            error_log("Logging to DB failed: " . $e->getMessage());
            return false;
        }
    }
}
