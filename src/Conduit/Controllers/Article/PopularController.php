<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
use Conduit\Transformers\ArticleTransformer;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
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

        // Calculate page number for paginator
        $page = floor($offset / $limit) + 1;

        // Query articles ordered by popularity_score DESC, then by title ASC
        $articlesQuery = Article::query()
            ->orderBy('popularity_score', 'desc')
            ->orderBy('title', 'asc');

        // Paginate results
        $paginator = $articlesQuery->paginate($limit, ['*'], 'page', $page);

        // Transform data
        $articles = new Collection($paginator->items(), new ArticleTransformer($requestUserId));
        $articles->setPaginator(new IlluminatePaginatorAdapter($paginator));

        $data = $this->fractal->createData($articles)->toArray();

        return $response->withJson([
            'articles' => $data['data'],
            'articlesCount' => $paginator->total(),
        ]);
    }
}
