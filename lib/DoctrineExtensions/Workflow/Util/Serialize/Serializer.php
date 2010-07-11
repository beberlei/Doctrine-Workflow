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

namespace DoctrineExtensions\Workflow\Util\Serialize;

/**
 * Serializes Array Data from ezcWorkflow components into a database storage format.
 *
 * ezcWorkflow (DatabaseTieIn) by default only uses PHPs internal serialize/unserialize
 * methods. These have drawbacks however in human readability and when they should
 * be in such a format that one can understand them by reading the records in the database.
 * This interface allows to specify which serializing method to use.
 */
interface Serializer
{
    /**
     * @param array $value
     * @return string
     */
    public function serialize($value);

    /**
     * @param string $value
     * @return array
     */
    public function unserialize($value, $defaultValue = array());
}