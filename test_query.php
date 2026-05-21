<?php
require_once 'spp/core/spp.php';

use SPPMod\SPPEntity\SppEntityQuery;

$query = new SppEntityQuery('node');
$query->condition('status', 1)
      ->condition('field_title', 'Hello%', 'LIKE')
      ->orderBy('created', 'DESC')
      ->limit(10);

var_dump($query->execute());

