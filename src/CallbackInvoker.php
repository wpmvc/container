<?php
/**
 * CallbackInvoker class.
 *
 * @package WpMVC\Container
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container;

defined( 'ABSPATH' ) || exit;

use Closure;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;
use Psr\Container\ContainerInterface;
use WpMVC\Container\Exception\ContainerException;

/**
 * Class CallbackInvoker
 *
 * Provides functionality for calling callbacks and methods with dependency injection.
 *
 * @package WpMVC\Container
 */
class CallbackInvoker
{
    /**
     * The container instance.
     *
     * @var ContainerInterface|Container
     */
    protected $container;

    /**
     * The resolution engine instance.
     *
     * @var ResolutionEngine
     */
    protected $engine;

    /**
     * CallbackInvoker constructor.
     *
     * @param ContainerInterface $container
     * @param ResolutionEngine   $engine
     */
    public function __construct( ContainerInterface $container, ResolutionEngine $engine ) {
        $this->container = $container;
        $this->engine    = $engine;
    }

    /**
     * Invoke a callback with dependency injection.
     * 
     * Resolves the callback format, matches its parameters with the container/params,
     * and executes the call.
     *
     * @param  callable|array|string  $callback    The callback to invoke (Function, Closure, [Class, Method], etc.).
     * @param  array                  $parameters  Optional manual parameters to pass to the callback.
     * @return mixed                               The result of the callback execution.
     * @throws ReflectionException  If the callback cannot be introspected.
     * @throws ContainerException   If the callback is invalid or unresolvable.
     */
    public function call( $callback, array $parameters = [] ) {
        [$resolved_callback, $metadata] = $this->resolve_callback( $callback );

        $args = $this->engine->resolve_dependencies( $metadata->getParameters(), $parameters );

        return call_user_func_array( $resolved_callback, $args );
    }

    /**
     * Resolve the callback and its reflection metadata.
     *
     * @param  mixed  $callback
     * @return array  [callable, \ReflectionFunctionAbstract]
     * @throws ReflectionException  If reflection fails.
     * @throws ContainerException   If the callback is not callable.
     */
    protected function resolve_callback( $callback ) {
        // 1. Handle Array Callbacks [class, method] or [instance, method]
        if ( is_array( $callback ) ) {
            return $this->resolve_array_callback( $callback );
        }

        // 2. Handle String Callbacks "Class::method"
        if ( is_string( $callback ) && strpos( $callback, '::' ) !== false ) {
            return $this->resolve_array_callback( explode( '::', $callback ) );
        }

        // 3. Handle Invokable Objects
        if ( is_object( $callback ) && ! ( $callback instanceof Closure ) ) {
            $ref = $this->get_method_reflection( $callback, '__invoke' );
            return [$callback, $ref];
        }

        // 4. Handle Closures and simple string functions
        if ( ! is_callable( $callback ) ) {
            throw new ContainerException( "The provided callback is not callable." );
        }

        return [$callback, new ReflectionFunction( $callback )];
    }

    /**
     * Resolve an array-based callback [Class/ID/Object, Method].
     * 
     * If a class name or service ID is provided, it tries to resolve an instance 
     * from the container before reflecting on the method.
     *
     * @param  array  $callback  The [target, method] array.
     * @return array             [callable, \ReflectionMethod]
     * @throws ReflectionException  If reflection fails.
     * @throws ContainerException   If the class, service ID, or method is invalid.
     */
    protected function resolve_array_callback( array $callback ) {
        [$class_or_id, $method] = $callback;

        // 1. Determine the class name and instance for reflection.
        if ( is_object( $class_or_id ) ) {
            $class    = get_class( $class_or_id );
            $instance = $class_or_id;
        } else {
            // Resolve the terminal ID (in case it's an alias).
            $class    = $this->container->resolved_id( $class_or_id );
            $instance = null;

            if ( ! class_exists( $class ) && ! interface_exists( $class ) ) {
                // Check if the identifier is a bound service that can be instantiated.
                if ( $this->container->has( $class_or_id ) ) {
                    $instance = $this->container->get( $class_or_id );
                    $class    = get_class( $instance );
                } else {
                    throw new ContainerException( "Class or service ID {$class} not found." );
                }
            }
        }

        $ref = $this->get_method_reflection( $class, $method );

        // 2. If the method is non-static and we don't have an instance yet, resolve one.
        if ( ! $ref->isStatic() && ! $instance ) {
            $instance = $this->container->get( $class );
        }

        return [ $instance ? [$instance, $method] : [$class, $method], $ref ];
    }

    /**
     * Get reflection of a method, with caching.
     *
     * @param  object|string  $class
     * @param  string         $method
     * @return ReflectionMethod
     * @throws ReflectionException  If reflection fails.
     * @throws ContainerException   If the method is not public.
     */
    protected function get_method_reflection( $class, string $method ): ReflectionMethod {
        $class_name = is_object( $class ) ? get_class( $class ) : $class;
        $cache_key  = $class_name . '::' . $method;

        if ( $cached = $this->engine->get_cached_method( $cache_key ) ) {
            return $cached;
        }

        $ref = new ReflectionMethod( $class_name, $method );
        
        if ( ! $ref->isPublic() ) {
            throw new ContainerException( "Method {$class_name}::{$method} is not public." );
        }

        if ( $ref->isAbstract() ) {
            throw new ContainerException( "Cannot call abstract method {$class_name}::{$method}." );
        }

        $this->engine->cache_method( $cache_key, $ref );

        return $ref;
    }
}
