<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
use Conduit\Services\PopularityService;
use Conduit\Transformers\ArticleTransformer;
use Interop\Container\ContainerInterface;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;

class FavoriteController
{

    /** @var \Conduit\Services\Auth\Auth */
    protected $auth;
    /** @var \League\Fractal\Manager */
    protected $fractal;
    /** @var \Conduit\Services\PopularityService */
    protected $popularityService;

    /**
     * UserController constructor.
     *
     * @param \Interop\Container\ContainerInterface $container
     *
     * @internal param $auth
     */
    public function __construct(\Slim\Container $container)
    {
        $this->auth = $container->get('auth');
        $this->fractal = $container->get('fractal');
        $this->popularityService = $container->get('popularityService');
    }

    /**
     * Create a new article's favorite
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     *
     * @param array               $args
     *
     * @return \Slim\Http\Response
     */
    public function store(Request $request, Response $response, array $args)
    {
        $article = Article::query()->where('slug', $args['slug'])->firstOrFail();
        $requestUser = $this->auth->requestUser($request);

        if (is_null($requestUser)) {
            return $response->withJson([], 401);
        }

        // Check if already favorited to avoid duplicate increments
        $alreadyFavorited = $article->isFavoritedByUser($requestUser->id);
        
        $requestUser->favoriteArticles()->syncWithoutDetaching($article->id);
        
        // Only increment if it wasn't already favorited
        if (!$alreadyFavorited) {
            $this->popularityService->incrementFavorite($article);
            $article->refresh(); // Reload to get updated popularity_score
        }

        $data = $this->fractal->createData(new Item($article, new ArticleTransformer($requestUser->id)))->toArray();

        return $response->withJson(['article' => $data]);

    }

    /**
     * Delete A Favorite
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     * @param array               $args
     *
     * @return \Slim\Http\Response
     */
    public function destroy(Request $request, Response $response, array $args)
    {
        $article = Article::query()->where('slug', $args['slug'])->firstOrFail();
        $requestUser = $this->auth->requestUser($request);

        if (is_null($requestUser)) {
            return $response->withJson([], 401);
        }

        // Check if it was favorited before removing
        $wasFavorited = $article->isFavoritedByUser($requestUser->id);
        
        $requestUser->favoriteArticles()->detach($article->id);
        
        // Only decrement if it was actually favorited
        if ($wasFavorited) {
            $this->popularityService->decrementFavorite($article);
            $article->refresh(); // Reload to get updated popularity_score
        }

        $data = $this->fractal->createData(new Item($article, new ArticleTransformer($requestUser->id)))->toArray();

        return $response->withJson(['article' => $data]);
    }

}