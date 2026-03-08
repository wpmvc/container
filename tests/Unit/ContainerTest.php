<?php
/**
 * ContainerTest class (Unit).
 *
 * @package WpMVC\Container\Tests\Unit
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container\Tests;

use PHPUnit\Framework\TestCase;
use WpMVC\Container\Container;
use WpMVC\Container\Exception\ContainerException;
use WpMVC\Container\Exception\NotFoundException;
use WpMVC\Container\Tests\Fixtures\Contracts\TestInterface;
use WpMVC\Container\Tests\Fixtures\Models\ConcreteImplementation;
use WpMVC\Container\Tests\Fixtures\Models\ConcreteClass;
use WpMVC\Container\Tests\Fixtures\Models\ServiceWithDependency;
use WpMVC\Container\Tests\Fixtures\Models\ServiceWithNestedDependency;
use WpMVC\Container\Tests\Fixtures\Models\CircularA;
use WpMVC\Container\Tests\Fixtures\Models\ClassWithParams;
use WpMVC\Container\Tests\Fixtures\Models\ClassWithOptionalParam;
use WpMVC\Container\Tests\Fixtures\Models\MethodInjectionClass;

/**
 * Class ContainerTest
 *
 * Unit tests for the core Container class, verifying basic binding, resolution,
 * and dependency injection functionality in isolation.
 *
 * @package WpMVC\Container\Tests
 */
class ContainerTest extends TestCase
{
    protected $container;

    protected function setUp(): void {
        $this->container = new Container();
    }

    /**
     * Verifies that the container can be instantiated.
     */
    public function test_it_can_be_instantiated() {
        $this->assertInstanceOf( Container::class, $this->container );
    }

    /**
     * Verifies resolution of a class that has no dependencies.
     */
    public function test_it_resolves_a_class_without_dependencies() {
        $instance = $this->container->get( ConcreteClass::class );
        $this->assertInstanceOf( ConcreteClass::class, $instance );
    }

    /**
     * Verifies that the container returns a fresh instance by default for unregistered classes.
     */
    public function test_it_returns_fresh_instance_by_default() {
        $instance1 = $this->container->get( ConcreteClass::class );
        $instance2 = $this->container->get( ConcreteClass::class );

        $this->assertNotSame( $instance1, $instance2 );
    }

    /**
     * Verifies that singletons return the same instance across multiple resolutions.
     */
    public function test_it_returns_same_instance_for_singleton() {
        $this->container->singleton( ConcreteClass::class );
        
        $instance1 = $this->container->get( ConcreteClass::class );
        $instance2 = $this->container->get( ConcreteClass::class );

        $this->assertSame( $instance1, $instance2 );
    }

    /**
     * Verifies that make() always returns a new instance even for shared services.
     */
    public function test_make_returns_new_instance() {
        $instance1 = $this->container->make( ConcreteClass::class );
        $instance2 = $this->container->make( ConcreteClass::class );

        $this->assertInstanceOf( ConcreteClass::class, $instance1 );
        $this->assertInstanceOf( ConcreteClass::class, $instance2 );
        $this->assertNotSame( $instance1, $instance2 );
    }

    /**
     * Verifies that has() returns true for classes that exist.
     */
    public function test_has_returns_true_for_existing_classes() {
        $this->assertTrue( $this->container->has( ConcreteClass::class ) );
    }

    /**
     * Verifies automatic resolution of constructor dependencies.
     */
    public function test_it_resolves_dependencies_automatically() {
        $service = $this->container->get( ServiceWithDependency::class );

        $this->assertInstanceOf( ServiceWithDependency::class, $service );
        $this->assertInstanceOf( ConcreteClass::class, $service->dependency );
    }

    /**
     * Verifies automatic resolution of multi-level nested dependencies.
     */
    public function test_it_resolves_nested_dependencies() {
        $nested = $this->container->get( ServiceWithNestedDependency::class );

        $this->assertInstanceOf( ServiceWithNestedDependency::class, $nested );
        $this->assertInstanceOf( ServiceWithDependency::class, $nested->service );
        $this->assertInstanceOf( ConcreteClass::class, $nested->service->dependency );
    }

