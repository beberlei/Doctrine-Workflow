<?php
/*
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

interface IWorkflowManager
{
    /**
     * @return \ezcWorkflowDefinitionStorage
     */
    public function getDefinitionStorage();

    /**
     * Load an execution given by a specifiy Id
     *
     * @param  int $executionId
     * @return \ezcWorkflowException
     */
    public function loadExecution($executionId);

    /**
     * @param \ezcWorkflow $workflow
     * @return DoctrineExceution
     */
    public function createExecution(\ezcWorkflow $workflow);

    /**
     * @param  int $workflowId
     * @return DoctrineExecution
     */
    public function createExecutionByWorkflowId($workflowId);

    /**
     * Poll across the complete execution table to find suspended workflows ready for execution.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function pollSuspendedExecutionIds($limit = null, $offset = null);

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
    public function loadWorkflowById( $workflowId );

    /**
     * Save a workflow definition to the database.
     *
     * @param  ezcWorkflow $workflow
     * @throws ezcWorkflowDefinitionStorageException
     */
    public function save( \ezcWorkflow $workflow );

    /**
     * Delete a workflow by its ID
     *
     * @param int $workflowId
     * @return void
     */
    public function deleteWorkflow($workflowId);

    /**
     * Get Ids of all Workflows that are not in use anymore in any execution and marked as outdated.
     *
     * @return array
     */
    public function getUnusedWorkflowIds();
}