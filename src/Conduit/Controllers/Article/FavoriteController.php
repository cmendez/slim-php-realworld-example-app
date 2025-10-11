<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
use Conduit\Transformers\ArticleTransformer;
use Slim\Http\Request;
use Slim\Http\Response;

class FavoriteController
{
    /** @var \Conduit\Services\Auth\Auth */
    protected $auth;

    /** @var \League\Fractal\Manager */
    protected $fractal;

    /** @var \Conduit\Services\Popularity\PopularityService */
    protected $popularity;

    /**
     * @param \Slim\Container $container
     */
    public function __construct(\Slim\Container $container)
    {
        $this->auth       = $container->get('auth');
        $this->fractal    = $container->get('fractal');
        $this->popularity = $container->get('popularity'); // 👈 inyectamos el servicio
    }

    /**
     * POST /api/articles/{slug}/favorite
     */
    public function store(Request $request, Response $response, array $args)
    {
        $article     = Article::query()->where('slug', $args['slug'])->firstOrFail();
        $requestUser = $this->auth->requestUser($request);

        if (is_null($requestUser)) {
            return $response->withJson([], 401);
        }

        // crea el favorito (sin duplicar)
        $requestUser->favoriteArticles()->syncWithoutDetaching($article->id);

        // 👇 actualiza el índice de popularidad (+2 puntos)
        $this->popularity->applyFavoriteDelta($article, +1);

        $data = $this->fractal
            ->createData(new ArticleTransformer($requestUser->id)->item($article))
            ->toArray();

        return $response->withJson(['article' => $data]);
    }

    /**
     * DELETE /api/articles/{slug}/favorite
     */
    public function destroy(Request $request, Response $response, array $args)
    {
        $article     = Article::query()->where('slug', $args['slug'])->firstOrFail();
        $requestUser = $this->auth->requestUser($request);

        if (is_null($requestUser)) {
            return $response->withJson([], 401);
        }

        // elimina el favorito
        $requestUser->favoriteArticles()->detach($article->id);

        $this->popularity->applyFavoriteDelta($article, -1);

        $data = $this->fractal
            ->createData(new ArticleTransformer($requestUser->id)->item($article))
            ->toArray();

        return $response->withJson(['article' => $data]);
    }
}
