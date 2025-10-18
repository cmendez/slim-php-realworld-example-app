<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
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
    }

    public function popular(Request $request, Response $response, array $args)
    {
        $requestUser = $this->auth->requestUser($request);
        $requestUserId = optional($requestUser)->id;

        // Traer los artículos con relaciones necesarias (autor, tags, etc.)
        $articles = Article::query()
            ->with(['user', 'tags', 'favorites'])
            ->orderBy('popularity_score', 'desc')
            ->limit(20)
            ->get();

        // Usamos el transformer para devolver la estructura completa
        $data = $this->fractal->createData(
            new \League\Fractal\Resource\Collection($articles, new ArticleTransformer($requestUserId))
        )->toArray();

        return $response->withJson([
            'articles' => $data['data'],
            'articlesCount' => count($articles)
        ]);
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

        $requestUser->favoriteArticles()->syncWithoutDetaching($article->id);

        // Incrementar el score de popularidad en 2 puntos
        $article->increment('popularity_score', 2);

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

        $requestUser->favoriteArticles()->detach($article->id);

        // Decrementar el score de popularidad en 2 puntos
        $article->decrement('popularity_score', 2);

        $data = $this->fractal->createData(new Item($article, new ArticleTransformer($requestUser->id)))->toArray();

        return $response->withJson(['article' => $data]);
    }

}