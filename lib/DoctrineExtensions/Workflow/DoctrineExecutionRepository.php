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

class DoctrineExecutionRepository
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
    public function __construct(Connection $conn, DefinitionStorage $storage)
    {
        $this->conn = $conn;
        $this->definitionStorage = $storage;
        $this->options = $storage->getOptions();
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
        $execution = new DoctrineExceution($this->conn, $this->definitionStorage);
        $execution->workflow = $workflow;
        return $execution;
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
}