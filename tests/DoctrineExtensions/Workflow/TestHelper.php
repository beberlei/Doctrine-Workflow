<?php
namespace DoctrineExtensions\Workflow;

class TestHelper
{
    static private $schema = array();

    static private $conn;

    /**
     * Create Schema for this test if not yet done
     *
     * @param WorkflowOptions $options
     */
    static public function createSchema(WorkflowOptions $options)
    {
        $conn = self::getConnection();

        $schemaBuilder = new SchemaBuilder($conn);
        try {
            $schemaBuilder->dropWorkflowSchema($options);
        } catch(\PDOException $e) {

        }
        $schemaBuilder->createWorkflowSchema($options);

        self::$schema[$options->getTablePrefix()] = true;
    }

    static public function getConnection()
    {
        if (self::$conn == null) {
            self::$conn = \Doctrine\DBAL\DriverManager::getConnection(array(
                'driver' => $GLOBALS['DC2_DRIVER'],
                'dbname' => $GLOBALS['DC2_DBNAME'],
                'user' => $GLOBALS['DC2_USER'],
                'password' => $GLOBALS['DC2_PASSWORD'],
                'memory' => true, // for sqlite
            ));
        }
        return self::$conn;
    }
}

if (!isset($GLOBALS['doctrine2-dbal-path']) || !isset($GLOBALS['doctrine2-common-path'])) {
    throw new \InvalidArgumentException('Global variables "doctrine2-common-path" and "doctrine2-dbal-path" have to be set in phpunit.xml');
}

$loaderfile = $GLOBALS['doctrine2-common-path']."/Doctrine/Common/ClassLoader.php";
if (!file_exists($loaderfile)) {
    throw new \InvalidArgumentException('Could not include Doctrine\Common\ClassLoader from "doctrine2-common-path".');
}
require_once($loaderfile);

$loader = new \Doctrine\Common\ClassLoader("Doctrine\Common", $GLOBALS['doctrine2-common-path']);
$loader->register();

$loader = new \Doctrine\Common\ClassLoader("Doctrine\DBAL", $GLOBALS['doctrine2-dbal-path']);
$loader->register();

$loader = new \Doctrine\Common\ClassLoader("DoctrineExtensions\Workflow", __DIR__."/../../../lib");
$loader->register();

if (!isset($GLOBALS['ezc-base-file']) || !file_exists($GLOBALS['ezc-base-file'])) {
    throw new \InvalidArgumentException('No path to the ezzBase class file given or file does not exist!');
}

require_once $GLOBALS['ezc-base-file'];
spl_autoload_register(array('ezcBase', 'autoload'));

if (!isset($GLOBALS['ezc-workflow-tests-dir']) || !file_exists($GLOBALS['ezc-workflow-tests-dir'])) {
    throw new \InvalidArgumentException('No path to the ezzWorkflow tests directory given or directory does not exist!');
}

require_once $GLOBALS['ezc-workflow-tests-dir'] . "/case.php";