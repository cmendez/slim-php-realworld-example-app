<?php

declare(strict_types=1);

namespace Conduit\Controllers;

class BaseController
{

    /**
     * @var \Slim\Container
     */
    protected $container;

    /**
     * BaseController constructor.
     *
     * @param \Slim\Container $container
     */
    public function __construct(\Slim\Container $container)
    {
        $this->container = $container;
    }

}