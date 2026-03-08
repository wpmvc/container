<?php
/**
 * ContainerRobustnessTest class.
 *
 * @package WpMVC\Container\Tests\Integration
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WpMVC\Container\Container;
use WpMVC\Container\Exception\ContainerException;
use WpMVC\Container\Tests\Fixtures\Models\ConcreteClass;
use WpMVC\Container\Tests\Fixtures\Models\InvokableClass;
use WpMVC\Container\Tests\Fixtures\Models\VariadicClass;
use WpMVC\Container\Tests\Fixtures\Models\VisibilityClass;

/**
 * Class ContainerRobustnessTest
 *
 * Verifies the container's resilience against edge cases, including visibility constraints,
 * variadic expansion, invokable objects, and state management.
 *
 * @package WpMVC\Container\Tests\Integration
 */
class ContainerRobustnessTest extends TestCase
{
    protected $container;

    protected function setUp(): void {
        $this->container = new Container();
    }

    /**
     * Verifies support for object bindings (injecting an already instantiated object).
     */
    public function test_it_supports_object_bindings() {
        $object      = new \stdClass();
        $object->val = 'pre-built';

        $this->container->singleton( 'service', $object );

        $this->assertSame( $object, $this->container->get( 'service' ) );
        $this->assertEquals( 'pre-built', $this->container->get( 'service' )->val );
    }

    /**
     * Verifies handling of variadic parameters in class constructors.
     */
    public function test_it_handles_variadic_constructor_params() {
        // Empty variadics
        $instance = $this->container->make( VariadicClass::class );
        $this->assertEmpty( $instance->args );

        // Passed positional params
        $instance2 = $this->container->make( VariadicClass::class, ['a', 'b', 'c'] );
        $this->assertCount( 3, $instance2->args );
        $this->assertEquals( ['a', 'b', 'c'], $instance2->args );
    }

    /**
     * Verifies that the container can call invokable objects.
     */
    public function test_it_resolves_invokable_objects() {
        $invokable = new InvokableClass();
        $result    = $this->container->call( $invokable, ['param' => 'hello'] );

        $this->assertInstanceOf( ConcreteClass::class, $result['concrete'] );
        $this->assertEquals( 'hello', $result['param'] );
    }

    /**
     * Verifies detection of alias resolution loops (A -> B -> A).
     */
    public function test_it_detects_alias_loops() {
        $this->container->alias( 'serviceB', 'serviceA' );
        $this->container->alias( 'serviceA', 'serviceB' );

        $this->expectException( ContainerException::class );
        $this->expectExceptionMessage( 'Circular alias resolution' );
        
        $this->container->get( 'serviceA' );
    }

    /**
     * Verifies that instances can be cleared from the container.
     */
    public function test_it_can_forget_instances() {
        $this->container->singleton( 's1', ConcreteClass::class );
        $obj1 = $this->container->get( 's1' );

        $this->container->forget_instances();

        $obj2 = $this->container->get( 's1' );
        $this->assertNotSame( $obj1, $obj2, "After forgetting instances, singleton should be re-instantiated." );
    }

    /**
     * Verifies the ability to flush the entire container registry and instance cache.
     */
    public function test_it_can_flush_everything() {
        $this->container->singleton( 's1', ConcreteClass::class );
        $this->container->alias( 's1', 'alias1' );
        
        $this->container->flush();

        $this->assertFalse( $this->container->has( 's1' ) );
        $this->assertFalse( $this->container->has( 'alias1' ) );
    }

    /**
     * Verifies that optional/nullable parameters resolve to null when dependencies are missing.
     */
    public function test_it_prefers_default_values_over_null_for_nullable_params() {
        $result = $this->container->call(
            function( ?NonExistentInterfaceForTest $dep = null ) {
                return $dep === null ? 'is-null' : 'is-not-null';
            }
        );

        $this->assertEquals( 'is-null', $result );
    }

    /**
     * Verifies that the container strictly respects method visibility (public vs private).
     */
    public function test_it_strictly_respects_method_visibility() {
        $instance = new VisibilityClass();

        // Public should work
        $this->assertEquals( 'public', $this->container->call( [$instance, 'public_method'] ) );

        // Private should fail
        $this->expectException( ContainerException::class );
        $this->expectExceptionMessage( 'is not public' );
        $this->container->call( [$instance, 'private_method'] );
    }

    /**
     * Verifies support for static method resolution.
     */
    public function test_it_resolves_static_methods() {
        $result = $this->container->call( 'WpMVC\Container\Tests\Fixtures\Models\MethodInjectionClass::static_method', ['param' => 'static'] );
        $this->assertEquals( 'static', $result['param'] );
        $this->assertInstanceOf( ConcreteClass::class, $result['dependency'] );
    }

    /**
     * Verifies support for primitive value bindings (strings, arrays, bools).
     */
    public function test_it_supports_primitive_value_bindings() {
        $this->container->singleton( 'api_key', 'secret-123' );
        $this->container->bind( 'config_array', ['db' => 'localhost'] );
        $this->container->singleton( 'is_enabled', true );

        $this->assertEquals( 'secret-123', $this->container->get( 'api_key' ) );
        $this->assertEquals( ['db' => 'localhost'], $this->container->get( 'config_array' ) );
        $this->assertTrue( $this->container->get( 'is_enabled' ) );
    }
}

// Internal Stub for Nullable Check
interface NonExistentInterfaceForTest {}
