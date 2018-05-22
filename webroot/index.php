<?php

$libpath = ini_get('yaf.library');
$application = new \Yaf\Application ( CONFIG_PATH."/application.ini");
$application->bootstrap()->run();



?>
