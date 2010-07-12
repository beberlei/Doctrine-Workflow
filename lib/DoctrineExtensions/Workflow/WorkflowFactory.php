<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */


namespace DoctrineExtensions\Workflow;

/**
 * Factory that creates the node, variable handler and service objects instances required by a specific workflow.
 *
 * You can hook into this class to add nodes that require certain dependencies,
 * for example database services, webservices or other services.
 */
class WorkflowFactory
{
    private $entityManager;

    public function __construct($entityManager = null)
    {
        $this->em = $entityManager;
    }

    /**
     * @param  string $className
     * @param  array $configuration
     * @return \ezcWorkflowNode
     */
    public function createNode($className, $configuration)
    {
        return new $className($configuration);
    }

    /**
     * @param  string $className
     * @return \ezcWorkflowVariableHandler
     */
    public function createVariableHandler($className)
    {
        if ($className == "DoctrineExtensions\Workflow\VariableHandler\EntityManagerHandler") {
            if (!($this->entityManager instanceof \Doctrine\ORM\EntityManager)) {
                throw new \ezcWorkflowException("EntityManagerHandler requires an EntityManager to be passed to the WorkflowFactory.");
            }

            return new $className($this->entityManager);
        } else {
            return new $className;
        }
    }
}