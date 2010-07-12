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

class DoctrineExecution extends \ezcWorkflowExecution
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
     * @var bool
     */
    private $loaded = false;

    public function __construct(Connection $conn, DefinitionStorage $storage, $executionId = null)
    {
        $this->conn = $conn;
        $this->definitionStorage = $storage;
        $this->options = $storage->getOptions();

        if ($executionId !== null) {
            $this->loadExecution($executionId);
        }
    }

    protected function doStart($parentId)
    {
        $this->conn->beginTransaction();

        $platform = $this->conn->getDatabasePlatform();

        $variables = $this->variables;

        $executionNextPollDate = null;
        if (isset($variables['batchWaitInterval'])) {
            if (!($variables['batchWaitInterval'] instanceof \DateInterval)) {
                throw new \ezcWorkflowExecutionException("Specified batch waiting interval has to be instance of DateInterval!");
            }

            $executionNextPollDate = new \DateTime("now");
            $executionNextPollDate->add($variables['batchWaitInterval']);
            $executionNextPollDate = $executionNextPollDate->format($platform->getDateTimeFormatString());
        }

        $serializer = $this->options->getSerializer();

        $now = new \DateTime("now");
        $data = array(
            'workflow_id'               => (int)$this->workflow->id,
            'execution_parent'          => $parentId,
            'execution_started'         => $now->format($platform->getDateTimeFormatString()),
            'execution_variables'       => $serializer->serialize($variables),
            'execution_waiting_for'     => $serializer->serialize($this->waitingFor),
            'execution_threads'         => $serializer->serialize($this->threads),
            'execution_next_thread_id'  => (int)$this->nextThreadId,
            'execution_next_poll_date'  => $executionNextPollDate,
        );
        $this->conn->insert($this->options->executionTable(), $data);

        // execution_id
        $this->id = (int)$this->conn->lastInsertId();
    }

    protected function doResume()
    {
        $this->conn->beginTransaction();
    }

    protected function doEnd()
    {
        $this->cleanUpExecutionStateTable();
        $this->cleanUpExecutionTable();

        if ( !$this->isCancelled() ) {
            $this->conn->commit();
        } else {
            $this->conn->rollBack(); // ?
        }
    }

    protected function cleanUpExecutionTable()
    {
        $sql = 'DELETE FROM ' . $this->options->executionTable() . ' WHERE execution_id = ? OR execution_parent = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $this->id);
        $stmt->bindParam(2, $this->id);
        $stmt->execute();
    }

    protected function cleanUpExecutionStateTable()
    {
        $sql = 'DELETE FROM ' . $this->options->executionStateTable() . ' WHERE execution_id = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
    }

    protected function doGetSubExecution($id = null)
    {
        if (is_numeric($id)) {
            $id = (int)$id;
        }

        return new self( $this->conn, $this->definitionStorage, $id );
    }

    protected function doSuspend()
    {
        $platform = $this->conn->getDatabasePlatform();

        $variables = $this->variables;
        $executionNextPollDate = null;
        if (isset($variables['batchWaitInterval'])) {
            if (!($variables['batchWaitInterval'] instanceof \DateInterval)) {
                throw new \ezcWorkflowExecutionException("Specified batch waiting interval has to be instance of DateInterval!");
            }

            $executionNextPollDate = new \DateTime("now");
            $executionNextPollDate->add($variables['waitInterval']);
            $executionNextPollDate = $executionNextPollDate->format($platform->getDateTimeFormatString());
        }

        $serializer = $this->options->getSerializer();

        $now = new \DateTime("now");
        $data = array(
            'execution_suspended'       => $now->format($platform->getDateTimeFormatString()),
            'execution_variables'       => $serializer->serialize($variables),
            'execution_waiting_for'     => $serializer->serialize($this->waitingFor),
            'execution_threads'         => $serializer->serialize($this->threads),
            'execution_next_thread_id'  => (int)$this->nextThreadId,
            'execution_next_poll_date'  => $executionNextPollDate,
        );

        $this->cleanUpExecutionStateTable();
        $this->conn->update($this->options->executionTable(), $data, array('execution_id' => (int)$this->id));

        foreach ($this->activatedNodes AS $node) {
            $data = array(
                'execution_id'          => (int)$this->id,
                'node_id'               => (int)$node->getId(),
                'node_state'            => $serializer->serialize( $node->getState() ),
                'node_activated_from'   => $serializer->serialize( $node->getActivatedFrom() ),
                'node_thread_id'        => $node->getThreadId(),
            );
            $this->conn->insert($this->options->executionStateTable(), $data);
        }

        $this->conn->commit();
    }

    protected function loadExecution($executionId)
    {
        if (!is_int($executionId)) {
            throw new \ezcWorkflowExecutionException("Execution-Id has to be an integer (strictly-typed).");
        }

        $sql = 'SELECT * FROM ' . $this->options->executionTable() . ' WHERE execution_id = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $executionId);
        $stmt->execute();

        $result = $stmt->fetchAll( \PDO::FETCH_ASSOC );

        if ( $result === false || empty( $result ) ) {
            throw new \ezcWorkflowExecutionException('Could not load execution state for ID ' . ((int)$executionId));
        }

        $execution = array_change_key_case($result[0], \CASE_LOWER);

        $this->id = (int)$execution['execution_id'];
        $this->nextThreadId = $execution['execution_next_thread_id'];

        $serializer = $this->options->getSerializer();

        $this->variables    = $serializer->unserialize($execution['execution_variables']);
        $this->waitingFor   = $serializer->unserialize($execution['execution_waiting_for']);
        $this->threads      = $serializer->unserialize($execution['execution_threads']);

        $this->workflow = $this->definitionStorage->loadById($execution['workflow_id']);

        $sql = 'SELECT * FROM ' . $this->options->executionStateTable() . ' WHERE execution_id = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $executionId);
        $stmt->execute();

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $active = array();

        foreach ( $result as $row ) {
            $row = array_change_key_case($row, \CASE_LOWER);
            
            $active[$row['node_id']] = array(
                'activated_from'    => $serializer->unserialize($row['node_activated_from']),
                'state'             => $serializer->unserialize($row['node_state'], null),
                'thread_id'         => $row['node_thread_id'],
            );
        }

        foreach ( $this->workflow->nodes as $node ) {
            $nodeId = $node->getId();

            if ( isset( $active[$nodeId] ) ) {
                $node->setActivationState( \ezcWorkflowNode::WAITING_FOR_EXECUTION );
                $node->setThreadId( $active[$nodeId]['thread_id'] );
                $node->setState( $active[$nodeId]['state'], null );
                $node->setActivatedFrom( $active[$nodeId]['activated_from'] );

                $this->activate( $node, false );
            }
        }

        $this->cancelled = false;
        $this->ended     = false;
        $this->loaded    = true;
        $this->resumed   = false;
        $this->suspended = true;
    }

    /**
     * Loads data from variable handlers and
     * merge it with the current execution data.
     */
    protected function loadFromVariableHandlers()
    {
        foreach ($this->workflow->getVariableHandlers() as $variableName => $className) {
            $object = $this->options->getWorkflowFactory()->createVariableHandler($className);
            $this->setVariable($variableName, $object->load($this, $variableName));
        }
    }

    /**
     * Saves data to execution data handlers.
     */
    protected function saveToVariableHandlers()
    {
        foreach ($this->workflow->getVariableHandlers() as $variableName => $className) {
            if (isset($this->variables[$variableName])) {
                $object = $this->options->getWorkflowFactory()->createVariableHandler($className);
                $object->save($this, $variableName, $this->variables[$variableName]);
            }
        }
    }
}