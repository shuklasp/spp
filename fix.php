<?php
$c = file_get_contents("spp/commands/AdminLegacyCommand.php");
$c = preg_replace('/->json\(array_merge\((.*?), \[\"_notify_msg\" => (.*?), \"(success|error)\", \$args\]\)\);/', '->json(array_merge($1, ["message" => $2, "success" => ("$3" === "success")]));', $c);
file_put_contents("spp/commands/AdminLegacyCommand.php", $c);

