<?php
/**
 * ContainerLifecycleTest class.
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
use WpMVC\Container\Tests\Fixtures\Models\ClassWithParams;

/**
 * Class ContainerLifecycleTest
 *
 * Verifies service lifecycle management, including scoping (singleton vs transient),
 * parameter overrides, and closure factory behavior.
 *
 * @package WpMVC\Container\Tests\Integration
 */
class ContainerLifecycleTest extends TestCase
{
    protected $container;

    protected function setUp(): void {
        $this->container = new Container();
    }

    /**
     * Verifies the distinction between singleton and transient scopes.
     */
    public function test_it_differentiates_between_singleton_and_transient_scopes() {
        // Singleton scope
        $this->container->singleton( 'shared', ConcreteClass::class );
        $obj1 = $this->container->get( 'shared' );
        $obj2 = $this->container->get( 'shared' );
        $this->assertSame( $obj1, $obj2, "Singleton must return the same instance." );

        // Transient scope
        $this->container->bind( 'transient', ConcreteClass::class );
        $obj3 = $this->container->get( 'transient' );
        $obj4 = $this->container->get( 'transient' );
        $this->assertNotSame( $obj3, $obj4, "Bind must return a fresh instance every time." );
    }

    /**
     * Verifies that parameters can be overridden during make() calls.
     */
    public function test_it_overrides_parameters_in_make() {
        $instance = $this->container->make(
            ClassWithParams::class, [
                'value'  => 'runtime-value',
                'number' => 100
            ]
        );

        $this->assertEquals( 'runtime-value', $instance->value );
        $this->assertEquals( 100, $instance->number );

        // Different call with partial overrides
        $instance2 = $this->container->make(
            ClassWithParams::class, [
                'value' => 'another-value'
            ]
        );
        $this->assertEquals( 'another-value', $instance2->value );
        $this->assertEquals( 0, $instance2->number ); // Should use default 0
    }

    /**
     * Verifies that shared instances can be parameterized (parameters are ignored if already instantiated).
     */
    public function test_it_allows_shared_instance_parameterization() {
        $this->container->singleton( ClassWithParams::class );
        
        $instance = $this->container->get( ClassWithParams::class, ['value' => 'first-call'] );
        $this->assertEquals( 'first-call', $instance->value );

        // This should NO LONGER throw an exception
        $instance2 = $this->container->get( ClassWithParams::class, ['value' => 'second-call'] );
        
        $this->assertSame( $instance, $instance2 );
        $this->assertEquals( 'first-call', $instance2->value ); // Parameters were ignored
    }

    /**
     * Verifies that closure factories receive the container and specific parameters.
     */
    public function test_it_passes_parameters_to_closure_factories() {
        $this->container->bind(
            'factory', function( $app, $params ) {
                $obj           = new \stdClass();
                $obj->injected = $params['val'] ?? 'default';
                $obj->app      = $app;
                return $obj;
            }
        );

        $instance = $this->container->make( 'factory', ['val' => 'custom'] );
        $this->assertEquals( 'custom', $instance->injected );
        $this->assertSame( $this->container, $instance->app );

        $instance2 = $this->container->make( 'factory' );
        $this->assertEquals( 'default', $instance2->injected );
    }
}
