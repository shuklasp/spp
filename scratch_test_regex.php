<?php
preg_match('/^UPDATE\s+([a-zA-Z0-9_\.]+)\s+SET\s+(.+?)(?:\s+WHERE\s+(.+?))?$/i', 'UPDATE t SET a=1 WHERE b=2', $matches);
print_r($matches);
