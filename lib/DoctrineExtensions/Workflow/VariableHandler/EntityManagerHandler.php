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

namespace DoctrineExtensions\Workflow\VariableHandler;

use Doctrine\ORM\EntityManager;
use ezcWorkflowExecution;

/**
 * Savely serialize details about entities into the DoctrineWorkflow tables.
 *
 * Doctrine 2 entities can easily contain large unserializable lazy-loading data. You can use
 * this VariableHandler to save only the entity name and id.
 *
 * For each Entity $variableName during suspending a $variableName."_dc2entity" is created as an
 * array of Entity Class and Entity Id. During resume of an execution these variables are checked
 * and the entities are fetched from the persistence context again.
 */
class EntityManagerHandler implements \ezcWorkflowVariableHandler
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Reconstruct an entity for the given variable name checking for details in a transformation.
     *
     * @param ezcWorkflowExecution $execution
     * @param string $variableName
     */
    public function load(ezcWorkflowExecution $execution, $variableName)
    {
        $entityDetailsVariable = $variableName . "_dc2entity";
        if ($execution->hasVariable($entityDetailsVariable)) {
            $entityData = $execution->getVariable($entityDetailsVariable);
            if (isset($entityData[0]) && isset($entityData[1])) {
                return $this->em->find($entityData[0], $entityData[1]);
            }
            $execution->setVariable($entityDetailsVariable, null);
        }
        return null;
    }

    /**
     * Savely persist Doctrine 2 Entity information
     *
     * @param ezcWorkflowExecution $execution
     * @param string $variableName
     * @param  $value
     */
    public function save(ezcWorkflowExecution $execution, $variableName, $value)
    {
        if (!is_object($value)) {
            return null;
        }

        if (!$this->em->contains($value)) {
            throw new \ezcWorkflowExecutionException("Entity '".get_class($value)."' at variable " . $variableName . " has to be managed by the EntityManager.");
        }

        $entityData = array(get_class($value), $this->em->getUnitOfWork()->getEntityIdentifier($value));
        $entityDetailsVariable = $variableName . "_dc2entity";
        $execution->setVariable($entityDetailsVariable, $entityData);
        $execution->setVariable($variableName, null);
    }
}