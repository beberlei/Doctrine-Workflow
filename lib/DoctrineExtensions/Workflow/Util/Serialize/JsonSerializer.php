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

class JsonSerializer implements Serializer
{
    public function serialize($value) {
        if ($value === null || (is_array($value) && count($value) == 0)) {
            return '';
        }

        return json_encode($value);
    }

    public function unserialize($value, $defaultValue = array())
    {
        if (!empty($value)) {
            return json_decode($value, true);
        }
        return $defaultValue;
    }
}