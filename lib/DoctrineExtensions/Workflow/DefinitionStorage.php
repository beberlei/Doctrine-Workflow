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

class DefinitionStorage implements \ezcWorkflowDefinitionStorage
{
    /**
     * @var Doctrine\DBAL\Connection
     */
    private $conn;

    /**
     * @var WorkflowOptions
     */
    private $options;

    /**
     * @var array
     */
    private $identityMap = array();

    /**
     * @param Connection $conn
     * @param WorkflowOptions $options
     */
    public function __construct(Connection $conn, WorkflowOptions $options)
    {
        $this->conn = $conn;
        $this->options = $options;
    }

    /**
     * @return WorkflowOptions
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Load a workflow definition by name.
     *
     * @param  string  $workflowName
     * @param  int $workflowVersion
     * @return ezcWorkflow
     * @throws ezcWorkflowDefinitionStorageException
     */
    public function loadByName( $workflowName, $workflowVersion = 0 )
    {
        if ($workflowVersion == 0) {
            $workflowVersion = $this->getCurrentVersion($workflowName);
        }

        $sql = 'SELECT workflow_id FROM ' . $this->options->workflowTable() . ' WHERE workflow_name = ? AND workflow_version = ?';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $workflowName);
        $stmt->bindParam(2, $workflowVersion);
        $stmt->execute();

        $result = $stmt->fetchAll( \PDO::FETCH_ASSOC );

        if ( $result !== false && isset( $result[0] ) ) {
            $result[0] = array_change_key_case($result[0], \CASE_LOWER);
            $workflowId = $result[0]['workflow_id'];
        } else {
            throw new \ezcWorkflowDefinitionStorageException('Could not load workflow definition.');
        }

