<?php
/**
 * Container class.
 *
 * @package WpMVC\Container
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container;

defined( 'ABSPATH' ) || exit;

use Closure;
use Psr\Container\ContainerInterface;
use WpMVC\Container\Exception\ContainerException;
use WpMVC\Container\Exception\NotFoundException;
use WpMVC\Container\Exception\CircularDependencyException;

/**
 * Class Container
 *
 * An Enterprise-Ready Dependency Injection Container for WordPress.
 * 
 * Provides a robust implementation of the PSR-11 ContainerInterface, 
 * featuring reflection-based autowiring, singleton management, 
 * alias resolution, and contextual bindings.
 *
 * @package WpMVC\Container
 *
 * @method $this bind(string $abstract, mixed|null $concrete = null) Register a transient binding.
 * @method $this singleton(string $abstract, mixed|null $concrete = null) Register a shared binding.
 * @method $this alias(string $abstract, string $alias) Alias a type to another name.
 * @method mixed get(string $id, array $params = []) Resolve a service instance from the container.
 * @method mixed make(string $abstract, array $parameters = []) Create a fresh instance of a class (Factory).
 * @method mixed call(callable|array|string $callback, array $parameters = []) Invoke a callable with dependency injection.
 * @method ContextualBindingBuilder when(string $concrete) Define a contextual binding for a specific class.
 */
class Container implements ContainerInterface
{
    /**
     * Stored service instances (Singletons).
     *
     * @var array
     */
    protected $instances = [];

    /**
     * Stack of IDs currently being resolved (Circular detection).
     *
     * @var array
     */
    protected $resolving = [];

    /**
     * The service registry instance.
     *
     * @var Registry
     */
    protected $registry;

    /**
     * The resolution engine instance.
     *
     * @var ResolutionEngine
     */
    protected $engine;

    /**
     * The callback invoker instance.
     *
     * @var CallbackInvoker
     */
    protected $invoker;

    /**
     * Container constructor.
     */
    public function __construct() {
        $this->registry = new Registry();
        $this->engine   = new ResolutionEngine( $this );
        $this->invoker  = new CallbackInvoker( $this, $this->engine );
    }

    /**
     * Register a transient binding.
     *
     * @param  string      $abstract
     * @param  mixed|null  $concrete
     * @return $this
     */
    public function bind( string $abstract, $concrete = null ): self {
        $this->registry->bind( $abstract, $concrete );
        return $this;
    }

    /**
     * Register a shared (singleton) binding.
     *
     * @param  string      $abstract
     * @param  mixed|null  $concrete
     * @return $this
     */
    public function singleton( string $abstract, $concrete = null ): self {
        $this->registry->singleton( $abstract, $concrete );
        return $this;
    }

    /**
     * Alias a type to another name.
     *
     * @param  string  $abstract
     * @param  string  $alias
     * @return $this
     */
    public function alias( string $abstract, string $alias ): self {
        $this->registry->alias( $abstract, $alias );
        return $this;
    }

    /**
     * Assign a set of tags to a given binding.
     *
     * @param  array|string  $abstracts
     * @param  array|mixed   $tags
     * @return $this
     */
    public function tag( $abstracts, $tags ): self {
        $this->registry->tag( (array) $abstracts, (array) $tags );
        return $this;
    }

    /**
     * Resolve all services associated with a given tag.
     *
     * @param  string  $tag  The tag identifier.
     * @return iterable      A collection of resolved service instances.
     */
    public function tagged( string $tag ): iterable {
        return array_map(
            function ( $abstract ) {
                return $this->get( $abstract );
            }, $this->registry->get_tag( $tag )
        );
    }

    /**
     * Define a contextual binding for a specific class.
     * 
     * Initiates the fluent API for context-sensitive dependency injection.
     *
     * @param  string  $concrete  The class name that requires the contextual binding.
     * @return ContextualBindingBuilder
     */
    public function when( string $concrete ): ContextualBindingBuilder {
        return new ContextualBindingBuilder( $this, $concrete );
    }

    /**
     * Add a contextual binding for a given class.
     *
     * @internal
     * @param  string  $concrete
     * @param  string  $abstract
     * @param  mixed   $implementation
     * @return void
     */
    public function add_contextual_binding( string $concrete, string $abstract, $implementation ): void {
        $this->registry->add_contextual_binding( $concrete, $abstract, $implementation );
    }

    /**
     * Get the contextual binding for a given class.
     *
     * @internal
     * @param  string  $concrete
     * @param  string  $abstract
     * @return mixed|null
     */
    public function get_contextual_binding( string $concrete, string $abstract ) {
        return $this->registry->get_contextual_binding( $concrete, $abstract );
    }

