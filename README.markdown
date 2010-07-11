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

This way you can use `ezcWorkflow` reliably against all supported Doctrine 2 drivers. You
should make sure to (re-)use the same `WorkflowOptions` instance, because it defines
the table prefix to be used.

## Saving, Loading and Removing Workflows

The API resembles the one of the ezcWorkflowDatabaseTiein, see [the tutorial for more information](http://www.ezcomponents.org/docs/api/trunk/introduction_WorkflowDatabaseTiein.html).

Saving a new workflow is very simple, just pass the `ezcWorkflow` instance to the
`DefinitionStorage::save()` method:

    use DoctrineExtensions\Workflow\DefinitionStorage;
    use DoctrineExtensions\Workflow\WorkflowOptions;

    $options = new WorkflowOptions(...);
    $conn = \Doctrine\DBAL\DriverManager::getConnection(...);

    $def = new DefinitionStorage($conn, $options);
    $def->save($workflow);

You can access the saved workflows ID by accessing the `$workflow->id` property
after the save method was called.

> **BEWARE**
>
> When you save a workflow retrieved from the database the existing workflow
> is not updated but a completly new workflow is saved into the database.
> The reason for this is simple and powerful: Workflows can be so complex
> that changing the inner workings of one could easily break already
> existing execution cycles of this workfow.

You can load a workflow by querying for its Workflow Id:

    $workflow = $def->loadById($id);

Removing a workflow from the database is very simple also, you can do it by ID:

    $def->remove($workflow->id);

### Cleaning up unused Workflows

As described in the previous sections, workflows are never updated but
new rows are inserted into the database. Depending on the number of workflows
you may get into trouble with the number of rows in the workflow related tables.

There are methods that allow you to clean up unused workflows. A workflow
is unused, if its marked as outdated (i.e. not the current version of the workflow
as defined by the workflow-name + version unique key) and no execution still
works with that workflow.

    $definition = new DefinitionStorage($conn, $options);
    foreach ($definition->getUnusedWorkflowIds() AS $workflowId) {
        $definition->remove($workflowId);
    }

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

    use DoctrineExtensions\Workflow\DoctrineExecutionRepository;

    $repository = new DoctrineExecutionRepository($conn, $storage);
    $execution = $repository->createExecution($workflow);
    $executionId = $execution->start();

Resume an operation for a given Execution Id.

    use DoctrineExtensions\Workflow\DoctrineExecutionRepository;

    $repository = new DoctrineExecutionRepository($conn, $storage);
    $execution = $repository->loadExecution($executionId);
    $execution->resume(array('choice' => true));

### Batch-Jobs for Resuming Execution

Whenever Workflow Operations are suspended there are two options to resume
them:

1. By a user that knows the Workflow Id
2. By a batch-job

A batch job would naturally process ALL the supsended workflows, which
can obviously cause considerable performance issues.

That is why Doctrine Workflow listens to a special execution variable called
`batchWaitInterval`. This variable has to be an instance of 
[`DateInterval`](http://de.php.net/DateInterval). Whenever the execution
of a workflow is suspended and this variable exists, the interval
is applied against the current date and saved into the database.

You can then poll for the suspended executions:

    $repository = new DoctrineExecutionRepository($conn, $options);
    $exIds = $repository->pollSuspendedExecutionIds($limit = 50, $offset = 0);

    foreach ($exIds AS $executionId) {
        $execution = $repository->loadExecution($executionId);
        $execution->resume(array());
    }

This API is really only for batch jobs as its querying very broad for
the suspended execution ids across all workflows. If you need something
more specific or optimized you have to implement it yourself.

## Serialization of Arrays

Both the Workflow Definitions and Executions contain variables that are arrays.
Arrays are always ugly to serialize into the database and ezcWorkflows DatabaseTiein
does so by using PHP internal methods `serialize` and `unserialize`.

By default the Doctrine Workflow also uses this methods however it offers
an alternative using [WDDX](http://en.wikipedia.org/wiki/WDDX) and
the related [PHP Extension](http://php.net/manual/en/book.wddx.php). This
allows to save the data in a human-readable (and editable) format.

    use \DoctrineExtensions\Workflow\WorkflowOptions;
    use \DoctrineExtensions\Workflow\Util\Serializer\WddxSerializer;

    $serializer = new WddxSerializer();
    $options = new WorkflowOptions('prefix_', null, null, $serializer);

Don't bother to implement JSON as a serializer, it won't work since
objects have to be serialized and deserialized. JSON cannot handle this.

> **WARNING**
>
> You cannot easily change the serializer down the road unless you
> implement the `Serializer` interface with some kind of decorator
> that detects the serializing format before delegating to the
> real serializer.