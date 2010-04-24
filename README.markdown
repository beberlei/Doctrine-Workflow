# Doctrine 2 Persistence for ezcWorkflow

This Doctrine 2 Extension offers a persistence mechanism for workflows and workflow executions exactly like ezcWorkflowDatabaseTiein does for the ezcDatabase component.

[Doctrine 2](http://www.doctrine-project.org)
[Zeta Components Workflow](http://www.ezcomponents.org/docs/api/trunk/introduction_Workflow.html)

## Little API Guide:

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
