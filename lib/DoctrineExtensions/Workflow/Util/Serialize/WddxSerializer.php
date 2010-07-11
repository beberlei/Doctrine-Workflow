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

class WddxSerializer implements Serializer
{
    public function __construct()
    {
        if (!extension_loaded('wddx')) {
            throw new \Exception("The XmlSerializer requires the PHP 'wddx' extension to be installed.");
        }
    }

    /**
     * @param array $value
     * @return string
     */
    public function serialize($value)
    {
        if ($value === null || (is_array($value) && count($value) == 0)) {
            return '';
        }

        return wddx_serialize_value($value);
    }

    /**
     * @param string $value
     * @return array
     */
    public function unserialize($value, $defaultValue = array())
    {
        if (!empty($value)) {
            return wddx_deserialize($value);
        }
        return $defaultValue;
    }
}