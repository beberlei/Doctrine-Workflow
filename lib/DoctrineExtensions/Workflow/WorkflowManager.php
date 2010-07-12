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

use Doctrine\DBAL\Connection;

class WorkflowManager implements IWorkflowManager
{
    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var WorkflowOptions
     */
    private $options = null;

    /**
     * @var DefinitionStorage
     */
    private $definitionStorage;

    /**
     * @param Connection $conn
     * @param DefinitionStorage $storage
     */
    public function __construct(Connection $conn, WorkflowOptions $options)
    {
        $this->conn = $conn;
        $this->options = $options;
        $this->definitionStorage = new DefinitionStorage($conn, $options);
    }

    /**
     * @return \ezcWorkflowDefinitionStorage
     */
    public function getDefinitionStorage()
    {
        return $this->definitionStorage;
    }

    /**
     * Load an execution given by a specifiy Id
     *
     * @param  int $executionId
     * @return DoctrineExecution
     */
    public function loadExecution($executionId)
    {
        return new DoctrineExecution($this->conn, $this->definitionStorage, $executionId);
    }

    /**
     * @param \ezcWorkflow $workflow
     * @return DoctrineExceution
     */
    public function createExecution(\ezcWorkflow $workflow)
    {
        $execution = new DoctrineExecution($this->conn, $this->definitionStorage);
        $execution->workflow = $workflow;
        return $execution;
    }

    /**
     * @param  int $workflowId
     * @return DoctrineExecution
     */
    public function createExecutionByWorkflowId($workflowId)
    {
        return $this->createExecution($this->definitionStorage->loadById($workflowId));
    }

    /**
     * Poll across the complete execution table to find suspended workflows ready for execution.
     * 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function pollSuspendedExecutionIds($limit = null, $offset = null)
    {
        $platform = $this->conn->getDatabasePlatform();

        $query = 'SELECT execution_id FROM ' . $this->options->executionTable() . ' ' .
                 'WHERE execution_next_poll_date < ?';
        if ($limit) {
            $query = $platform->modifyLimitQuery($query, $limit, $offset);
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, date_create('now')->format($platform->getDateTimeFormatString()));
        $stmt->execute();

        $executionIds = array();
        while ($executionId = $stmt->fetchColumn()) {
            $executionIds[] = $executionId;
        }
        return $executionIds;
    }

    public function deleteWorkflow($workflowId)
    {
        return $this->definitionStorage->delete($workflowId);
    }

    public function getUnusedWorkflowIds()
    {
        $sql = 'SELECT w.workflow_id FROM ' . $this->options->workflowTable() . ' w ' .
               'WHERE w.workflow_id NOT IN ( SELECT DISTINCT e.workflow_id FROM ' . $this->options->executionTable() . ') ' .
               ' AND w.workflow_outdated = 1';
        $stmt = $this->conn->query();

        $workflowIds = array();
        while ($workflowId = $stmt->fetchColumn()) {
            $workflowIds[] = $workflowId;
        }
        return $workflowIds;
    }

    public function loadWorkflowById($workflowId)
    {
        return $this->definitionStorage->loadById($workflowId);
    }

    public function save(\ezcWorkflow $workflow)
    {
        $this->definitionStorage->save($workflow);
    }
}