<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
use Conduit\Transformers\ArticleTransformer;
use League\Fractal\Resource\Collection;
use Slim\Http\Request;
use Slim\Http\Response;

class PopularController
{
    /** @var \Conduit\Services\Auth\Auth */
    protected $auth;
    /** @var \League\Fractal\Manager */
    protected $fractal;

    /**
     * PopularController constructor.
     *
     * @param \Slim\Container $container
     */
    public function __construct(\Slim\Container $container)
    {
        $this->auth = $container->get('auth');
        $this->fractal = $container->get('fractal');
    }

    /**
     * Get popular articles ordered by popularity_score
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     * @param array               $args
     *
     * @return \Slim\Http\Response
     */
    public function index(Request $request, Response $response, array $args)
    {
        $requestUserId = optional($this->auth->requestUser($request))->id;

        // Get pagination parameters
        $limit = $request->getParam('limit', 20);
        $offset = $request->getParam('offset', 0);

        // Query articles ordered by popularity_score DESC, then by title ASC
        $articles = Article::query()
            ->orderBy('popularity_score', 'desc')
            ->orderBy('title', 'asc')
            ->skip($offset)
            ->take($limit)
            ->get();

        // Get total count for articlesCount
        $totalCount = Article::query()->count();

        // Transform data
        $articlesCollection = new Collection($articles, new ArticleTransformer($requestUserId));
        $data = $this->fractal->createData($articlesCollection)->toArray();

        return $response->withJson([
            'articles' => $data['data'],
            'articlesCount' => $totalCount,
        ]);
    }
}