        return $this->loadWorkflow($workflowId, $workflowName, $workflowVersion);
    }

    /**
     * Load a workflow definition by ID.
     *
     * Providing the name of the workflow that is to be loaded as the
     * optional second parameter saves a database query.
     *
     * @param  int $workflowId
     * @return ezcWorkflow
     * @throws ezcWorkflowDefinitionStorageException
     * @throws ezcDbException
     */
    public function loadById( $workflowId )
    {
        $platform = $this->conn->getDatabasePlatform();

        $sql = "SELECT workflow_name, workflow_version FROM " . $this->options->workflowTable() . " WHERE workflow_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $workflowId);
        $stmt->execute();

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($result) == 1) {
            $result[0] = array_change_key_case($result[0], \CASE_LOWER);
            
            $workflowName = $result[0]['workflow_name'];
            $workflowVersion = $result[0]['workflow_version'];
        } else {
            throw new \ezcWorkflowDefinitionStorageException('Could not load workflow definition.');
        }

        return $this->loadWorkflow($workflowId, $workflowName, $workflowVersion);
    }

    /**
     *
     * @param  int $workflowId
     * @param  string $workflowName
     * @param  int $workflowVersion
     * @return \ezcWorkflow
     */
    protected function loadWorkflow($workflowId, $workflowName, $workflowVersion)
    {
        $workflowId = (int)$workflowId;
        if (isset($this->identityMap[$workflowId])) {
            return $this->identityMap[$workflowId];
        }

        $sql = "SELECT node_id, node_class, node_configuration FROM " . $this->options->nodeTable() . " WHERE workflow_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $workflowId);
        $stmt->execute();

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Create node objects.
        foreach ( $result as $node ) {
            $node = array_change_key_case($node, \CASE_LOWER);

            $configuration = $this->options->getSerializer()->unserialize($node['node_configuration'], null);

            if ( is_null( $configuration ) ) {
                $configuration = \ezcWorkflowUtil::getDefaultConfiguration( $node['node_class'] );
            }

            $nodes[$node['node_id']] = $this->options->getWorkflowFactory()->createNode($node['node_class'], $configuration);

            if ($nodes[$node['node_id']] instanceof \ezcWorkflowNodeFinally &&
                    !isset( $finallyNode ) ) {
                $finallyNode = $nodes[$node['node_id']];
            }

            else if ($nodes[$node['node_id']] instanceof \ezcWorkflowNodeEnd &&
                    !isset( $defaultEndNode ) ) {
                $defaultEndNode = $nodes[$node['node_id']];
            }

            else if ($nodes[$node['node_id']] instanceof \ezcWorkflowNodeStart &&
                    !isset( $startNode ) ) {
                $startNode = $nodes[$node['node_id']];
            }
        }

        if ( !isset( $startNode ) || !isset( $defaultEndNode ) ) {
            throw new \ezcWorkflowDefinitionStorageException(
            'Could not load workflow definition.'
            );
        }

        $sql = "SELECT nc.outgoing_node_id, nc.incoming_node_id FROM " . $this->options->nodeConnectionTable() ." nc ".
               "INNER JOIN " . $this->options->nodeTable() . " n ON n.node_id = nc.incoming_node_id WHERE n.workflow_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $workflowId);
        $stmt->execute();

        $connections = $stmt->fetchAll( \PDO::FETCH_ASSOC );

        foreach ( $connections as $connection ) {
            $connection = array_change_key_case($connection, \CASE_LOWER);
            $nodes[$connection['incoming_node_id']]->addOutNode($nodes[$connection['outgoing_node_id']]);
        }

        if ( !isset( $finallyNode ) || count( $finallyNode->getInNodes() ) > 0 ) {
            $finallyNode = null;
        }

        // Create workflow object and add the node objects to it.
        $workflowClassName = $this->options->workflowClassName();
        $workflow = new $workflowClassName( $workflowName, $startNode, $defaultEndNode, $finallyNode );
        $workflow->definitionStorage = $this;
        $workflow->id = (int)$workflowId;
        $workflow->version = (int)$workflowVersion;

        $this->identityMap[$workflow->id] = $workflow;

        $sql = "SELECT variable, class FROM " . $this->options->variableHandlerTable() . " WHERE workflow_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $workflowId);
        $stmt->execute();

        $result = $stmt->fetchAll( \PDO::FETCH_ASSOC );
        $nodes  = array();

        if ( $result !== false )
        {
            foreach ( $result as $variableHandler )
            {
                $workflow->addVariableHandler(
                  $variableHandler['variable'],
                  $variableHandler['class']
                );
            }
        }

        // Verify the loaded workflow.
        $workflow->verify();

        return $workflow;
    }

    /**
     * Save a workflow definition to the database.
     *
     * @param  ezcWorkflow $workflow
     * @throws ezcWorkflowDefinitionStorageException
     */
    public function save( \ezcWorkflow $workflow )
    {
        // Verify the workflow.
        $workflow->verify();

        if (strlen($workflow->name) == 0) {
            throw new \ezcWorkflowDefinitionStorageException();
        }

        $platform = $this->conn->getDatabasePlatform();

        try {
            $this->conn->beginTransaction();
            
            $workflowVersion = $this->getCurrentVersion($workflow->name) + 1;

            $this->conn->update(
                $this->options->workflowTable(),
                array('workflow_outdated' => 1),
                array('workflow_name' => $workflow->name)
            );

            $date = new \DateTime("now");
            $this->conn->insert($this->options->workflowTable(), array(
                'workflow_name' => $workflow->name,
                'workflow_version' => $workflowVersion,
                'workflow_created' => $date->format($platform->getDateTimeFormatString()),
                'workflow_outdated' => 0,
            ));
            $workflow->id = (int)$this->conn->lastInsertId();
            $workflow->definitionStorage = $this;

            $this->identityMap[$workflow->id] = $workflow;

            // Write node table rows.
            $nodeMap = array();

            foreach ( $workflow->nodes as $node ) {
                /* @var $node \ezcWorkflowNode */

                $this->conn->insert($this->options->nodeTable(), array(
                    'workflow_id' => (int)$workflow->id,
                    'node_class' => get_class($node),
                    'node_configuration' => $this->options->getSerializer()->serialize( $node->getConfiguration() ),
                ));

                $nodeId = $this->conn->lastInsertId();
                $nodeMap[$nodeId] = $node;
            }

            foreach ($workflow->nodes AS $node) {
                foreach ( $node->getOutNodes() as $outNode ) {
                    $incomingNodeId = null;
                    $outgoingNodeId = null;

                    foreach ( $nodeMap as $_id => $_node ) {
                        if ( $_node === $node ) {
                            $incomingNodeId = $_id;
                        }

                        else if ( $_node === $outNode ) {
                            $outgoingNodeId = $_id;
                        }

                        if ( $incomingNodeId !== NULL && $outgoingNodeId !== NULL ) {
                            break;
                        }
                    }

                    $this->conn->insert($this->options->nodeConnectionTable(), array(
                        'incoming_node_id' => $incomingNodeId,
                        'outgoing_node_id' => $outgoingNodeId,
                    ));
                }
            }
            unset($nodeMap);

            foreach ($workflow->getVariableHandlers() AS $variable => $class) {
                $this->conn->insert($this->options->variableHandlerTable(), array(
                    'workflow_id' => (int)$workflow->id,
                    'variable' => $variable,
                    'class' => $class,
                ));
            }

            $this->conn->commit();
        } catch(\Exception $e) {
            $this->conn->rollBack();
            throw new \ezcWorkflowDefinitionStorageException("Error while persistint workflow: " . $e->getMessage());
        }
    }

    protected function getCurrentVersion($name)
    {
        $platform = $this->conn->getDatabasePlatform();

        $sql = "SELECT MAX(workflow_version) AS version FROM " . $this->options->workflowTable() . " ".
               "WHERE workflow_name = ? " . $platform->getForUpdateSQL();
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $name);
        $stmt->execute();

        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if ( $result !== false && isset( $result[0]['version'] ) && $result[0]['version'] !== null ) {
            $result[0] = array_change_key_case($result[0], \CASE_LOWER);
            return $result[0]['version'];
        } else {
            return 0;
        }
    }

    /**
     * Delete a workflow by its ID
     *
     * @param int $workflowId
     * @return void
     */
    public function delete($workflowId)
    {
        $this->conn->beginTransaction();
        try {
            // delete only those two, the rest should be deleted automatically through cascading foreign keys
            $this->conn->delete($this->options->variableHandlerTable(), array('workflow_id' => $workflowId));
            $this->conn->delete($this->options->workflowTable(), array('workflow_id' => $workflowId));

            $this->conn->commit();
        } catch(\Exception $e) {
            $this->conn->rollback();
        }
    }
}
