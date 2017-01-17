<?php
require dirname(__DIR__) . '/vendor/autoload.php';

// For multi-process. >>> DO NOT REMOVE THIS STATEMENT <<<
declare(ticks = 1);

error_reporting(-1);
date_default_timezone_set('Asia/Tokyo');

ini_set('default_socket_timeout', -1);

$config = parse_ini_file($argv[1], true);
$class = "\\Punchmachine\\Benchmarker\\{$config['global']['benchmarker']}";
$benchmaker = new $class($config);
$benchmaker->run();
