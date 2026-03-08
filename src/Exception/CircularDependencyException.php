<?php
/**
 * CircularDependencyException class.
 *
 * @package WpMVC\Container
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class CircularDependencyException
 *
 * Exception thrown when a circular dependency is detected during resolution.
 *
 * @package WpMVC\Container\Exception
 */
class CircularDependencyException extends ContainerException
{
}
