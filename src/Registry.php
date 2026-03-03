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
     * Register a transient binding.
     *
     * @param  string      $abstract
     * @param  mixed|null  $concrete
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
                $this->tags[$tag][] = $abstract;
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
     * @param  string  $id
     * @return string
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
     * @param  string  $abstract
     * @param  array   $history
     * @return mixed
     * @throws ContainerException  If a circular resolution path is detected.
     */
    public function get_concrete( string $abstract, array $history = [] ) {
        if ( isset( $history[$abstract] ) ) {
            throw new ContainerException( "Circular resolution detected for [{$abstract}]." );
        }
        
        $history[$abstract] = true;

        $id = $this->resolve_id( $abstract );

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
        return $this->bindings[$id]['shared'] ?? false;
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
        $this->bindings = [];
        $this->aliases  = [];
        $this->tags     = [];
    }
}
