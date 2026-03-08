<?php
/**
 * BasicBindingTest class.
 *
 * @package WpMVC\Container\Tests\Integration
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WpMVC\Container\Container;
use WpMVC\Container\Tests\Fixtures\Contracts\InterfaceA;
use WpMVC\Container\Tests\Fixtures\Contracts\InterfaceB;
use WpMVC\Container\Tests\Fixtures\Models\ConcreteC;
use WpMVC\Container\Tests\Fixtures\Models\Level1;
use WpMVC\Container\Tests\Fixtures\Models\Level2;
use WpMVC\Container\Tests\Fixtures\Models\Level3;
use WpMVC\Container\Tests\Fixtures\Models\Level4;
use WpMVC\Container\Tests\Fixtures\Models\Level5;
use WpMVC\Container\Tests\Fixtures\Models\MixedDependencyClass;
use WpMVC\Container\Tests\Fixtures\Models\ConcreteClass;

/**
 * Class BasicBindingTest
 *
 * Covers standard container functionality including deep nesting resolution,
 * interface chaining, recursive aliasing, and mixed autowiring.
 *
 * @package WpMVC\Container\Tests\Integration
 */
class BasicBindingTest extends TestCase
{
    protected Container $container;

    protected function setUp(): void {
        $this->container = new Container();
    }

    /**
     * Verifies resolution of deep dependency trees (5 levels).
     */
    public function test_it_resolves_deep_dependency_trees() {
        $start_time = microtime( true );
        $level1     = $this->container->get( Level1::class );
        $end_time   = microtime( true );

        $this->assertInstanceOf( Level1::class, $level1 );
        $this->assertInstanceOf( Level2::class, $level1->level2 );
        $this->assertInstanceOf( Level3::class, $level1->level2->level3 );
        $this->assertInstanceOf( Level4::class, $level1->level2->level3->level4 );
        $this->assertInstanceOf( Level5::class, $level1->level2->level3->level4->level5 );

        // Performance check for reflection cache: second resolution should be significantly faster
        $start_time2 = microtime( true );
        $this->container->make( Level1::class );
        $end_time2 = microtime( true );

        $this->assertLessThanOrEqual( $end_time - $start_time, $end_time2 - $start_time2, "Cached resolution should be faster." );
    }

    /**
     * Verifies resolution of interface chains (A -> B -> Concrete).
     */
    public function test_it_resolves_interface_chains() {
        $this->container->bind( InterfaceA::class, InterfaceB::class );
        $this->container->bind( InterfaceB::class, ConcreteC::class );

        $instance = $this->container->get( InterfaceA::class );
        $this->assertInstanceOf( ConcreteC::class, $instance );
    }

    /**
     * Verifies resolution of recursive alias chains.
     */
    public function test_it_resolves_recursive_aliases() {
        $this->container->alias( ConcreteClass::class, 'alias3' );
        $this->container->alias( 'alias3', 'alias2' );
        $this->container->alias( 'alias2', 'alias1' );

        $instance = $this->container->get( 'alias1' );
        $this->assertInstanceOf( ConcreteClass::class, $instance );
    }

    /**
     * Verifies handling of mixed autowiring (classes + provided parameters).
     */
    public function test_it_handles_mixed_autowiring_with_overrides() {
        $instance = $this->container->make(
            MixedDependencyClass::class, [
                'value' => 'injected-value'
            ]
        );

        $this->assertInstanceOf( MixedDependencyClass::class, $instance );
        $this->assertInstanceOf( ConcreteClass::class, $instance->concrete );
        $this->assertEquals( 'injected-value', $instance->value );
    }
}
