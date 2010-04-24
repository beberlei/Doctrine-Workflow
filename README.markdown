# Doctrine 2 Persistence for ezcWorkflow

This Doctrine 2 Extension offers a persistence mechanism for workflows and workflow executions exactly like ezcWorkflowDatabaseTiein does for the ezcDatabase component.

* [Doctrine 2](http://www.doctrine-project.org)
* [Zeta Components Workflow](http://www.ezcomponents.org/docs/api/trunk/introduction_Workflow.html)

## Setup the Database Schema

you can setup the database schema for the Persistence using the `DoctrineExtensions\Workflow\SchemaBuilder`
class:

    $conn = \Doctrine\DBAL\DriverManager::getConnection($params);
    $options = new WorkflowOptions($prefix = 'test_');
    $schemaBuilder = new SchemaBuilder(conn);
    $schemaBuilder->dropWorkflowSchema($options);
    $schemaBuilder->createWorkflowSchema($options);

This way you can use it against all supported Doctrine 2 drivers.

## Little API Guide:

The API resembles the one of the ezcWorkflowDatabaseTiein, see [the tutorial for more information](http://www.ezcomponents.org/docs/api/trunk/introduction_WorkflowDatabaseTiein.html).

Save a workflow:

    $def = new DefinitionStorage($this->conn, $this->options);
    $def->save($workflow);

Load a workflow by id:

    $workflow = $def->loadById($id);

Start a workflow and retrieve the execution id when it gets supsened

    $execution = new DoctrineExecution($this->conn, $this->storage);
    $execution->workflow = $workflow;
    $executionId = $execution->start();

Resumse an operation for a given Execution Id

    $execution = new DoctrineExecution($this->conn, $this->storage, $executionId);
    $execution->resume(array('choice' => true));
