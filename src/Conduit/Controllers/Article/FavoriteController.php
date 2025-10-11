<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
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

    public function __construct(\Slim\Container $container)
    {
        $this->auth    = $container->get('auth');
        $this->fractal = $container->get('fractal');
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

        // Recalcular popularidad después de marcar favorito
        $this->recalcPopularity($article);

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

        // Recalcular popularidad después de quitar favorito
        $this->recalcPopularity($article);

        $data = $this->fractal
            ->createData(new Item($article, new ArticleTransformer($requestUser->id)))
            ->toArray();

        return $response->withJson(['article' => $data]);
    }

    /**
     * Recalcula y guarda popularity_score (simple, recálculo completo).
     */
    private function recalcPopularity(Article $article): void
    {
        // --- 1) Favoritos: +2 por cada favorito ---
        $score = (int) $article->favorites()->count() * 2;

        // --- 2) Comentarios: +1 base por comentario, +2 por positiva, -2 por negativa ---
        $positivas = ['bueno','excelente','genial','positivo','util','interesante','claro'];
        $negativas = ['malo','terrible','pesimo','negativo','confuso','aburrido','falso'];

        // Tomamos solo los bodies, sin cachear relaciones previas
        $bodies = $article->comments()->pluck('body')->all();

        foreach ($bodies as $body) {
            $txt  = is_string($body) ? $body : '';
            // normalizar: minúsculas + quitar tildes + limpiar signos
            $txt  = mb_strtolower($txt, 'UTF-8');
            $norm = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
            if ($norm === false) { $norm = $txt; }
            $norm = preg_replace('/[^a-z0-9\s]/', ' ', $norm);

            $tokens = preg_split('/\s+/', trim($norm)) ?: [];

            // +1 base por este comentario
            $cScore = 1;

            // sumar/restar por palabras clave
            foreach ($tokens as $w) {
                if ($w === '') continue;
                if (in_array($w, $positivas, true)) { $cScore += 2; continue; }
                if (in_array($w, $negativas, true)) { $cScore -= 2; }
            }

            $score += $cScore;
        }

        // Guardar (asignación directa NO requiere $fillable)
        $article->popularity_score = $score;
        $article->save();
    }
}
