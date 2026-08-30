<?php

declare(strict_types=1);

namespace Conduit\Middleware;

use Interop\Container\ContainerInterface;
use Slim\DeferredCallable;

class OptionalAuth
{

    /**
     * @var \Interop\Container\ContainerInterface
     */
    private $container;

    /**
     * OptionalAuth constructor.
     *
     * @param \Interop\Container\ContainerInterface $container
     *
     * @internal param \Slim\Middleware\JwtAuthentication $jwt
     */
    public function __construct(\Slim\Container $container)
    {
        $this->container = $container;
    }

    /**
     * OptionalAuth middleware invokable class to verify JWT token when present in Request
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        if ($request->hasHeader('Authorization')) {
            $callable = new DeferredCallable($this->container->get('jwt'), $this->container);

            return $callable($request, $response, $next);
        }

        return $next($request, $response);
    }
}
