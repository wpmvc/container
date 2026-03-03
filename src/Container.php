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
 * Enterprise-Ready Dependency Injection Container for WordPress.
 *
 * @package WpMVC\Container
 *
 * @method $this bind(string $abstract, mixed|null $concrete = null)
 * @method $this singleton(string $abstract, mixed|null $concrete = null)
 * @method $this alias(string $abstract, string $alias)
 * @method mixed get(string $id, array $params = [])
 * @method mixed make(string $abstract, array $parameters = [])
 * @method mixed call(callable|array|string $callback, array $parameters = [])
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
     * Resolve all bindings for a given tag.
     *
     * @param  string  $tag
     * @return iterable
     */
    public function tagged( string $tag ): iterable {
        return array_map(
            function ( $abstract ) {
                return $this->get( $abstract );
            }, $this->registry->get_tag( $tag )
        );
    }

    /**
     * Get a service from the container.
     *
     * @template T
     * @param  class-string<T>|string  $id      Service ID or class name to resolve.
     * @param  array                   $params  Parameters for resolution.
     * @return T
     *
     * @throws NotFoundException            If the service cannot be resolved.
     * @throws CircularDependencyException  If a circularity is detected.
     * @throws ContainerException           If instantiation fails.
     */
    public function get( string $id, array $params = [] ) {
        // 1. If it's already in instance cache, return it
        if ( isset( $this->instances[$id] ) ) {
            if ( ! empty( $params ) ) {
                throw new ContainerException( "Cannot pass parameters to an already instantiated shared service: {$id}" );
            }
            return $this->instances[$id];
        }

        // 2. Circular dependency detection
        if ( isset( $this->resolving[$id] ) ) {
            throw new CircularDependencyException( "Circular dependency detected while resolving: {$id}" );
        }

        $this->resolving[$id] = true;

        try {
            // 3. Determine terminal ID and concrete
            $resolved_id = $this->registry->resolve_id( $id );
            $concrete    = $this->registry->get_concrete( $id );
            
            // If the key is not in registry, try to autowire it as a class
            $is_shared = $this->registry->has( $id ) ? $this->registry->is_shared( $id ) : true;

            // 4. Check if the resolved ID or concrete is already instantiated
            $cached_key = null;
            if ( isset( $this->instances[$resolved_id] ) ) {
                $cached_key = $resolved_id;
            } elseif ( is_string( $concrete ) && isset( $this->instances[$concrete] ) ) {
                $cached_key = $concrete;
            }

            if ( $is_shared && $cached_key !== null ) {
                if ( ! empty( $params ) ) {
                    throw new ContainerException( "Cannot pass parameters to an already instantiated shared service: {$cached_key}" );
                }
                $this->instances[$id] = $this->instances[$cached_key];
                return $this->instances[$id];
            }

            // 5. Build the instance
            $instance = $this->build( $concrete, $params, $id );

            // 6. Cache if shared
            if ( $is_shared ) {
                $this->instances[$id] = $instance;
                
                // Map the instance to the concrete class name too
                if ( is_string( $concrete ) && $concrete !== $id ) {
                    $this->instances[$concrete] = $instance;
                }
                
                // Map it to the terminal resolved ID too
                if ( $resolved_id !== $id && $resolved_id !== $concrete ) {
                    $this->instances[$resolved_id] = $instance;
                }
            }

            return $instance;
        } finally {
            unset( $this->resolving[$id] );
        }
    }

    /**
     * Create a new instance of the given class (Factory).
     *
     * @template T
     * @param  class-string<T>|string  $abstract    The class name or abstract to build.
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
