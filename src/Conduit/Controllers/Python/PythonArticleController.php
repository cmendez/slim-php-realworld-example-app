<?php

declare(strict_types=1);

namespace Conduit\Controllers\Python;

use Conduit\Exceptions\PythonServiceException;
use Slim\Http\Request;
use Slim\Http\Response;

class PythonArticleController
{
    /** @var \Conduit\Services\Python\PythonClient */
    private $pythonClient;

    public function __construct(\Slim\Container $container)
    {
        $this->pythonClient = $container->get('pythonClient');
    }

    public function index(Request $request, Response $response)
    {
        try {
            return $response->withJson([
                'articles' => $this->pythonClient->getArticles(),
                'path' => 'angular-php-python',
            ]);
        } catch (PythonServiceException $exception) {
            return $response->withJson([
                'error' => $exception->getMessage(),
            ], $exception->getHttpStatus());
        }
    }
}
