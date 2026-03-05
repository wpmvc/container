<?php
/**
 * ContextualBindingBuilder class.
 *
 * @package WpMVC\Container
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContextualBindingBuilder
 *
 * Fluent API for defining contextual bindings.
 *
 * @package WpMVC\Container
 */
class ContextualBindingBuilder
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The concrete class that needs the dependency.
     *
     * @var string
     */
    protected $concrete;

    /**
     * The abstract dependency that needs resolving.
     *
     * @var string
     */
    protected $needs;

    /**
     * ContextualBindingBuilder constructor.
     *
     * @param Container $container
     * @param string    $concrete
     */
    public function __construct( Container $container, string $concrete ) {
        $this->container = $container;
        $this->concrete  = $concrete;
    }

    /**
     * Define the abstract dependency the class needs.
     * 
     * This is usually an interface or a class name that is injected 
     * into the concrete class's constructor.
     *
     * @param  string  $abstract  The abstract identifier.
     * @return $this
     */
    public function needs( string $abstract ): self {
        $this->needs = $abstract;
        return $this;
    }

    /**
     * Define the implementation to be given when the dependency is requested.
     * 
     * Binds the specific implementation (class name or closure) to the 
     * abstract dependency for the current concrete context.
     *
     * @param  mixed  $implementation  The implementation to provide (Class name or Closure).
     * @return void
     */
    public function give( $implementation ): void {
        $this->container->add_contextual_binding( $this->concrete, $this->needs, $implementation );
    }
}
