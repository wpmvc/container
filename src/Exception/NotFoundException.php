<?php
/**
 * NotFoundException class.
 *
 * @package WpMVC\Container
 * @author  WpMVC
 * @license MIT
 */

namespace WpMVC\Container\Exception;

defined( 'ABSPATH' ) || exit;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class NotFoundException
 *
 * Exception thrown when a requested service or class is not found in the container.
 *
 * @package WpMVC\Container\Exception
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface
{
}