    /**
     * Get a service from the container.
     *
     * @template T
     * @param  class-string<T>  $id      Service ID or class name to resolve.
     * @param  array                   $params  Parameters for resolution.
     * @return T
     *
     * @throws NotFoundException            If the service cannot be resolved.
     * @throws CircularDependencyException  If a circularity is detected.
     * @throws ContainerException           If instantiation fails.
     */
    public function get( string $id, array $params = [] ) {
        // 1. Resolve terminal ID immediately to ensure alias consistency.
        $resolved_id = $this->registry->resolve_id( $id );

        // 2. Direct cache hit (check if the service is already instantiated).
        if ( isset( $this->instances[$resolved_id] ) ) {
            return $this->instances[$resolved_id];
        }

        $concrete  = $this->registry->get_concrete_internal( $resolved_id );
        $is_shared = $this->registry->is_shared_internal( $resolved_id );

        // 3. Cross-resolution cache hit (e.g., interface resolved to already cached concrete singleton).
        if ( $is_shared && is_string( $concrete ) && isset( $this->instances[$concrete] ) ) {
            return $this->instances[$resolved_id] = $this->instances[$concrete];
        }

        // 4. Circular dependency detection using the terminal resolved ID.
        if ( isset( $this->resolving[$resolved_id] ) ) {
            throw new CircularDependencyException( "Circular dependency detected while resolving: {$id} (resolved to {$resolved_id})" );
        }

        $this->resolving[$resolved_id] = true;

        try {
            // 5. Build the instance via the resolution engine.
            $instance = $this->build( $concrete, $params, $id );

            // 6. Cache the instance if the binding is shared (singleton).
            if ( $is_shared ) {
                $this->instances[$resolved_id] = $instance;
                
                // If it was an interface resolution, alias the interface to the instance too.
                if ( $id !== $resolved_id ) {
                    $this->instances[$id] = $instance;
                }
            }

            return $instance;
        } finally {
            // Unset resolution flag to avoid false positives in subsequent calls.
            unset( $this->resolving[$resolved_id] );
        }
    }

    /**
     * Create a new instance of the given class (Factory).
     *
     * @template T
     * @param  class-string<T>  $abstract    The class name or abstract to build.
     * @param  array                   $parameters  Parameters for resolution.
     * @return T
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function make( string $abstract, array $parameters = [] ) {
        $abstract = $this->registry->resolve_id( $abstract );
        $concrete = $this->registry->get_concrete( $abstract );

        return $this->build( $concrete, $parameters, $abstract );
    }

    /**
     * Build the concrete instance.
     *
     * @param  mixed    $concrete
     * @param  array    $params
     * @param  ?string  $id
     * @return mixed
     * @throws ContainerException  If the target is not instantiable.
     * @throws NotFoundException   If class or alias not found.
     */
    protected function build( $concrete, array $params = [], ?string $id = null ) {
        // Resolve Closure closures
        if ( $concrete instanceof Closure ) {
            return $concrete( $this, $params );
        }

        if ( is_string( $concrete ) ) {
            // Try to autowire the class
            if ( class_exists( $concrete ) ) {
                return $this->engine->resolve( $concrete, $params );
            }
             
            // Return raw string if it's an alias pointing to something else
            if ( $id !== null && $concrete !== $id ) {
                return $concrete;
            }

             throw new NotFoundException( "Class or alias not found: {$concrete}" );
        }

        // Return provided objects
        if ( is_object( $concrete ) ) {
            return $concrete;
        }

        // Handle array callbacks
        if ( is_array( $concrete ) && is_callable( $concrete ) ) {
            return $this->call( $concrete, $params );
        }

        // Return scalars/arrays
        if ( is_scalar( $concrete ) || is_array( $concrete ) ) {
            return $concrete;
        }

        throw new ContainerException( "Target is not instantiable or callable: " . gettype( $concrete ) );
    }

    /**
     * Call a callback with dependency injection.
     *
     * @param  callable|array|string  $callback
     * @param  array                  $parameters
     * @return mixed
     * @throws \ReflectionException
     * @throws ContainerException
     */
    public function call( $callback, array $parameters = [] ) {
        return $this->invoker->call( $callback, $parameters );
    }

    /**
     * Set a shared instance (singleton) directly into the container.
     *
     * @param  string  $id
     * @param  mixed   $instance
     * @return $this
     */
    public function set( string $id, $instance ): self {
        $resolved_id = $this->registry->resolve_id( $id );
        $concrete    = $this->registry->get_concrete( $id );

        $this->instances[$id] = $instance;

        if ( is_string( $concrete ) && $concrete !== $id ) {
            $this->instances[$concrete] = $instance;
        }

        if ( $resolved_id !== $id && $resolved_id !== $concrete ) {
            $this->instances[$resolved_id] = $instance;
        }

        return $this;
    }

    /**
     * Check if the container has a service or class registered.
     *
     * @param  string  $id
     * @return bool
     */
    public function has( string $id ): bool {
        // Fast paths: Already instantiated or registered in the registry
        if ( isset( $this->instances[$id] ) || $this->registry->has( $id ) ) {
            return true;
        }

        // Slow path: Resolve alias and check again, then check class_exists
        $id = $this->registry->resolve_id( $id );
        return isset( $this->instances[$id] ) || $this->registry->has( $id ) || class_exists( $id );
    }

    /**
     * Get the terminal resolved ID for a given identifier.
     *
     * @param  string  $id
     * @return string
     */
    public function resolved_id( string $id ): string {
        return $this->registry->resolve_id( $id );
    }

    /**
     * Clear all cached singleton instances.
     *
     * @return void
     */
    public function forget_instances(): void {
        $this->instances = [];
    }

    /**
     * Reset the entire container to its initial state.
     *
     * @return void
     */
    public function flush(): void {
        $this->instances = [];
        $this->registry->flush();
    }
}
