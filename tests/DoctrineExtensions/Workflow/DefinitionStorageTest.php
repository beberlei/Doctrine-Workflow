<?php

namespace DoctrineExtensions\Workflow;

class DefinitionStorageTest extends \PHPUnit_Framework_TestCase
{
    private $conn;
    private $options;

    public function setUp()
    {
        $this->conn = \DoctrineExtensions\TestHelper::getConnection();
        $this->options = new WorkflowOptions('test_');
        $schemaBuilder = new SchemaBuilder($this->conn);
        try {
            $schemaBuilder->dropWorkflowSchema($this->options);
        } catch(\PDOException $e) {
            
        }
        $schemaBuilder->createWorkflowSchema($this->options);
    }

    public function testSaveNodes()
    {
        $workflow = new \ezcWorkflow('Test');

        $printAction1 = new \ezcWorkflowNodeAction(array('class' => 'DoctrinExtensions\Workflow\MyPrintAction', 'arguments' => array('Foo')));
        $printAction2 = new \ezcWorkflowNodeAction(array('class' => 'DoctrinExtensions\Workflow\MyPrintAction', 'arguments' => array('Bar')));
        $printAction3 = new \ezcWorkflowNodeAction(array('class' => 'DoctrinExtensions\Workflow\MyPrintAction', 'arguments' => array('Baz')));

        $workflow->startNode->addOutNode($printAction1);
        $printAction2->addInNode($printAction1);
        $printAction3->addInNode($printAction2);
        $workflow->endNode->addInNode($printAction3);

        $this->assertWorkflowPersistance($workflow);
    }

    public function testSaveFinallyNodes()
    {
        $finallyAction = new \ezcWorkflowNodeFinally();
        $workflow = new \ezcWorkflow('Test', null, null, $finallyAction);

        $workflow->startNode->addOutNode($workflow->endNode);

        $this->assertWorkflowPersistance($workflow);
    }

    public function testSaveVariableHandlers()
    {
        $variableHandler = $this->getMock('ezcWorkflowVariableHandler');

        $workflow = new \ezcWorkflow('Test');
        $workflow->startNode->addOutNode($workflow->endNode);
        $workflow->addVariableHandler('foo', get_class($variableHandler));

        $this->assertWorkflowPersistance($workflow);
    }

    public function testWorkflowsAreNeverUpdated()
    {
        $workflow = new \ezcWorkflow('Test');
        $workflow->startNode->addOutNode($workflow->endNode);

        $def = new DefinitionStorage($this->conn, $this->options);
        $def->save($workflow);
        $workflowId1 = $workflow->id;
        $def->save($workflow);
        $workflowId2 = $workflow->id;

        $this->assertEquals($workflowId1 + 1, $workflowId2);
    }

    public function assertWorkflowPersistance(\ezcWorkflow $workflow)
    {
        $def = new DefinitionStorage($this->conn, $this->options);
        $def->save($workflow);

        $persistedWorkflow = $def->loadById($workflow->id);
        $this->assertEquals($workflow, $persistedWorkflow, "The persisted workflow has to be exactly equal to the orignal one after loading.");
    }
}

class MyPrintAction implements \ezcWorkflowServiceObject
{
    private $whatToSay;

    public function  __construct($whatToSay) {
        $this->whatToSay = $whatToSay;
    }

    public function __toString() {
        return 'myPrint';
    }
    public function execute(\ezcWorkflowExecution $execution) {
        echo $this->whatToSay."\n";
    }
}