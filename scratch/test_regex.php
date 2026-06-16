<?php
preg_match_all('/\(((?>[^()]+|(?R))*)\)/s', '(val1, NOW()), (val3, val4)', $m);
var_dump($m[1]);
