<?php

namespace WpMVC\Container\Tests;

use PHPUnit\Framework\TestCase;
use WpMVC\Container\Container;
use WpMVC\Container\Exception\ContainerException;
use WpMVC\Container\Exception\NotFoundException;

class ContainerTest extends TestCase
{
    protected $container;

    protected function setUp(): void {
        $this->container = new Container();
    }

    public function test_it_can_be_instantiated() {
        $this->assertInstanceOf( Container::class, $this->container );
    }

    public function test_it_resolves_a_class_without_dependencies() {
        $instance = $this->container->get( ConcreteClass::class );
        $this->assertInstanceOf( ConcreteClass::class, $instance );
    }

    public function test_it_returns_same_instance_for_singleton() {
        $instance1 = $this->container->get( ConcreteClass::class );
        $instance2 = $this->container->get( ConcreteClass::class );

        $this->assertSame( $instance1, $instance2 );
    }

    public function test_make_returns_new_instance() {
        $instance1 = $this->container->make( ConcreteClass::class );
        $instance2 = $this->container->make( ConcreteClass::class );

        $this->assertInstanceOf( ConcreteClass::class, $instance1 );
        $this->assertInstanceOf( ConcreteClass::class, $instance2 );
        $this->assertNotSame( $instance1, $instance2 );
    }

    public function test_has_returns_true_for_existing_classes() {
        $this->assertTrue( $this->container->has( ConcreteClass::class ) );
    }

    public function test_has_returns_false_for_non_existent_classes() {
        // has() returns true if class_exists() is true.
        // It should return false for a nonsense string.
        $this->assertFalse( $this->container->has( 'NonExistentClassXYZ' ) );
    }

    public function test_it_resolves_dependencies_automatically() {
        $service = $this->container->get( ServiceWithDependency::class );

        $this->assertInstanceOf( ServiceWithDependency::class, $service );
        $this->assertInstanceOf( ConcreteClass::class, $service->dependency );
    }

    public function test_it_resolves_nested_dependencies() {
        $nested = $this->container->get( ServiceWithNestedDependency::class );

        $this->assertInstanceOf( ServiceWithNestedDependency::class, $nested );
        $this->assertInstanceOf( ServiceWithDependency::class, $nested->service );
        $this->assertInstanceOf( ConcreteClass::class, $nested->service->dependency );
    }

    public function test_it_throws_not_found_exception_for_non_existent_class() {
        $this->expectException( NotFoundException::class );
        $this->container->get( 'Some\Random\NonExistent\Class' );
    }

    public function test_it_throws_container_exception_for_circular_dependency() {
        $this->expectException( ContainerException::class );
        $this->expectExceptionMessage( 'Circular dependency detected' );

        $this->container->get( CircularA::class );
    }

    public function test_it_can_make_with_parameters_override() {
        $instance = $this->container->make(
            ClassWithParams::class,
            [
                'value'  => 'custom value',
                'number' => 42
            ]
        );

        $this->assertEquals( 'custom value', $instance->value );
        $this->assertEquals( 42, $instance->number );
    }

    public function test_call_method_injection() {
        $instance = new MethodInjectionClass();

        $result = $this->container->call(
            [$instance, 'method'],
            [
                'param' => 'test'
            ]
        );

        $this->assertEquals( 'test', $result['param'] );
        $this->assertInstanceOf( ConcreteClass::class, $result['dependency'] );
    }

    public function test_call_closure_injection() {
        $result = $this->container->call(
            function ( ConcreteClass $dep, $test ) {
                return ['dep' => $dep, 'test' => $test];
            },
            ['test' => 'worked']
        );

        $this->assertInstanceOf( ConcreteClass::class, $result['dep'] );
        $this->assertEquals( 'worked', $result['test'] );
    }

    public function test_set_manually_binds_instance() {
        $mock = new \stdClass();
        $this->container->set( 'custom_key', $mock );

        $this->assertTrue( $this->container->has( 'custom_key' ) );
        $this->assertSame( $mock, $this->container->get( 'custom_key' ) );
    }

    public function test_call_static_method_injection() {
        $result = $this->container->call(
            [MethodInjectionClass::class, 'static_method'],
            [
                'param' => 'static_test'
            ]
        );

        $this->assertEquals( 'static_test', $result['param'] );
        $this->assertInstanceOf( ConcreteClass::class, $result['dependency'] );
    }
}

// Fixtures

class ConcreteClass {}

class ServiceWithDependency {
    public $dependency;

    public function __construct( ConcreteClass $dependency ) {
        $this->dependency = $dependency;
    }
}

class ServiceWithNestedDependency {
    public $service;

    public function __construct( ServiceWithDependency $service ) {
        $this->service = $service;
    }
}

class CircularA {
    public function __construct( CircularB $b ) {
    }
}

class CircularB {
    public function __construct( CircularA $a ) {
    }
}

class ClassWithParams {
    public $value;

    public $number;

    public function __construct( $value, $number = 0 ) {
        $this->value  = $value;
        $this->number = $number;
    }
}

class MethodInjectionClass {
    public function method( ConcreteClass $dependency, $param ) {
        return ['dependency' => $dependency, 'param' => $param];
    }

    public static function static_method( ConcreteClass $dependency, $param ) {
        return ['dependency' => $dependency, 'param' => $param];
    }
}
