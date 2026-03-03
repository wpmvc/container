<?php
/**
 * RecursiveResolutionTest class.
 *
 * @package WpMVC\Container\Tests\Integration
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WpMVC\Container\Container;
use WpMVC\Container\Tests\Fixtures\Models\ConcreteClass;

/**
 * Class RecursiveResolutionTest
 *
 * Verifies the container's ability to resolve complex, nested, and recursive dependency chains
 * involving mixed bindings and aliases.
 *
 * @package WpMVC\Container\Tests\Integration
 */
class RecursiveResolutionTest extends TestCase
{
    /**
     * Verifies that the container can resolve a chain of mixed aliases and bindings.
     */
    public function test_recursive_resolution_handles_mixed_alias_and_binding() {
        $container = new Container();
        
        $interface = RecursiveInterface::class;
        $abstract  = 'abstract_service';
        $alias     = 'impl_alias';
        $concrete  = RecursiveImpl::class;

        $container->alias( $abstract, $interface );
        $container->bind( $abstract, $alias );
        $container->alias( $concrete, $alias );

        $instance = $container->get( $interface );
        $this->assertInstanceOf( $concrete, $instance );
    }

    /**
     * Verifies that the CallbackInvoker can resolve service IDs from the container.
     */
    public function test_callback_invoker_resolves_generic_service_id() {
        $container = new Container();
        $container->set( 'my_service', new CallbackService() );

        $result = $container->call( ['my_service', 'handle'] );
        $this->assertEquals( 'handled', $result );
    }

    /**
     * Verifies that the ResolutionEngine correctly filters positional candidates based on type hints.
     */
    public function test_positional_parameters_respect_builtin_type_hints() {
        $container = new Container();
        $container->singleton( ConcreteClass::class );

        // Provides stdClass (invalid for string $name) and "John" (valid for string $name)
        $instance = $container->make( PositionalGuardTarget::class, [new \stdClass(), "John"] );

        $this->assertEquals( "John", $instance->name );
        $this->assertInstanceOf( ConcreteClass::class, $instance->dep );
    }
}

// Internal Stubs for RecursiveResolutionTest
interface RecursiveInterface {}
class RecursiveImpl implements RecursiveInterface {}

class CallbackService {
    public function handle() {
        return 'handled';
    }
}

class PositionalGuardTarget {
    public $name;

    public $dep;

    public function __construct( string $name, ConcreteClass $dep ) {
        $this->name = $name;
        $this->dep  = $dep;
    }
}
