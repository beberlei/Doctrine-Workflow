<?php

namespace DoctrineExtensions\Workflow;

class EzcDefinitionTest extends \ezcWorkflowTestCase
{
    private $conn;
    private $options;
    private $dbStorage;

    public function setUp()
    {
        parent::setUp();

        $this->conn = \DoctrineExtensions\TestHelper::getConnection();
        $this->options = new WorkflowOptions('test_');
        $schemaBuilder = new SchemaBuilder($this->conn);
        try {
            $schemaBuilder->dropWorkflowSchema($this->options);
        } catch(\PDOException $e) {

        }
        $schemaBuilder->createWorkflowSchema($this->options);

        $this->dbStorage = new DefinitionStorage($this->conn, $this->options);
    }

    /**
     * @dataProvider workflowNameProvider
     */
    public function testSaveAndLoadWorkflow( $workflowName )
    {
        $xmlWorkflow = $this->xmlStorage->loadByName( $workflowName );

        $this->dbStorage->save( $xmlWorkflow );
        $dbWorkflow = $this->dbStorage->loadByName( $workflowName );

        $this->assertEquals( $xmlWorkflow, $dbWorkflow );
    }

    public function testExceptionWhenLoadingNotExistingWorkflow()
    {
        try {
            $this->dbStorage->loadById( 1 );
        } catch ( \ezcWorkflowDefinitionStorageException $e ) {
            $this->assertEquals( 'Could not load workflow definition.', $e->getMessage() );
            return;
        }

        $this->fail( 'Expected an ezcWorkflowDefinitionStorageException to be thrown.' );
    }

    public function testExceptionWhenLoadingNotExistingWorkflow2()
    {
        try {
            $this->dbStorage->loadByName( 'NotExisting' );
        } catch ( \ezcWorkflowDefinitionStorageException $e ) {
            $this->assertEquals( 'Could not load workflow definition.', $e->getMessage() );
            return;
        }

        $this->fail( 'Expected an ezcWorkflowDefinitionStorageException to be thrown.' );
    }

    public function testExceptionWhenLoadingNotExistingWorkflowVersion()
    {
        $this->setUpStartEnd();
        $this->dbStorage->save( $this->workflow );

        try {
            $workflow = $this->dbStorage->loadByName( 'StartEnd', 2 );
        } catch ( \ezcWorkflowDefinitionStorageException $e ) {
            $this->assertEquals( 'Could not load workflow definition.', $e->getMessage() );
            return;
        }

        $this->fail( 'Expected an ezcWorkflowDefinitionStorageException to be thrown.' );
    }
}
