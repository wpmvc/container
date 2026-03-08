<?php
/**
 * TypeSafetyTest class.
 *
 * @package WpMVC\Container\Tests\Integration
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WpMVC\Container\Container;
use WpMVC\Container\Exception\CircularDependencyException;
use WpMVC\Container\Exception\ContainerException;

/**
 * Class TypeSafetyTest
 *
 * Focuses on verifying strict type guarding and hardening measures within the container,
 * including scalar mismatch prevention and closure-based cycle detection.
 *
 * @package WpMVC\Container\Tests\Integration
 */
class TypeSafetyTest extends TestCase
{
    /**
     * Verifies that circular dependencies in closures are correctly caught.
     */
    public function test_circular_closure_detection() {
        $container = new Container();
        
        $container->bind(
            'A', function( $c ) {
                return $c->get( 'B' );
            }
        );
        
        $container->bind(
            'B', function( $c ) {
                return $c->get( 'A' );
            }
        );

        try {
            $container->get( 'A' );
        } catch ( \Exception $e ) {
            $this->assertInstanceOf( CircularDependencyException::class, $e );
            return;
        }
        
        $this->fail( 'CircularDependencyException was not thrown for closure cycle.' );
    }

    /**
     * Verifies that scalar type guards prevent type mismatches during resolution.
     */
    public function test_scalar_type_guards_prevent_mismatch() {
        $container = new Container();
        
        // Target needs an int
        $target = new class(0) {
            public $val;

            public function __construct( int $val ) {
                $this->val = $val; }
        };
        $class  = get_class( $target );

        // Providing a string "123" instead of an int should fail
        try {
            $container->make( $class, ["123"] );
        } catch ( \Exception $e ) {
            $this->assertInstanceOf( ContainerException::class, $e );
            return;
        }

        $this->fail( 'ContainerException was not thrown for scalar type mismatch.' );
    }
}
