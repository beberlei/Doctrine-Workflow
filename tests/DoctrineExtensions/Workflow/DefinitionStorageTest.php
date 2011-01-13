<?php

namespace DoctrineExtensions\Workflow;

class DefinitionStorageTest extends \PHPUnit_Framework_TestCase
{
    private $conn;
    private $options;

    public function createSerializer()
    {
        if (isset($GLOBALS['DOCTRINE_WORKFLOW_SERIALIZER_IMPL'])) {
            return new $GLOBALS['DOCTRINE_WORKFLOW_SERIALIZER_IMPL']();
        }
        return null;
    }

    public function setUp()
    {
        $this->conn = \DoctrineExtensions\Workflow\TestHelper::getConnection();
        $this->options = new WorkflowOptions('test_', null, null, $this->createSerializer());
        TestHelper::createSchema($this->options);
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

        $workflow = new \ezcWorkflow('Test');
        $workflow->startNode->addOutNode($workflow->endNode);

        $variableHandler = $this->getMock('ezcWorkflowVariableHandler');
        $workflow->addVariableHandler('foo', get_class($variableHandler));

        $this->assertWorkflowPersistance($workflow);
    }

    public function assertWorkflowPersistance(\ezcWorkflow $workflow)
    {
        $manager = new WorkflowManager($this->conn, $this->options);
        $manager->save($workflow);

        $persistedWorkflow = $manager->loadWorkflowById($workflow->id);
        $this->assertEquals($workflow, $persistedWorkflow, "The persisted workflow has to be exactly equal to the orignal one after loading.");
    }

    public function testWorkflowIdentityMap()
    {
        $this->markTestSkipped('No Identity Map anymore, workflows have state that i dont fully grasp yet.');

        $workflow = new \ezcWorkflow('IdentityTest');
        $workflow->startNode->addOutNode($workflow->endNode);
        
        $manager = new WorkflowManager($this->conn, $this->options);
        $manager->save($workflow);

        $this->assertSame($workflow, $manager->loadWorkflowById($workflow->id));
        $this->assertSame($manager->loadWorkflowById($workflow->id), $manager->loadWorkflowById($workflow->id));
    }

    public function testDeleteWorkflow()
    {
        $variableHandler = $this->getMock('ezcWorkflowVariableHandler');
        $workflow = new \ezcWorkflow('IdentityTest');
        $workflow->startNode->addOutNode($workflow->endNode);
        $workflow->addVariableHandler('foo', get_class($variableHandler));

        $manager = new WorkflowManager($this->conn, $this->options);
        $manager->save($workflow);

        $manager->deleteWorkflow($workflow->id);

        $this->setExpectedException('ezcWorkflowDefinitionStorageException', 'Could not load workflow definition.');
        $manager->loadWorkflowById($workflow->id);
    }

    public function testUpdateWorkflowWithNoChangesKeepsWorkflowId()
    {
        $workflow = new \ezcWorkflow('UpdateTest');
        $workflow->startNode->addOutNode($workflow->endNode);

        $manager = new WorkflowManager($this->conn, $this->options);
        $manager->save($workflow);

        $workflowId = $workflow->id;

        $manager->save($workflow);

        $this->assertEquals($workflowId, $workflow->id);
    }

    public function testUpdateWorkflowWithOneNewNode()
    {
        $workflow = new \ezcWorkflow('UpdateTest2');
        $workflow->startNode->addOutNode($workflow->endNode);

        $manager = new WorkflowManager($this->conn, $this->options);
        $manager->save($workflow);

        $workflowId = $workflow->id;

        // add new node
        $printAction1 = new \ezcWorkflowNodeAction(array('class' => 'DoctrinExtensions\Workflow\MyPrintAction', 'arguments' => array('Foo')));
        $workflow->startNode->removeOutNode($workflow->endNode);
        $workflow->startNode->addOutNode($printAction1);
        $printAction1->addOutNode($workflow->endNode);

        // add variable handler
        $variableHandler = $this->getMock('ezcWorkflowVariableHandler');
        $workflow->addVariableHandler('foo', get_class($variableHandler));

        $manager->save($workflow);
        $this->assertEquals($workflowId, $workflow->id);

        $loadedWorkflow = $manager->loadWorkflowById($workflow->id);

        $startOutNodes = $loadedWorkflow->startNode->getOutNodes();
        $this->assertInstanceOf('ezcWorkflowNodeAction', $startOutNodes[0]);

        $actionOutNodes = $startOutNodes[0]->getOutNodes();
        $this->assertInstanceOf('ezcWorkflowNodeEnd', $actionOutNodes[0]);

        $this->assertEquals(array('foo' => get_class($variableHandler)), $workflow->getVariableHandlers());
    }

    public function testUpdateWorkflowWithOneNewNodeVariableHandler()
    {
        $workflow = new \ezcWorkflow('UpdateTest3');
        $workflow->startNode->addOutNode($workflow->endNode);

        $manager = new WorkflowManager($this->conn, $this->options);
        $manager->save($workflow);

        $workflowId = $workflow->id;

        // add variable handler
        $variableHandler = $this->getMock('ezcWorkflowVariableHandler');
        $workflow->addVariableHandler('foo', get_class($variableHandler));

        $manager->save($workflow);
        $this->assertEquals($workflowId, $workflow->id);

        $this->assertEquals(array('foo' => get_class($variableHandler)), $workflow->getVariableHandlers());
    }

    public function testUpdateWorkflowNodeConfiguration()
    {
        $workflow = new \ezcWorkflow('UpdateTest4');

        $printAction1 = new \ezcWorkflowNodeAction(array('class' => 'DoctrinExtensions\Workflow\MyPrintAction', 'arguments' => array('Foo')));
        $workflow->startNode->addOutNode($printAction1);
        $printAction1->addOutNode($workflow->endNode);

        $manager = new WorkflowManager($this->conn, $this->options);
        $manager->save($workflow);

        $workflowId = $workflow->id;

        $reflField = new \ReflectionProperty('ezcWorkflowNodeAction', 'configuration');
        $reflField->setAccessible(true);

        $this->assertEquals(
            array('class' => 'DoctrinExtensions\Workflow\MyPrintAction', 'arguments' => array('Foo')),
            $reflField->getValue($printAction1)
        );

        $reflField->setValue($printAction1, array('class' => 'DoctrinExtensions\Workflow\MyPrintAction', 'arguments' => array('bar')));

        $manager->save($workflow);

        $this->assertEquals($workflowId, $workflow->id);

        $loadedWorkflow = $manager->loadWorkflowById($workflow->id);

        $startOutNodes = $loadedWorkflow->startNode->getOutNodes();
        $this->assertInstanceOf('ezcWorkflowNodeAction', $startOutNodes[0]);
        $printAction1 = $startOutNodes[0];

        $this->assertEquals(
            array('class' => 'DoctrinExtensions\Workflow\MyPrintAction', 'arguments' => array('bar')),
            $reflField->getValue($printAction1)
        );
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