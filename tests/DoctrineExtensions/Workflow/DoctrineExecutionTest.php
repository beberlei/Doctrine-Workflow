<?php
/**
 * Doctrine Workflow
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */

namespace DoctrineExtensions\Workflow;

class DoctrineExecutionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Connection
     */
    private $conn;
    private $options;

    /**
     * @var DefinitionStorage
     */
    private $manager;

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
        $this->manager = new WorkflowManager($this->conn, $this->options);
    }

    public function testStartToEndExecution()
    {
        $workflow = new \ezcWorkflow('Test');
        $workflow->startNode->addOutNode($workflow->endNode);

        $this->manager->save($workflow);

        $execution = $this->manager->createExecution($workflow);
        $execution->workflow = $workflow;
        $execution->setVariable('foo', 'bar');
        $execution->setVariable('bar', 'baz');
        $execution->start();

        $this->assertEquals(0, count($this->conn->fetchAll('SELECT * FROM '.$this->options->executionTable())));
        $this->assertEquals(0, count($this->conn->fetchAll('SELECT * FROM '.$this->options->executionStateTable())));
    }

    public function testStartSuspendResume()
    {
        $workflow = new \ezcWorkflow('Test');
        $input = new \ezcWorkflowNodeInput(array( 'choice' => new \ezcWorkflowConditionIsBool ));
        $workflow->startNode->addOutNode($input);
        $input->addOutNode($workflow->endNode);

        $this->manager->save($workflow);

        $execution = $this->manager->createExecution($workflow);
        $execution->workflow = $workflow;
        $executionId = $execution->start();

        $execution = $this->manager->loadExecution($executionId);
        $execution->resume(array('choice' => true));
    }
}