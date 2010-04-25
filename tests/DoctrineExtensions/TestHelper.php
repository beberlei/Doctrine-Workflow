<?php
namespace DoctrineExtensions;

class TestHelper
{
    static public function getConnection()
    {
        return \Doctrine\DBAL\DriverManager::getConnection(array(
            'driver' => $GLOBALS['DC2_DRIVER'],
            'dbname' => $GLOBALS['DC2_DBNAME'],
            'user' => $GLOBALS['DC2_USER'],
            'password' => $GLOBALS['DC2_PASSWORD'],
            'memory' => true, // for sqlite
        ));
    }
}

$loaderfile = $GLOBALS['doctrine2-path']."/Doctrine/Common/ClassLoader.php";
if (!file_exists($loaderfile)) {
    throw new \InvalidArgumentException('Could not include Doctrine\Common\ClassLoader from "doctrine2-path".');
}
require_once($loaderfile);

$loader = new \Doctrine\Common\ClassLoader("Doctrine", $GLOBALS['doctrine2-path']);
$loader->register();

$loader = new \Doctrine\Common\ClassLoader("DoctrineExtensions", __DIR__."/../../lib");
$loader->register();

if (!isset($GLOBALS['ezc-base-file']) || !file_exists($GLOBALS['ezc-base-file'])) {
    throw new \InvalidArgumentException('No path to the ezzBase class file given or file does not exist!');
}

require_once $GLOBALS['ezc-base-file'];
spl_autoload_register(array('ezcBase', 'autoload'));

if (!isset($GLOBALS['doctrine2-path'])) {
    throw new \InvalidArgumentException('Global variable "doctrine2-path" has to be set in phpunit.xml');
}

if (!isset($GLOBALS['ezc-workflow-tests-dir']) || !file_exists($GLOBALS['ezc-workflow-tests-dir'])) {
    throw new \InvalidArgumentException('No path to the ezzWorkflow tests directory given or directory does not exist!');
}

require_once $GLOBALS['ezc-workflow-tests-dir'] . "/case.php";