<?php

declare(strict_types=1);

namespace MailFlow\Core\Exception;

use Psr\Container\ContainerExceptionInterface;

class ContainerException extends \Exception implements ContainerExceptionInterface
{
}