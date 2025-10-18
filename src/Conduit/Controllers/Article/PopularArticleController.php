<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
use Conduit\Transformers\ArticleTransformer;
use League\Fractal\Resource\Collection;
use Slim\Http\Request;
use Slim\Http\Response;

class PopularArticleController
{
    protected $auth;
    protected $fractal;

    public function __construct(\Slim\Container $container)
    {
        $this->auth = $container->get('auth');
        $this->fractal = $container->get('fractal');
    }

    /**
     * Listado de artículos ordenados por popularidad
     */
    public function index(Request $request, Response $response, array $args)
    {
        $requestUserId = optional($this->auth->requestUser($request))->id;

        $limit = (int) $request->getParam('limit', 20);
        $offset = (int) $request->getParam('offset', 0);

        // Primero contamos sin limit ni offset
        $totalCount = Article::count();

        // Luego obtenemos artículos ordenados por popularidad
        $articles = Article::query()
            ->with(['tags', 'user'])
            ->orderByDesc('popularity_score')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $data = $this->fractal->createData(
            new Collection($articles, new ArticleTransformer($requestUserId))
        )->toArray();

        return $response->withJson([
            'articles' => $data['data'],
            'articlesCount' => $totalCount,
        ]);
    }
}
