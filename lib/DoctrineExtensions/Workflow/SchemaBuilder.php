<?php

namespace DoctrineExtensions\Workflow;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Schema;

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

        $queries = $sqlCol->getQueries();
        foreach ($queries AS $query) {
            try {
                $this->conn->exec($query);
            } catch(\Exception $e) {
                
            }
        }
    }

    public function getWorkflowSchema(WorkflowOptions $options)
    {
        $schema = new \Doctrine\DBAL\Schema\Schema();

        $workflowTable = $schema->createTable($options->workflowTable());
        $columnOptions = $this->_handlePrimaryKey($schema, $options->workflowTable(), $options->workflowSequence() );
        $workflowTable->addColumn('workflow_id', 'integer', $columnOptions);
        $workflowTable->addColumn('workflow_name', 'string');
        $workflowTable->addColumn('workflow_version', 'integer');
        $workflowTable->addColumn('workflow_outdated', 'integer');
        $workflowTable->addColumn('workflow_created', 'datetime');
        $workflowTable->setPrimaryKey(array('workflow_id'));
        $workflowTable->addUniqueIndex(array('workflow_name', 'workflow_version'));

        $nodeTable = $schema->createTable($options->nodeTable());
        $columnOptions = $this->_handlePrimaryKey($schema, $options->nodeTable(), $options->nodeSequence() );
        $nodeTable->addColumn('node_id', 'integer', $columnOptions);
        $nodeTable->addColumn('workflow_id', 'integer');
        $nodeTable->addColumn('node_class', 'string');
        $nodeTable->addColumn('node_configuration', 'text', array('notnull' => false, "length" => null));
        $nodeTable->setPrimaryKey(array('node_id'));
        $nodeTable->addIndex(array('workflow_id'));
        $nodeTable->addForeignKeyConstraint($options->workflowTable(), array('workflow_id'), array('workflow_id'), array('onDelete' => 'CASCADE'));

        $connectionTable = $schema->createTable($options->nodeConnectionTable());
        $columnOptions = $this->_handlePrimaryKey($schema, $options->nodeConnectionTable(), $options->nodeConnectionSequence() );
        $connectionTable->addColumn('id', 'integer', $columnOptions);
        $connectionTable->addColumn('incoming_node_id', 'integer');
        $connectionTable->addColumn('outgoing_node_id', 'integer');
        $connectionTable->setPrimaryKey(array('id'));
        $connectionTable->addForeignKeyConstraint($options->nodeTable(), array('incoming_node_id'), array('node_id'), array('onDelete' => 'CASCADE'));
        $connectionTable->addForeignKeyConstraint($options->nodeTable(), array('outgoing_node_id'), array('node_id'), array('onDelete' => 'CASCADE'));

        $variableHandlerTable = $schema->createTable($options->variableHandlerTable());
        $variableHandlerTable->addColumn('workflow_id', 'integer');
        $variableHandlerTable->addColumn('variable', 'string');
        $variableHandlerTable->addColumn('class', 'string');
        $variableHandlerTable->setPrimaryKey(array('workflow_id', 'variable'));
        $variableHandlerTable->addForeignKeyconstraint($options->workflowTable(), array('workflow_id'), array('workflow_id'));

        $executionTable = $schema->createTable($options->executionTable());
        $columnOptions = $this->_handlePrimaryKey($schema, $options->executionTable(), $options->executionSequence() );
        $executionTable->addColumn('execution_id', 'integer', $columnOptions);
        $executionTable->addColumn('workflow_id', 'integer');
        $executionTable->addColumn('execution_parent', 'integer', array('notnull' => false));
        $executionTable->addColumn('execution_started', 'datetime');
        $executionTable->addColumn('execution_suspended', 'datetime', array('notnull' => false));
        $executionTable->addColumn('execution_variables', 'text', array('notnull' => false, "length" => null));
        $executionTable->addColumn('execution_waiting_for', 'text', array('notnull' => false, "length" => null));
        $executionTable->addColumn('execution_threads', 'text', array('notnull' => false, "length" => null));
        $executionTable->addColumn('execution_next_thread_id', 'integer');
        $executionTable->addColumn('execution_next_poll_date', 'datetime', array('notnull' => false));
        $executionTable->addIndex(array('execution_next_poll_date'));

        $executionTable->setPrimaryKey(array('execution_id'));
        $executionTable->addIndex(array('execution_parent'));
        $executionTable->addForeignKeyConstraint($options->workflowTable(), array('workflow_id'), array('workflow_id'));
        $executionTable->addForeignKeyConstraint($options->executionTable(), array('execution_parent'), array('execution_id'));

        $executionStateTable = $schema->createTable($options->executionStateTable());
        $executionStateTable->addColumn('execution_id', 'integer');
        $executionStateTable->addColumn('node_id', 'integer');
        $executionStateTable->addColumn('node_state', 'text', array('notnull' => false, "length" => null));
        $executionStateTable->addColumn('node_activated_from', 'text', array('notnull' => false, "length" => null));
        $executionStateTable->addColumn('node_thread_id', 'integer');
        $executionStateTable->setPrimaryKey(array('execution_id', 'node_id'));
        $executionStateTable->addForeignKeyConstraint($options->executionTable(), array('execution_id'), array('execution_id'));
        $executionStateTable->addForeignKeyConstraint($options->nodeTable(), array('node_id'), array('node_id'));

        return $schema;
    }

    protected function _handlePrimaryKey(Schema $schema, $tableName, $sequenceName = null)
    {
        $columnOptions = array();
        if ($this->conn->getDatabasePlatform()->prefersIdentityColumns()) {
            $columnOptions = array('autoincrement' => true);
        } elseif ($this->conn->getDatabasePlatform( )->prefersSequences()) {
            $sequence = $schema->createSequence($sequenceName);
            // Doens't work because of the ordering used by Doctrine in dropping tables.
            //$columnOptions = array( 'default' => "nextval('" . $sequenceName . "')" );
        }
        return $columnOptions;
    }
}
