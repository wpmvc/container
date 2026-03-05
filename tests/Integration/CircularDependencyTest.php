<?php
/**
 * CircularDependencyTest class.
 *
 * @package WpMVC\Container\Tests\Integration
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WpMVC\Container\Container;
use WpMVC\Container\Exception\CircularDependencyException;

/**
 * Class CircularDependencyTest
 *
 * Verifies that the container correctly detects and prevents infinite resolution loops
 * across various binding types (interfaces, aliases, and closures).
 *
 * @package WpMVC\Container\Tests\Integration
 */
class CircularDependencyTest extends TestCase
{
    /**
     * Verifies singleton integrity regardless of resolution order.
     */
    public function test_singleton_integrity_regardless_of_resolution_order() {
        $container = new Container();
        $container->singleton( StubConcrete::class );
        $container->singleton( StubInterface::class, StubConcrete::class );

        // Resolve concrete FIRST
        $instance1 = $container->get( StubConcrete::class );
        // Resolve abstract SECOND
        $instance2 = $container->get( StubInterface::class );

        $this->assertSame( $instance1, $instance2, "Concrete and Abstract must resolve to the SAME singleton instance." );
    }

    /**
     * Verifies that the CallbackInvoker can resolve aliased services.
     */
    public function test_callback_invoker_resolves_aliases() {
        $container = new Container();
        $container->singleton( StubConcrete::class );
        $container->alias( StubConcrete::class, 'my_api' );

        $result = $container->call( 'my_api::testMethod' );
        $this->assertEquals( 'ok', $result );
    }

    /**
     * Verifies that circular dependencies throw the specific CircularDependencyException.
     */
    public function test_circular_dependency_uses_specific_exception() {
        $container = new Container();
        
        $this->expectException( CircularDependencyException::class );
        $container->get( CircA::class );
    }

    /**
     * Verifies that setting a service with an alias synchronizes correctly with the real class.
     */
    public function test_set_with_alias_is_synchronized_with_real_class() {
        $container = new Container();
        $container->alias( StubConcrete::class, 'my_service' );
        
        $manual      = new StubConcrete();
        $manual->val = 'manual';
        
        $container->set( 'my_service', $manual );
        
        $resolved = $container->get( StubConcrete::class );
        $this->assertSame( $manual, $resolved );
        $this->assertEquals( 'manual', $resolved->val );
    }

    /**
     * Verifies that positional parameters do not collide with type hints during resolution.
     */
    public function test_positional_parameters_do_not_collide_with_type_hints() {
        $container = new Container();
        
        $instance = $container->make( TargetWithMixedParams::class, ['SomeName'] );
        
        $this->assertInstanceOf( StubConcrete::class, $instance->dep );
        $this->assertEquals( 'SomeName', $instance->name );
    }

    /**
     * Verifies that variadic parameters correctly collect remaining named arguments.
     */
    public function test_variadic_parameters_collect_remaining_named_args() {
        $container = new Container();
        $instance  = $container->make( VariadicTarget::class, ['first' => 1, 'second' => 2] );
        
        $this->assertEquals( [1, 2], array_values( $instance->args ) );
    }

    /**
     * Verifies that the reflection cache is isolated per container instance.
     */
    public function test_reflection_cache_is_isolated_per_container() {
        $c1 = new Container();
        $c2 = new Container();

        $this->assertInstanceOf( StubConcrete::class, $c1->get( StubConcrete::class ) );
        $this->assertInstanceOf( StubConcrete::class, $c2->get( StubConcrete::class ) );
    }
}

// Internal Stubs for Testing CircularDependencyTest
interface StubInterface {}
class StubConcrete implements StubInterface {
    public $val = 'real';

    public function testMethod() {
        return 'ok'; }
}
class CircA { public function __construct( CircB $b ) {} }
class CircB { public function __construct( CircA $a ) {} }
class TargetWithMixedParams {
    public $dep;

    public $name;

    public function __construct( StubConcrete $dep, $name ) {
        $this->dep  = $dep;
        $this->name = $name;
    }
}
class VariadicTarget {
    public $args;

    public function __construct( ...$args ) {
        $this->args = $args; }
}
