<?php

echo "Define LIBS_DIR in " . __FILE__;
exit;

define('WWW_DIR', dirname(__FILE__));
define('APP_DIR', WWW_DIR . '/../app');
//define('LIBS_DIR', WWW_DIR . '/../libs');

require APP_DIR . '/bootstrap.php';