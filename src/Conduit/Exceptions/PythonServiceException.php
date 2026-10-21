<?php

declare(strict_types=1);

namespace Conduit\Exceptions;

class PythonServiceException extends \RuntimeException
{
    /** @var int */
    private $httpStatus;

    public function __construct($message, $httpStatus)
    {
        parent::__construct($message);
        $this->httpStatus = (int) $httpStatus;
    }

    public function getHttpStatus()
    {
        return $this->httpStatus;
    }
}
