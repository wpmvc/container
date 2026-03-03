<?php
/**
 * ResolutionEngine class.
 *
 * @package WpMVC\Container
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container;

defined( 'ABSPATH' ) || exit;

use ReflectionClass;
use ReflectionNamedType;
use Psr\Container\ContainerInterface;
use WpMVC\Container\Exception\ContainerException;
use WpMVC\Container\Exception\NotFoundException;
use WpMVC\Container\Exception\CircularDependencyException;

/**
 * Class ResolutionEngine
 *
 * Handles reflection-based autowiring and dependency resolution for classes.
 *
 * @package WpMVC\Container
 */
class ResolutionEngine
{
    /**
     * The container instance.
     *
     * @var ContainerInterface|Container
     */
    protected $container;

    /**
     * Cache for reflection objects.
     *
     * @var array
     */
    protected $reflection_cache = [
        'constructors' => [],
        'methods'      => [],
    ];

    /**
     * ResolutionEngine constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct( ContainerInterface $container ) {
        $this->container = $container;
    }

    /**
     * Resolve a service instance using Reflection.
     *
     * @param  string  $id
     * @param  array   $params
     * @return mixed
     * @throws NotFoundException   If the class does not exist.
     * @throws ContainerException  If the class is not instantiable.
     */
    public function resolve( string $id, array $params = [] ) {
        if ( ! isset( $this->reflection_cache['constructors'][$id] ) ) {
            $ref = new ReflectionClass( $id );
            
            if ( ! $ref->isInstantiable() ) {
                throw new ContainerException( "Class is not instantiable: {$id}" );
            }

            $this->reflection_cache['constructors'][$id] = $ref;
        }

        $ref         = $this->reflection_cache['constructors'][$id];
        $constructor = $ref->getConstructor();
        $args        = [];
        
        if ( $constructor ) {
            $args = $this->resolve_dependencies( $constructor->getParameters(), $params );
        }

        return $ref->newInstanceArgs( $args );
    }

    /**
     * Resolve dependencies for a set of ReflectionParameters.
     *
     * @param  \ReflectionParameter[]  $parameters_metadata
     * @param  array                   $parameters
     * @return array
     * @throws ContainerException  If a dependency cannot be resolved.
     */
    public function resolve_dependencies( array $parameters_metadata, array $parameters = [] ): array {
        $args = [];
        
        foreach ( $parameters_metadata as $param ) {
            $name = $param->getName();

            // 1. Try named parameter from the provided array
            if ( array_key_exists( $name, $parameters ) ) {
                $args[] = $parameters[$name];
                unset( $parameters[$name] );
                continue;
            }

            // 2. Try to resolve by type hint
            $type = $param->getType();
            if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
                $id = $type->getName();

                // Optimization: Check if any provided parameter matches this type
                foreach ( $parameters as $key => $provided_param ) {
                    if ( is_object( $provided_param ) && ( $provided_param instanceof $id || ltrim( get_class( $provided_param ), '\\' ) === ltrim( $id, '\\' ) ) ) {
                        $args[] = $provided_param;
                        unset( $parameters[$key] );
                        continue 2;
                    }
                }

                try {
                    // Recursively resolve the dependency from the container
                    $args[] = $this->container->get( $id, $parameters );
                    continue;
                } catch ( NotFoundException $e ) {
                    // Fall through to other resolution strategies
                } catch ( CircularDependencyException $e ) {
                    throw $e;
                } catch ( ContainerException $e ) {
                    // Fall through to other resolution strategies
                }
            }

            // 3. Try variadic parameter
            if ( $param->isVariadic() ) {
                // Collect all remaining parameters
                foreach ( $parameters as $key => $value ) {
                    $args[] = $value;
                    unset( $parameters[$key] );
                }
                break;
            }

            // 4. Try positional parameter from the remaining array
            if ( ( $found_key = $this->resolve_positional_parameter( $parameters, $type ) ) !== null ) {
                $args[] = $parameters[$found_key];
                unset( $parameters[$found_key] );
                continue;
            }

            // 5. Try default value
            if ( $param->isDefaultValueAvailable() ) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // 6. Handle Nullable types
            if ( ( method_exists( $param, 'allowsNull' ) && $param->allowsNull() ) || ( $type && $type->allowsNull() ) ) {
                $args[] = null;
                continue;
            }

            throw new ContainerException( "Unresolvable dependency [{$param}] in [{$param->getDeclaringClass()->getName()}]." );
        }
        
        return $args;
    }

    /**
     * Resolve a positional parameter from the given candidates.
     *
     * @param  array                 $parameters
     * @param  \ReflectionType|null  $type
     * @return int|string|null
     */
    protected function resolve_positional_parameter( array $parameters, $type ) {
        $candidates = array_filter( array_keys( $parameters ), 'is_int' );
        sort( $candidates );

        foreach ( $candidates as $key ) {
            if ( $this->is_valid_type( $parameters[$key], $type ) ) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Check if a given value matches the expected reflection type.
     *
     * @param  mixed                 $value
     * @param  \ReflectionType|null  $type
     * @return bool
     */
    protected function is_valid_type( $value, $type ): bool {
        if ( ! $type instanceof ReflectionNamedType ) {
            return true;
        }

        if ( ! $type->isBuiltin() ) {
            $target = $type->getName();
            return $value instanceof $target;
        }

        // Tighten scalar type checks
        switch ( $type->getName() ) {
            case 'int':      return is_int( $value );
            case 'string':   return is_string( $value );
            case 'bool':     return is_bool( $value );
            case 'float':    return is_float( $value );
            case 'array':    return is_array( $value );
            case 'object':   return is_object( $value );
            case 'callable': return is_callable( $value );
            case 'iterable': return is_iterable( $value );
            case 'mixed':    return true;
        }

        return ! is_object( $value );
    }

    /**
     * Retrieve a cached reflection method.
     *
     * @param  string  $key
     * @return \ReflectionMethod|null
     */
    public function get_cached_method( string $key ): ?\ReflectionMethod {
        return $this->reflection_cache['methods'][$key] ?? null;
    }

    /**
     * Cache a reflection method for future reuse.
     *
     * @param  string             $key
     * @param  \ReflectionMethod  $method
     * @return void
     */
    public function cache_method( string $key, \ReflectionMethod $method ): void {
        $this->reflection_cache['methods'][$key] = $method;
    }
}
