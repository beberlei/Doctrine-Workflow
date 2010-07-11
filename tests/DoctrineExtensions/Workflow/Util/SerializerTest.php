<?php

namespace DoctrineExtensions\Workflow\Util;
use DoctrineExtensions\Workflow\Util\Serialize\ZetaSerializer;
use DoctrineExtensions\Workflow\Util\Serialize\WddxSerializer;
use DoctrineExtensions\Workflow\Util\Serialize\JsonSerializer;

class SerializerTest extends \PHPUnit_Framework_TestCase
{
    public static function dataSerialize()
    {
        return array(
            array(null,                     array(),                    array()),
            array(null,                     null,                       null),
            array(array(),                  array(),                    array()),
            array(array(),                  null,                       null),
            array(array('foo' => 'bar'),    array('foo' => 'bar'),      array()),
            array(array('foo' => 'bar'),    array('foo' => 'bar'),      null),
            array(array('c' => "\xc9\x80"), array('c' => "\xc9\x80"),   null),
            // empty object instances exist in ezcWorkflow!!
            array(array('c' => new \stdClass()), array('c' => new \stdClass()), null),
        );
    }

    /**
     * @dataProvider dataSerialize
     */
    public function testZetaSerializer($value, $expectedValue, $defaultValue)
    {
        $z = new ZetaSerializer();
        $this->assertEquals($expectedValue, $z->unserialize($z->serialize($value), $defaultValue));
    }

    /**
     * @dataProvider dataSerialize
     */
    public function testWbbxSerializer($value, $expectedValue, $defaultValue)
    {
        if (!extension_loaded('wddx')) {
            $this->markTestSkipped('WDDX PHP Extension is required for this tests to run.');
        }

        $z = new WddxSerializer();
        $this->assertEquals($expectedValue, $z->unserialize($z->serialize($value), $defaultValue));
    }
}
