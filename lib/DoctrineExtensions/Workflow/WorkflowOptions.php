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

use DoctrineExtensions\Workflow\Util\Serialize\Serializer;

class WorkflowOptions
{
    /**
     * @var string
     */
    private $prefix = '';

    /**
     * @var string
     */
    private $workflowClass = 'ezcWorkflow';

    /**
     * @var NodeFactory
     */
    private $workflowFactory = null;

    /**
     * @var Serializer
     */
    private $serializer = null;

    /**
     *
     * @param string $prefix
     * @param string $workflowClassName
     */
    public function __construct($prefix = '', $workflowClassName = 'ezcWorkflow', WorkflowFactory $workflowFactory = null, Serializer $serializer = null)
    {
        $this->prefix = $prefix;
        $this->workflowClass = ($workflowClassName) ?: 'ezcWorkflow';
        $this->workflowFactory = ($workflowFactory) ?: new WorkflowFactory();
        $this->serializer = ($serializer) ?: new Util\Serialize\ZetaSerializer();
    }

    public function getTablePrefix()
    {
        return $this->prefix;
    }

    public function workflowTable()
    {
        return $this->prefix . 'workflow';
    }

    public function nodeTable()
    {
        return $this->prefix . 'node';
    }

    public function nodeConnectionTable()
    {
        return $this->prefix . 'node_connection';
    }

    public function variableHandlerTable()
    {
        return $this->prefix . 'variable_handler';
    }

    public function executionTable()
    {
        return $this->prefix . 'execution';
    }

    public function executionStateTable()
    {
        return $this->prefix . 'execution_state';
    }

    public function workflowClassName()
    {
        return $this->workflowClass;
    }

    /**
     * @return WorkflowFactory
     */
    public function getWorkflowFactory()
    {
        return $this->workflowFactory;
    }

    /**
     * @return Serializer
     */
    public function getSerializer()
    {
        return $this->serializer;
    }
}