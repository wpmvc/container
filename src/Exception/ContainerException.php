<?php

namespace WpMVC\Container\Exception;

defined( 'ABSPATH' ) || exit;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ContainerException extends Exception implements ContainerExceptionInterface
{
}
