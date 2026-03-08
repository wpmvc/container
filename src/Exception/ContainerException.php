<?php
/**
 * ContainerException class.
 *
 * @package WpMVC\Container
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container\Exception;

defined( 'ABSPATH' ) || exit;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * Class ContainerException
 *
 * Base exception for all container-related errors.
 *
 * @package WpMVC\Container\Exception
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
}
