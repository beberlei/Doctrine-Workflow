# Doctrine 2 Persistence for ezcWorkflow

This Doctrine 2 Extension offers a persistence mechanism for workflows and workflow executions exactly like ezcWorkflowDatabaseTiein does for the ezcDatabase component.

* [Doctrine 2](http://www.doctrine-project.org)
* [Zeta Components Workflow](http://www.ezcomponents.org/docs/api/trunk/introduction_Workflow.html)

This extension uses the `Doctrine\DBAL` component and is not built on top of the ORM by default. In a later section this
document also describes how you can integrate Workflow with the Doctrine 2 ORM.

## Configuration

The Public API of Doctrine Workflow is implemented by the `DotrineExtensions\Workflow\WorkflowManager` class.
It accepts a `Doctrine\DBAL\Connection` and a `DoctrineExtensions\Workflow\WorkflowOptions` instance as
its constructor arguments:

    use DotrineExtensions\Workflow\WorkflowManager;

    $manager = new WorkflowManager($conn, $options);

The `WorkflowManager` implements an interface `IWorkflowManager` that can be used for decoupling
your domain model from the Doctrine based Workflow Engine for testability.

Doctrine Workflow is configured with a `WorkflowOptions` instance. It receives the following arguments
in the constructor:

    $options = new WorkflowOptions($prefix, $workflowClass, $nodeFactory, $serializer);

### $prefix - Table Prefix

Defines the prefix for the 6 required workflow persistence tables.

### $workflowClass

Defines the class workflows should be instantiated with, defaults to `ezcWorkflow`.

### $workflowFactory - Dependency Injection

By default Doctrine uses a the `DoctrineExtensions\Workflow\WorkflowFactory` instance to create
Node and VariableHandler instances. However there are often cases when you want to inject node classes that delegate work to other
more powerful services. You can extend the WorkflowFactory to support this:

    $myFactory = new MyWorkflowFactory($myDependenyInjectionContainer);
    $options = new WorkflowOptions('', null, $myFactory);

    $manager = new WorkflowManager($conn, $options);

### $serializer - Serialization of Arrays

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

### Setup the Database Schema

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

Saving a new workflow is very simple:

    use DoctrineExtensions\Workflow\WorkflowManager;
    use DoctrineExtensions\Workflow\WorkflowOptions;

    $options = new WorkflowOptions(...);
    $conn = \Doctrine\DBAL\DriverManager::getConnection(...);

    $manager = new WorkflowManager($conn, $options);
    $manager->save($workflow);

You can access the saved workflows ID by accessing the `$workflow->id` property
after the save method was called.

> **BEWARE**
>
> When you save a workflow retrieved from the database the existing workflow
> is not updated but a completely new workflow is saved into the database.
> The reason for this is simple and powerful: Workflows can be so complex
> that changing the inner workings of one could easily break already
> existing execution cycles of this workfow.

You can load a workflow by querying for its Workflow Id:

    $workflow = $manager->loadWorkflowById($id);

Removing a workflow from the database is very simple also, you can do it by ID:

    $manager->deleteWorkflow($workflow->id);

### Cleaning up unused Workflows

As described in the previous sections, workflows are never updated but
new rows are inserted into the database. Depending on the number of workflows
you may get into trouble with the number of rows in the workflow related tables.

There are methods that allow you to clean up unused workflows. A workflow
is unused, if its marked as outdated (i.e. not the current version of the workflow
as defined by the workflow-name + version unique key) and no execution still
works with that workflow.

    use DoctrineExtensions\Workflow\WorkflowManager;

    $manager = new WorkflowManager($conn, $options);
    foreach ($manager->getUnusedWorkflowIds() AS $workflowId) {
        $manager->deleteWorkflow($workflowId);
    }

## Executing Workflows

Start a workflow and retrieve the execution id when it gets suspended:

    use DoctrineExtensions\Workflow\WorkflowManager;

    $manager = new WorkflowManager($conn, $options);
    $execution = $manager->createExecution($workflow);
    // or
    $execution = $manager->createExecutionByWorkflowId($workflowId);

    $executionId = $execution->start();

Resume an operation for a given Execution Id.

    use DoctrineExtensions\Workflow\WorkflowManager;

    $manager = new WorkflowManager($conn, $options);
    $execution = $manager->loadExecution($executionId);
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

    $manager = new WorkflowManager($conn, $options);
    $exIds = $manager->pollSuspendedExecutionIds($limit = 50, $offset = 0);

    foreach ($exIds AS $executionId) {
        $execution = $manager->loadExecution($executionId);
        $execution->resume(array());
    }

This API is really only for batch jobs as its querying very broad for
the suspended execution ids across all workflows. If you need something
more specific or optimized you have to implement it yourself.

## Integrating Workflow with Doctrine 2 ORM

Using the Workflow Engine in isolation is a very academic endeavor, this
section describes how you integrate it into your application using
a Doctrine 2 domain model.

As an example I use the classic Content-Management-System Article Publishing
cycle. A workflow has to be processed before any article is published.

The details of workflow generation through a GUI are very complex. Therefore lets
say that our application defines 3 different workflows for article publishing
beforehand. Whenever a new Article is created, it directly gets assigned
a workflow based on some business logic (Depending on User, Category, Whatever).

Because Doctrine 2 Entities can contain very deep object-graphs (due to lazy-loading)
all the entity variables used have to be handled by the `EntityManagerHandler` Variable Handler.
A Variable Handler in `ezcWorkflow` is a serialize/unserialize transformation applied to a certain variable
upon suspend and resume operations.

In our case the workflow uses the `CmsArticle` variable as instance of the Article, so
we call:

    $workflow->addVariableHandler('CmsArticle', 'DoctrineExtensions\Workflow\VariableHandler\EntityManagerHandler');

One restriction exists with the EntityManagerHandler: You are only allowed to use entities that already have an ID assigned.

This handler needs access to the `EntityManager` that manages the CmsArticle instance. You can
configure that by passing it to the `WorkflowFactory` constructor:

    $em = EntityManager::create($params);
    $factory = new WorkflowFactory($em);
    $options = new WorkflowOptions($prefix, null, $factory);

    $workflowManager = new WorkflowManager($em->getConnection(), $options);

The author can then start the publishing workflow, `CmsArticle::startPublishingWorkflow()`
is called.

The CMS Article class looks like:

    /**
     * @Entity
     */
    class CmsArticle
    {
        /** @Id @GeneratedValue @Column(type="integer") */
        private $id;

        /** @Column(type="integer") */
        private $publishingWorkflowId = 1;

        /** @Column(type="integer", nullable=true) */
        private $publishingExecutionId = null;

        public function getPublishingWorkflowId()
        {
            return $this->publishingWorkflowId;
        }

        public function getPublishingExecutionId()
        {
            return $this->publishingExecutionId;
        }

        public function startPublishingWorkflow($executionId)
        {
            $this->publishingExecutionId = $executionId;
        }

        public function publishingWorkflowHasStarted()
        {
            return ($this->publishingExecutionId != null);
        }
    }

An intermediary CmsPublishingWorkflow class (domain object but not an entity) now controls the workflow:

    /**
     * Not an Entity
     */
    class CmsPublishingWorkflow
    {
        private $manager;

        public function __construct(IWorkflowManager $manager)
        {
            $this->manager = $manager;
        }

        public function startPublishingWorkflow(CmsArticle $article)
        {
            if ($article->publishingWorkflowHasStarted()) {
                throw new Exception("A workflow execution was already started!");
            }

            $this->execution = $this->manager->createExecutionByWorkflowId($article->getPublishingWorkflowId());
            $this->execution->setVariable('CmsArticle', $article);
            $article->startPublishingWorkflow($this->execution->start());
        }

        public function resumePublishingWorkflow(CmsArticle $article)
        {
            $this->execution = $this->manager->loadExecution($article->getPublishingExecutionId());
        }

        public function needsPublisherOk()
        {
            $variables = $this->execution->getWaitingFor();
            return isset($variables['publisherOk']);
        }
        // more logic!
    }

See how `$article` is passed as a variable to the execution context in `startPublishingWorkflow()`. This variable
is handled by the EntityManagerHandler as described above.

> **NOTICE**
>
> The `EntityManagerHandler` variable handler does *NOT* execute the flush-operation on the Entity Manager.
> When a workflow is suspended that potentially changed the used entities you have to call `EntityManager::flush()`
> yourself.

An example of how to use this now:

    $article = $em->find('CmsArticle', $articleId);
    // do stuff with the article
    $publishingWorkflow = new CmsPublishingWorkflow($manager);
    $publishingWorkflow->startPublishingWorkflow($article);