<?php
/**
 * Registry class.
 *
 * @package WpMVC\Container
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container;

defined( 'ABSPATH' ) || exit;

use WpMVC\Container\Exception\ContainerException;

/**
 * Class Registry
 *
 * Responsible for managing service bindings, aliases, and tags.
 *
 * @package WpMVC\Container
 */
class Registry
{
    /**
     * The stored service bindings.
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * The registered service aliases.
     *
     * @var array
     */
    protected $aliases = [];

    /**
     * The registered service tags.
     *
     * @var array
     */
    protected $tags = [];

    /**
     * The contextual bindings.
     *
     * @var array
     */
    protected $contextual = [];

    /**
     * Register a transient (non-shared) binding.
     * 
     * Transient services are re-instantiated every time they are resolved.
     *
     * @param  string      $abstract  The abstract identifier (interface or class name).
     * @param  mixed|null  $concrete  The concrete implementation (closure or class name).
     * @return void
     */
    public function bind( string $abstract, $concrete = null ): void {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?: $abstract,
            'shared'   => false,
        ];
    }

    /**
     * Register a shared (singleton) binding.
     *
     * @param  string      $abstract
     * @param  mixed|null  $concrete
     * @return void
     */
    public function singleton( string $abstract, $concrete = null ): void {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?: $abstract,
            'shared'   => true,
        ];
    }

    /**
     * Alias a service to a different name.
     *
     * @param  string  $abstract
     * @param  string  $alias
     * @return void
     */
    public function alias( string $abstract, string $alias ): void {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Assign a set of tags to a given binding.
     *
     * @param  array|string  $abstracts
     * @param  array|mixed   $tags
     * @return void
     */
    public function tag( $abstracts, $tags ): void {
        $tags      = (array) $tags;
        $abstracts = (array) $abstracts;

        foreach ( $tags as $tag ) {
            if ( ! isset( $this->tags[$tag] ) ) {
                $this->tags[$tag] = [];
            }

            foreach ( $abstracts as $abstract ) {
                if ( ! in_array( $abstract, $this->tags[$tag], true ) ) {
                    $this->tags[$tag][] = $abstract;
                }
            }
        }
    }

    /**
     * Get all bindings associated with a given tag.
     *
     * @param  string  $tag
     * @return array
     */
    public function get_tag( string $tag ): array {
        return $this->tags[$tag] ?? [];
    }

    /**
     * Resolve the terminal identifier by following all aliases.
     * 
     * This method recursively follows aliases until it finds the root identifier.
     * It includes basic circular alias detection.
     *
     * @param  string  $id  The identifier or alias to resolve.
     * @return string       The terminal (root) identifier.
     * @throws ContainerException  If a circular alias resolution is detected.
     */
    public function resolve_id( string $id ): string {
        $history = [];
        
        while ( isset( $this->aliases[$id] ) ) {
            if ( isset( $history[$id] ) ) {
                throw new ContainerException( "Circular alias resolution detected for [{$id}]." );
            }
            
            $history[$id] = true;
            $id           = $this->aliases[$id];
        }
        
        return $id;
    }

    /**
     * Get the concrete implementation mapped to an abstract identifier.
     *
     * Performs a full resolution including alias traversal and recursion 
     * for chained bindings.
     *
     * @param  string  $abstract  The abstract identifier to resolve.
     * @param  array   $history   Resolution history (used for circular detection).
     * @return mixed              The concrete implementation (target class or closure).
     * @throws ContainerException  If a circular resolution path is detected.
     */
    public function get_concrete( string $abstract, array $history = [] ) {
        if ( isset( $history[$abstract] ) ) {
            throw new ContainerException( "Circular resolution detected for [{$abstract}]." );
        }
        
        $history[$abstract] = true;

        $id = $this->resolve_id( $abstract );

        return $this->get_concrete_internal( $id, $history );
    }

    /**
     * Internal method to get concrete implementation without re-resolving ID.
     * 
     * @internal This method assumes the ID has already been resolved via resolve_id().
     * 
     * @param  string  $id       The already resolved terminal ID.
     * @param  array   $history  Resolution history.
     * @return mixed
     */
    public function get_concrete_internal( string $id, array $history = [] ) {
        if ( isset( $this->bindings[$id] ) ) {
            $concrete = $this->bindings[$id]['concrete'];
            
            // Recurse if the concrete is another binding or alias
            if ( is_string( $concrete ) && $concrete !== $id && $this->has( $concrete ) ) {
                return $this->get_concrete( $concrete, $history );
            }

            return $concrete;
        }

        return $id;
    }

    /**
     * Determine if a given service is shared (singleton).
     *
     * @param  string  $abstract
     * @return bool
     */
    public function is_shared( string $abstract ): bool {
        $id = $this->resolve_id( $abstract );
        return $this->is_shared_internal( $id );
    }

    /**
     * Internal method to check shared status without re-resolving ID.
     * 
     * @internal
     */
    public function is_shared_internal( string $id ): bool {
        return $this->bindings[$id]['shared'] ?? false;
    }

    /**
     * Add a contextual binding to the registry.
     *
     * @param  string  $concrete
     * @param  string  $abstract
     * @param  mixed   $implementation
     * @return void
     */
    public function add_contextual_binding( string $concrete, string $abstract, $implementation ): void {
        $this->contextual[$concrete][$abstract] = $implementation;
    }

    /**
     * Get the contextual binding for a given concrete and abstract.
     *
     * @param  string  $concrete
     * @param  string  $abstract
     * @return mixed|null
     */
    public function get_contextual_binding( string $concrete, string $abstract ) {
        return $this->contextual[$concrete][$abstract] ?? null;
    }

    /**
     * Check if a binding or alias exists for the given ID.
     *
     * @param  string  $id
     * @return bool
     */
    public function has( string $id ): bool {
        return isset( $this->bindings[$id] ) || isset( $this->aliases[$id] );
    }

    /**
     * Clear all bindings, aliases, and tags from the registry.
     *
     * @return void
     */
    public function flush(): void {
        $this->bindings   = [];
        $this->aliases    = [];
        $this->tags       = [];
        $this->contextual = [];
    }
}
