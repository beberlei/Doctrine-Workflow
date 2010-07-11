<?php

namespace DoctrineExtensions\Workflow;

class EzcExecutionTest extends \ezcWorkflowTestCase
{
    private $conn;
    private $options;
    private $dbStorage;

    public function setUp()
    {
        parent::setUp();

        $this->conn = \DoctrineExtensions\Workflow\TestHelper::getConnection();
        $this->options = new WorkflowOptions('test_');
        TestHelper::createSchema($this->options);

        $this->dbStorage = new DefinitionStorage($this->conn, $this->options);
    }

    public function testStartInputEnd()
    {
        $this->setUpStartInputEnd();
        $this->dbStorage->save( $this->workflow );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage);
        $execution->workflow = $this->workflow;

        $id = $execution->start();

        $this->assertNotNull( $id );
        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage, $id);

        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution->resume( array( 'variable' => 'value' ) );

        $this->assertTrue( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertFalse( $execution->isSuspended() );
    }

    public function testStartInputEndReset()
    {
        $this->setUpStartInputEnd();
        $this->dbStorage->save( $this->workflow );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage);
        $execution->workflow = $this->workflow;

        $id = $execution->start();

        $this->assertNotNull( $id );
        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage, $id);

        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution->resume( array( 'variable' => 'value' ) );

        $this->assertTrue( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertFalse( $execution->isSuspended() );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage);
        $execution->workflow = $this->workflow;
        $execution->workflow->reset();

        $id = $execution->start();

        $this->assertNotNull( $id );
        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage, $id);

        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution->resume( array( 'variable' => 'value' ) );

        $this->assertTrue( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertFalse( $execution->isSuspended() );
    }

    public function testParallelSplitSynchronization()
    {
        $this->setUpParallelSplitSynchronization2();
        $this->dbStorage->save( $this->workflow );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage);
        $execution->workflow = $this->workflow;

        $id = $execution->start();

        $this->assertNotNull( $id );
        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage, $id);

        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution->resume( array( 'foo' => 'bar' ) );

        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage, $id);

        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution->resume( array( 'bar' => 'foo' ) );

        $this->assertTrue( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertFalse( $execution->isSuspended() );
    }

    public function testNonInteractiveSubWorkflow()
    {
        $this->setUpStartEnd();
        $this->dbStorage->save( $this->workflow );

        $this->setUpWorkflowWithSubWorkflow( 'StartEnd' );
        $this->dbStorage->save( $this->workflow );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage);
        $execution->workflow = $this->workflow;

        $id = $execution->start();

        $this->assertNull( $id );
        $this->assertTrue( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertFalse( $execution->isSuspended() );
    }

    public function testInteractiveSubWorkflow()
    {
        $this->setUpStartInputEnd();
        $this->dbStorage->save( $this->workflow );

        $this->setUpWorkflowWithSubWorkflow( 'StartInputEnd' );
        $this->dbStorage->save( $this->workflow );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage);
        $execution->workflow = $this->workflow;

        $id = $execution->start();

        $this->assertNotNull( $id );
        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution = new DoctrineExecution($this->conn, $this->dbStorage, $id);

        $this->assertFalse( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertTrue( $execution->isSuspended() );

        $execution->resume( array( 'variable' => 'value' ) );

        $this->assertTrue( $execution->hasEnded() );
        $this->assertFalse( $execution->isCancelled() );
        $this->assertFalse( $execution->isResumed() );
        $this->assertFalse( $execution->isSuspended() );
    }

    public function testInvalidExecutionIdThrowsException()
    {
        try {
            $execution = new DoctrineExecution($this->conn, $this->dbStorage, '1');
        }
        catch ( \ezcWorkflowExecutionException $e ) {
            $this->assertEquals( 'Execution-Id has to be an integer (strictly-typed).', $e->getMessage() );
            return;
        }

        $this->fail( 'Expected an ezcWorkflowExecutionException to be thrown.' );
    }

    public function testNotExistingExecutionThrowsException()
    {
        try {
            $execution = new DoctrineExecution($this->conn, $this->dbStorage, 1);
        }
        catch ( \ezcWorkflowExecutionException $e ) {
            $this->assertEquals( 'Could not load execution state for ID 1', $e->getMessage() );
            return;
        }

        $this->fail( 'Expected an ezcWorkflowExecutionException to be thrown.' );
    }

    public function testPropertiesInvalidWorkflowInstance_ThrowsException()
    {
        $execution = new DoctrineExecution($this->conn, $this->dbStorage);

        try {
            $execution->workflow = new \StdClass;
        }
        catch ( \ezcBaseValueException $e ) {
            $this->assertEquals( 'The value \'O:8:"stdClass":0:{}\' that you were trying to assign to setting \'workflow\' is invalid. Allowed values are: ezcWorkflow.', $e->getMessage() );
            return;
        }

        $this->fail( 'Expected an ezcBaseValueException to be thrown.' );
    }

    public function testProperties3()
    {
        $execution = new DoctrineExecution($this->conn, $this->dbStorage);

        try {
            $foo = $execution->foo;
        }
        catch ( \ezcBasePropertyNotFoundException $e ) {
            $this->assertEquals( 'No such property name \'foo\'.', $e->getMessage() );
            return;
        }

        $this->fail( 'Expected an ezcBasePropertyNotFoundException to be thrown.' );
    }

    public function testProperties4()
    {
        $this->setUpStartEnd();

        $execution = new DoctrineExecution($this->conn, $this->dbStorage);

        try {
            $execution->foo = null;
        }
        catch ( \ezcBasePropertyNotFoundException $e ) {
            $this->assertEquals( 'No such property name \'foo\'.', $e->getMessage() );
            return;
        }

        $this->fail( 'Expected an ezcBasePropertyNotFoundException to be thrown.' );
    }
}
