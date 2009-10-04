<?php

use Nette\Debug;
use Nette\Environment;
use Nette\Loaders\RobotLoader;
use Nette\Application\SimpleRouter;
use Nette\Application\IRouter;

require LIBS_DIR . '/Nette/loader.php';
//
// Change this to path where you have ConfirmationDialog Component
require APP_DIR . '../../ConfirmationDialog/ConfirmationDialog.php';

Debug::enable();

Environment::loadConfig();

$application = Environment::getApplication();

$router = $application->getRouter();


$router[] = new Nette\Application\SimpleRouter(array(
	'presenter' => 'Default',
	'action' => 'default',
));



$application->run();
