<?php

namespace WpMVC\Container\Exception;

defined( 'ABSPATH' ) || exit;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
}
