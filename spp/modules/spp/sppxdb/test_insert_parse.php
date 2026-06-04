<?php

$sql = "insert into invoices values('INV-003', 'Pragyesh Corporation', 20000, 'paid')";
if (preg_match('/^INSERT\s+INTO\s+([a-zA-Z0-9_\.]+)\s*(?:\((.+?)\))?\s*VALUES\s*\((.+?)\)$/i', $sql, $matches)) {
    print_r($matches);
    $values = str_getcsv($matches[3], ',', "'");
    print_r($values);
}
