<?php

namespace WpMVC;

defined( 'ABSPATH' ) || exit;

use ReflectionClass;
use ReflectionNamedType;

/**
 * Lightweight Dependency Injection Container
 * Provides auto-registration and singleton behavior on get()
 */
class Container
{
    /**
     * @var array
     */
    protected $instances = [];

    /**
     * Get a service from the container.
     * Auto-registers the service as a singleton if not already registered.
     *
     * @param string $id
     * @param array $params
     * @return mixed
     * @throws \ReflectionException
     */
    public function get( string $id, array $params = [] ) {
        if ( isset( $this->instances[$id] ) ) {
            return $this->instances[$id];
        }

        $instance             = $this->resolve( $id, $params );
        $this->instances[$id] = $instance;

        return $instance;
    }

    /**
     * @var array
     */
    protected $resolving = [];

    /**
     * Resolve a service instance (without storing as singleton).
     *
     * @param string $id
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    protected function resolve( string $id, array $params = [] ) {
        if ( ! class_exists( $id ) ) {
            throw new \Exception( "Service not found: {$id}" );
        }

        if ( isset( $this->resolving[$id] ) ) {
            throw new \Exception( "Circular dependency detected while resolving: {$id}" );
        }

        $this->resolving[$id] = true;

        try {
            $ref = new ReflectionClass( $id );

            if ( ! $ref->isInstantiable() ) {
                throw new \Exception( "Class is not instantiable: {$id}" );
            }

            $constructor = $ref->getConstructor();
            $args        = [];

            if ( $constructor ) {
                $args = $this->resolve_dependencies( $constructor, $params );
            }

            return $ref->newInstanceArgs( $args );
        } finally {
            unset( $this->resolving[$id] );
        }
    }

    /**
     * Set a service instance directly.
     *
     * @param string $id
     * @param mixed $service
     * @return void
     */
    public function set( string $id, $service ): void {
        $this->instances[$id] = $service;
    }

    /**
     * Check if container has a service (either instance or class exists)
     *
     * @param string $id
     * @return bool
     */
    public function has( string $id ): bool {
        return isset( $this->instances[$id] ) || class_exists( $id );
    }

    /**
     * Create a new instance of the given class (Factory).
     * Does not store the instance as a singleton.
     *
     * @param string $abstract
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    public function make( string $abstract, array $parameters = [] ) {
        return $this->resolve( $abstract, $parameters );
    }

    /**
     * Call a callback with dependency injection.
     *
     * @param callable|array|string $callback
     * @param array $parameters
     * @return mixed
     * @throws \ReflectionException
     */
    public function call( $callback, array $parameters = [] ) {
        if ( is_array( $callback ) ) {
            $class  = is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0];
            $method = $callback[1];
            $ref    = new \ReflectionMethod( $class, $method );

            if ( is_string( $callback[0] ) && ! $ref->isStatic() ) {
                $callback[0] = $this->get( $callback[0] );
            }
        } elseif ( is_string( $callback ) && strpos( $callback, '::' ) !== false ) {
            $parts = explode( '::', $callback );
            $ref   = new \ReflectionMethod( $parts[0], $parts[1] );

            if ( ! $ref->isStatic() ) {
                $instance = $this->get( $parts[0] );
                $callback = [$instance, $parts[1]];
            }
        } elseif ( is_object( $callback ) && ! ( $callback instanceof \Closure ) ) {
            $ref = new \ReflectionMethod( $callback, '__invoke' );
        } else {
            $ref = new \ReflectionFunction( $callback );
        }

        $args = $this->resolve_dependencies( $ref, $parameters );

        return call_user_func_array( $callback, $args );
    }

    /**
     * Resolve dependencies for a reflection function/method.
     * 
     * @param \ReflectionFunctionAbstract $ref
     * @param array $parameters
     * @return array
     */
    protected function resolve_dependencies( \ReflectionFunctionAbstract $ref, array $parameters = [] ): array {
        $args = [];
        foreach ( $ref->getParameters() as $param ) {
            $name = $param->getName();

            if ( array_key_exists( $name, $parameters ) ) {
                $args[] = $parameters[$name];
                continue;
            }

            $type = $param->getType();
            if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
                $id = $type->getName();

                // Avoid infinite recursion / simple circular dependency check could be added here
                // For now, we trust get() to handle it (standard recursion)
                $args[] = $this->get( $id );
                continue;
            }

            if ( $param->isDefaultValueAvailable() ) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // If we cannot resolve it, pass null or throw?
            // PHP will throw ArgumentCountError if we don't pass anything for a required param.
            // We'll let that happen as it's the most correct behavior for missing dependencies.
        }
        return $args;
    }
}
