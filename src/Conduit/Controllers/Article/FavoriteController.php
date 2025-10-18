<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
use Conduit\Services\Article\PopularityService; // <-- IMPORTAMOS EL SERVICIO
use Conduit\Transformers\ArticleTransformer;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;

class FavoriteController
{
    /** @var \Conduit\Services\Auth\Auth */
    protected $auth;

    /** @var \League\Fractal\Manager */
    protected $fractal;

    /** @var \Conduit\Services\Article\PopularityService */ // <-- AÑADIMOS LA PROPIEDAD PARA EL SERVICIO
    protected $popularityService;

    public function __construct(\Slim\Container $container)
    {
        $this->auth    = $container->get('auth');
        $this->fractal = $container->get('fractal');
        $this->popularityService = $container->get('popularityService'); // <-- LO OBTENEMOS DEL CONTENEDOR
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

        // Evitar duplicados en la pivote
        $requestUser->favoriteArticles()->syncWithoutDetaching([$article->id]);

        // USAMOS EL SERVICIO EN LUGAR DEL MÉTODO ANTIGUO
        $this->popularityService->updateScore($article);

        $data = $this->fractal
            ->createData(new Item($article, new ArticleTransformer($requestUser->id)))
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

        $requestUser->favoriteArticles()->detach($article->id);

        // USAMOS EL SERVICIO EN LUGAR DEL MÉTODO ANTIGUO
        $this->popularityService->updateScore($article);

        $data = $this->fractal
            ->createData(new Item($article, new ArticleTransformer($requestUser->id)))
            ->toArray();

        return $response->withJson(['article' => $data]);
    }

}