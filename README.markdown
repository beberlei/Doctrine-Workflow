# Doctrine 2 Persistence for ezcWorkflow

This Doctrine 2 Extension offers a persistence mechanism for workflows and workflow executions exactly like ezcWorkflowDatabaseTiein does for the ezcDatabase component.

* [Doctrine 2](http://www.doctrine-project.org)
* [Zeta Components Workflow](http://www.ezcomponents.org/docs/api/trunk/introduction_Workflow.html)

## Setup the Database Schema

you can setup the database schema for the Persistence using the `DoctrineExtensions\Workflow\SchemaBuilder`
class:

    use DoctrineExtensions\Workflow\WorkflowOptions;
    use DoctrineExtensions\Workflow\SchemaBuilder;

    $conn = \Doctrine\DBAL\DriverManager::getConnection($params);
    $options = new WorkflowOptions($prefix = 'test_');
    $schemaBuilder = new SchemaBuilder(conn);
    $schemaBuilder->dropWorkflowSchema($options);
    $schemaBuilder->createWorkflowSchema($options);

This way you can use it against all supported Doctrine 2 drivers.

## Save and Load Workflows

The API resembles the one of the ezcWorkflowDatabaseTiein, see [the tutorial for more information](http://www.ezcomponents.org/docs/api/trunk/introduction_WorkflowDatabaseTiein.html).

Saving a workflow:

    use DoctrineExtensions\Workflow\DefinitionStorage;
    use DoctrineExtensions\Workflow\WorkflowOptions;

    $options = new WorkflowOptions(...);
    $conn = \Doctrine\DBAL\DriverManager::getConnection(...);

    $def = new DefinitionStorage($conn, $options);
    $def->save($workflow);

Load a workflow by id:

    $workflow = $def->loadById($id);

### NodeFactory for Dependency Injection

By default Doctrine uses a the `DoctrineExtensions\Workflow\NodeFactory` instance to create
Node instances. By default each node class takes an array of configuration options as a parameter.
However there are often cases when you want to inject node classes that delegate work to other
more powerful services. You can extend the NodeFactory to support this:

    $myNodeFactory = new MyNodeFactory($myDependenyInjectionContainer);
    $options = new WorkflowOptions('', null, $myNodeFactory);

    $def = new DefinitionStorage($conn, $options);

## Executing Workflows

Start a workflow and retrieve the execution id when it gets supsened

    use DoctrineExtensions\Workflow\DoctrineExecution;

    $execution = new DoctrineExecution($this->conn, $this->storage);
    $execution->workflow = $workflow;
    $executionId = $execution->start();

Resumse an operation for a given Execution Id

    use DoctrineExtensions\Workflow\DoctrineExecution;

    $execution = new DoctrineExecution($this->conn, $this->storage, $executionId);
    $execution->resume(array('choice' => true));
