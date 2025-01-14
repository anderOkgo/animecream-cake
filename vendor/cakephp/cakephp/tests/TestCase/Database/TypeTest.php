<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Database;

use Cake\Database\Type;
use Cake\Database\Type\BoolType;
use Cake\Database\Type\IntegerType;
use Cake\Database\Type\UuidType;
use Cake\TestSuite\TestCase;
use PDO;
use TestApp\Database\Type\BarType;
use TestApp\Database\Type\FooType;

/**
 * Tests Type class
 */
class TypeTest extends TestCase
{

    /**
     * Original type map
     *
     * @var array
     */
    protected $_originalMap = [];

    /**
     * Backup original Type class state
     *
     * @return void
     */
    public function setUp()
    {
        $this->_originalMap = Type::map();
        parent::setUp();
    }

    /**
     * Restores Type class state
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();

        Type::map($this->_originalMap);
    }

    /**
     * Tests Type class is able to instantiate basic types
     *
     * @dataProvider basicTypesProvider
     * @return void
     */
    public function testBuildBasicTypes($name)
    {
        $type = Type::build($name);
        $this->assertInstanceOf('Cake\Database\Type', $type);
        $this->assertEquals($name, $type->getName());
        $this->assertEquals($name, $type->getBaseType());
    }

    /**
     * provides a basics type list to be used as data provided for a test
     *
     * @return void
     */
    public function basicTypesProvider()
    {
        return [
            ['string'],
            ['text'],
            ['smallinteger'],
            ['tinyinteger'],
            ['integer'],
            ['biginteger'],
        ];
    }

    /**
     * Tests trying to build an unknown type throws exception
     *
     * @return void
     */
    public function testBuildUnknownType()
    {
        $this->expectException(\InvalidArgumentException::class);
        Type::build('foo');
    }

    /**
     * Tests that once a type with a name is instantiated, the reference is kept
     * for future use
     *
     * @return void
     */
    public function testInstanceRecycling()
    {
        $type = Type::build('integer');
        $this->assertSame($type, Type::build('integer'));
    }

    /**
     * Tests new types can be registered and built
     *
     * @return void
     */
    public function testMapAndBuild()
    {
        $map = Type::map();
        $this->assertNotEmpty($map);
        $this->assertFalse(isset($map['foo']));

        $fooType = FooType::class;
        Type::map('foo', $fooType);
        $map = Type::map();
        $this->assertEquals($fooType, $map['foo']);
        $this->assertEquals($fooType, Type::map('foo'));

        $type = Type::build('foo');
        $this->assertInstanceOf($fooType, $type);
        $this->assertEquals('foo', $type->getName());
        $this->assertEquals('text', $type->getBaseType());

        Type::map('foo2', $fooType);
        $map = Type::map();
        $this->assertSame($fooType, $map['foo2']);
        $this->assertSame($fooType, Type::map('foo2'));

        $type = Type::build('foo2');
        $this->assertInstanceOf($fooType, $type);
    }

    /**
     * Tests overwriting type map works for building
     *
     * @return void
     */
    public function testReMapAndBuild()
    {
        $fooType = FooType::class;
        Type::map('foo', $fooType);
        $type = Type::build('foo');
        $this->assertInstanceOf($fooType, $type);

        $barType = BarType::class;
        Type::map('foo', $barType);
        $type = Type::build('foo');
        $this->assertInstanceOf($barType, $type);
    }

    /**
     * Tests new types can be registered and built as objects
     *
     * @return void
     */
    public function testMapAndBuildWithObjects()
    {
        $map = Type::map();
        Type::clear();

        $uuidType = new UuidType('uuid');
        Type::map('uuid', $uuidType);

        $this->assertSame($uuidType, Type::build('uuid'));
        Type::map($map);
    }

    /**
     * Tests clear function in conjunction with map
     *
     * @return void
     */
    public function testClear()
    {
        $map = Type::map();
        $this->assertNotEmpty($map);

        $type = Type::build('float');
        Type::clear();

        $this->assertEmpty(Type::map());
        Type::map($map);
        $newMap = Type::map();

        $this->assertEquals(array_keys($map), array_keys($newMap));
        $this->assertEquals($map['integer'], $newMap['integer']);
        $this->assertEquals($type, Type::build('float'));
    }

    /**
     * Tests bigintegers from database are converted correctly to PHP
     *
     * @return void
     */
    public function testBigintegerToPHP()
    {
        $this->skipIf(
            PHP_INT_SIZE === 4,
            'This test requires a php version compiled for 64 bits'
        );
        $type = Type::build('biginteger');
        $integer = time() * time();
        $driver = $this->getMockBuilder('\Cake\Database\Driver')->getMock();
        $this->assertSame($integer, $type->toPHP($integer, $driver));
        $this->assertSame($integer, $type->toPHP('' . $integer, $driver));
        $this->assertSame(3, $type->toPHP(3.57, $driver));
    }

    /**
     * Tests bigintegers from PHP are converted correctly to statement value
     *
     * @return void
     */
    public function testBigintegerToStatement()
    {
        $type = Type::build('biginteger');
        $integer = time() * time();
        $driver = $this->getMockBuilder('\Cake\Database\Driver')->getMock();
        $this->assertEquals(PDO::PARAM_INT, $type->toStatement($integer, $driver));
    }

    /**
     * Tests decimal from database are converted correctly to PHP
     *
     * @return void
     */
    public function testDecimalToPHP()
    {
        $type = Type::build('decimal');
        $driver = $this->getMockBuilder('\Cake\Database\Driver')->getMock();

        $this->assertSame(3.14159, $type->toPHP('3.14159', $driver));
        $this->assertSame(3.14159, $type->toPHP(3.14159, $driver));
        $this->assertSame(3.0, $type->toPHP(3, $driver));
        $this->assertSame(1.0, $type->toPHP(['3', '4'], $driver));
    }

    /**
     * Tests integers from PHP are converted correctly to statement value
     *
     * @return void
     */
    public function testDecimalToStatement()
    {
        $type = Type::build('decimal');
        $string = '12.55';
        $driver = $this->getMockBuilder('\Cake\Database\Driver')->getMock();
        $this->assertEquals(PDO::PARAM_STR, $type->toStatement($string, $driver));
    }

    /**
     * Test setting instances into the factory/registry.
     *
     * @return void
     */
    public function testSet()
    {
        $instance = $this->getMockBuilder('Cake\Database\Type')->getMock();
        Type::set('random', $instance);
        $this->assertSame($instance, Type::build('random'));
    }

    /**
     * @return void
     */
    public function testDebugInfo()
    {
        $type = new Type('foo');
        $result = $type->__debugInfo();
        $expected = [
            'name' => 'foo',
        ];
        $this->assertEquals($expected, $result);
    }
}