    /**
     * Verifies that requesting a non-existent class throws a NotFoundException.
     */
    public function test_it_throws_not_found_exception_for_non_existent_class() {
        $this->expectException( NotFoundException::class );
        $this->container->get( 'Some\Random\NonExistent\Class' );
    }

    /**
     * Verifies that circular dependencies are caught (basic autowiring case).
     */
    public function test_it_throws_container_exception_for_circular_dependency() {
        $this->expectException( ContainerException::class );
        $this->expectExceptionMessage( 'Circular dependency detected' );

        $this->container->get( CircularA::class );
    }

    /**
     * Verifies that make() can override default parameters.
     */
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

    /**
     * Verifies that passing parameters to an already instantiated shared service does not throw anymore.
     */
    public function test_it_ignores_parameters_for_existing_shared_service() {
        $this->container->singleton( ClassWithParams::class );
        
        $instance1 = $this->container->get( ClassWithParams::class, ['value' => 'first', 'number' => 1] );
        $instance2 = $this->container->get( ClassWithParams::class, ['value' => 'ignored', 'number' => 2] );

        $this->assertSame( $instance1, $instance2 );
        $this->assertEquals( 'first', $instance2->value );
    }

    /**
     * Verifies method injection via the call() method.
     */
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

    /**
     * Verifies closure injection via the call() method.
     */
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

    /**
     * Verifies interface to concrete mapping.
     */
    public function test_it_binds_interface_to_concrete() {
        $this->container->bind( TestInterface::class, ConcreteImplementation::class );
        $instance = $this->container->get( TestInterface::class );

        $this->assertInstanceOf( ConcreteImplementation::class, $instance );
    }

    /**
     * Verifies using a closure as a factory binding.
     */
    public function test_it_binds_closure_as_factory() {
        $this->container->bind(
            'config', function() {
                return ['db' => 'localhost'];
            }
        );

        $config = $this->container->get( 'config' );
        $this->assertEquals( 'localhost', $config['db'] );
    }

    /**
     * Verifies the use of positional parameters in call().
     */
    public function test_call_with_positional_parameters() {
        $result = $this->container->call(
            function ( ConcreteClass $dep, $test ) {
                return ['dep' => $dep, 'test' => $test];
            },
            [1 => 'positional'] // Index 1 is $test
        );

        $this->assertInstanceOf( ConcreteClass::class, $result['dep'] );
        $this->assertEquals( 'positional', $result['test'] );
    }

    /**
     * Verifies handling of nullable parameters in call().
     */
    public function test_it_handles_nullable_parameters() {
        $result = $this->container->call(
            function ( ?TestInterface $dep = null ) {
                return $dep;
            }
        );

        $this->assertNull( $result );
    }

    /**
     * Verifies resolution of optional constructor parameters.
     */
    public function test_it_resolves_optional_parameters() {
        $instance = $this->container->make( ClassWithOptionalParam::class );
        $this->assertEquals( 'default', $instance->value );
    }

    /**
     * Verifies that middleware-style calls work correctly (injecting stdClass and closure).
     */

    /**
     * Verifies that the container correctly resolves middleware-like calls (injecting stdClass and closure).
     */
    public function test_it_correctly_resolves_middleware_like_calls() {
        $req  = new \stdClass();
        $next = function() { return 'next'; };

        $result = $this->container->call(
            function( \stdClass $r, $n ) {
                return [$r, $n()];
            }, [$req, $next]
        );

        $this->assertSame( $req, $result[0] );
        $this->assertEquals( 'next', $result[1] );
    }

    /**
     * Verifies that duplicate tags are ignored.
     */
    public function test_it_prevents_duplicate_tags() {
        $this->container->tag( ConcreteClass::class, ['plugin', 'service'] );
        $this->container->tag( ConcreteClass::class, ['plugin'] ); // Duplicate

        $tagged = $this->container->tagged( 'plugin' );
        $count  = 0;
        foreach ( $tagged as $item ) {
            $count++;
        }

        $this->assertEquals( 1, $count );
    }

    /**
     * Verifies that calling an abstract method throws a ContainerException.
     */
    public function test_it_throws_exception_on_abstract_method_call() {
        $this->expectException( ContainerException::class );
        $this->expectExceptionMessage( 'Cannot call abstract method' );

        $this->container->call( [\Psr\Container\ContainerInterface::class, 'get'] );
    }
}
