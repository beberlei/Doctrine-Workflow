<?php

namespace DoctrineExtensions\Workflow;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;

class SchemaBuilder
{
    private $conn = null;

    public function __construct(Connection $connection)
    {
        $this->conn = $connection;
    }

    public function createWorkflowSchema(WorkflowOptions $options)
    {
        $sqlCol = new \Doctrine\DBAL\Schema\Visitor\CreateSchemaSqlCollector($this->conn->getDatabasePlatform());
        $schema = $this->getWorkflowSchema($options);
        $schema->visit($sqlCol);

        foreach ($sqlCol->getQueries() AS $query) {
            $this->conn->exec($query);
        }
    }

    public function dropWorkflowSchema(WorkflowOptions $options)
    {
        $sqlCol = new \Doctrine\DBAL\Schema\Visitor\DropSchemaSqlCollector($this->conn->getDatabasePlatform());
        $schema = $this->getWorkflowSchema($options);
        $schema->visit($sqlCol);

        foreach ($sqlCol->getQueries() AS $query) {
            $this->conn->exec($query);
        }
    }

    public function getWorkflowSchema(WorkflowOptions $options)
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $workflowTable = $schema->createTable($options->workflowTable());
        if ($this->conn->getDatabasePlatform()->prefersIdentityColumns()) {
            $workflowTable->setIdGeneratorType(Table::ID_IDENTITY);
        }
        $workflowTable->addColumn('workflow_id', 'integer');
        $workflowTable->addColumn('workflow_name', 'string');
        $workflowTable->addColumn('workflow_version', 'integer');
        $workflowTable->addColumn('workflow_outdated', 'integer');
        $workflowTable->addColumn('workflow_created', 'datetime');
        $workflowTable->setPrimaryKey(array('workflow_id'));
        $workflowTable->addUniqueIndex(array('workflow_name', 'workflow_version'));

        $nodeTable = $schema->createTable($options->nodeTable());
        if ($this->conn->getDatabasePlatform()->prefersIdentityColumns()) {
            $nodeTable->setIdGeneratorType(Table::ID_IDENTITY);
        }
        $nodeTable->addColumn('node_id', 'integer');
        $nodeTable->addColumn('workflow_id', 'integer');
        $nodeTable->addColumn('node_class', 'string');
        $nodeTable->addColumn('node_configuration', 'text', array('notnull' => false));
        $nodeTable->setPrimaryKey(array('node_id'));
        $nodeTable->addIndex(array('workflow_id'));
        $nodeTable->addForeignKeyConstraint($options->workflowTable(), array('workflow_id'), array('workflow_id'));

        $connectionTable = $schema->createTable($options->nodeConnectionTable());
        if ($this->conn->getDatabasePlatform()->prefersIdentityColumns()) {
            $connectionTable->setIdGeneratorType(Table::ID_IDENTITY);
        }
        $connectionTable->addColumn('id', 'integer');
        $connectionTable->addColumn('incoming_node_id', 'integer');
        $connectionTable->addColumn('outgoing_node_id', 'integer');
        $connectionTable->setPrimaryKey(array('id'));
        $connectionTable->addForeignKeyConstraint($options->nodeTable(), array('incoming_node_id'), array('node_id'));
        $connectionTable->addForeignKeyConstraint($options->nodeTable(), array('outgoing_node_id'), array('node_id'));

        $variableHandlerTable = $schema->createTable($options->variableHandlerTable());
        $variableHandlerTable->addColumn('workflow_id', 'integer');
        $variableHandlerTable->addColumn('variable', 'string');
        $variableHandlerTable->addColumn('class', 'string');
        $variableHandlerTable->setPrimaryKey(array('workflow_id', 'variable'));

        $executionTable = $schema->createTable($options->executionTable());
        if ($this->conn->getDatabasePlatform()->prefersIdentityColumns()) {
            $executionTable->setIdGeneratorType(Table::ID_IDENTITY);
        }
        $executionTable->addColumn('execution_id', 'integer');
        $executionTable->addColumn('workflow_id', 'integer');
        $executionTable->addColumn('execution_parent', 'integer', array('notnull' => false));
        $executionTable->addColumn('execution_started', 'datetime');
        $executionTable->addColumn('execution_suspended', 'datetime', array('notnull' => false));
        $executionTable->addColumn('execution_variables', 'text', array('notnull' => false));
        $executionTable->addColumn('execution_waiting_for', 'text', array('notnull' => false));
        $executionTable->addColumn('execution_threads', 'text', array('notnull' => false));
        $executionTable->addColumn('execution_next_thread_id', 'integer');
        $executionTable->addColumn('execution_next_poll_date', 'datetime', array('notnull' => false));
        $executionTable->addColumn('execution_entity_name', 'string', array('notnull' => false));
        $executionTable->addColumn('execution_entity_id', 'integer', array('notnull' => false));

        $executionTable->setPrimaryKey(array('execution_id'));
        $executionTable->addIndex(array('execution_parent'));
        $executionTable->addForeignKeyConstraint($options->workflowTable(), array('workflow_id'), array('workflow_id'));
        $executionTable->addForeignKeyConstraint($options->executionTable(), array('execution_parent'), array('execution_id'));

        $executionStateTable = $schema->createTable($options->executionStateTable());
        $executionStateTable->addColumn('execution_id', 'integer');
        $executionStateTable->addColumn('node_id', 'integer');
        $executionStateTable->addColumn('node_state', 'text', array('notnull' => false));
        $executionStateTable->addColumn('node_activated_from', 'text', array('notnull' => false));
        $executionStateTable->addColumn('node_thread_id', 'integer');
        $executionStateTable->setPrimaryKey(array('execution_id', 'node_id'));
        $executionStateTable->addForeignKeyConstraint($options->executionTable(), array('execution_id'), array('execution_id'));
        $executionStateTable->addForeignKeyConstraint($options->nodeTable(), array('node_id'), array('node_id'));

        return $schema;
    }
}