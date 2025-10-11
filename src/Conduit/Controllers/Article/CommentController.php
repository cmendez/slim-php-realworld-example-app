<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
use Conduit\Models\Comment;
use Conduit\Transformers\CommentTransformer;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Respect\Validation\Validator as v;
use Slim\Http\Request;
use Slim\Http\Response;

class CommentController
{
    /** @var \Conduit\Validation\Validator */
    protected $validator;
    /** @var \Illuminate\Database\Capsule\Manager */
    protected $db;
    /** @var \Conduit\Services\Auth\Auth */
    protected $auth;
    /** @var \League\Fractal\Manager */
    protected $fractal;

    public function __construct(\Slim\Container $container)
    {
        $this->auth      = $container->get('auth');
        $this->fractal   = $container->get('fractal');
        $this->validator = $container->get('validator');
        $this->db        = $container->get('db');
    }

    /**
     * GET /api/articles/{slug}/comments
     */
    public function index(Request $request, Response $response, array $args)
    {
        $requestUserId = optional($this->auth->requestUser($request))->id;

        $article = Article::query()
            ->with('comments')
            ->where('slug', $args['slug'])
            ->firstOrFail();

        $data = $this->fractal->createData(
            new Collection($article->comments, new CommentTransformer($requestUserId))
        )->toArray();

        return $response->withJson(['comments' => $data['data']]);
    }

    /**
     * POST /api/articles/{slug}/comments
     */
    public function store(Request $request, Response $response, array $args)
    {
        $article     = Article::query()->where('slug', $args['slug'])->firstOrFail();
        $requestUser = $this->auth->requestUser($request);

        if (is_null($requestUser)) {
            return $response->withJson([], 401);
        }

        $this->validator->validateArray(
            $data = $request->getParam('comment'),
            ['body' => v::notEmpty()]
        );

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }

        $comment = Comment::create([
            'body'       => $data['body'],
            'user_id'    => $requestUser->id,
            'article_id' => $article->id,
        ]);

        // Recalcular popularidad después de crear el comentario
        $this->recalcPopularity($article);

        $data = $this->fractal
            ->createData(new Item($comment, new CommentTransformer()))
            ->toArray();

        return $response->withJson(['comment' => $data]);
    }

    /**
     * DELETE /api/articles/{slug}/comments/{id}
     */
    public function destroy(Request $request, Response $response, array $args)
    {
        $comment     = Comment::query()->findOrFail($args['id']);
        $requestUser = $this->auth->requestUser($request);

        if (is_null($requestUser)) {
            return $response->withJson([], 401);
        }

        if ($requestUser->id != $comment->user_id) {
            return $response->withJson(['message' => 'Forbidden'], 403);
        }

        $article = Article::query()->find($comment->article_id);

        $comment->delete();

        // Recalcular popularidad después de eliminar el comentario
        if ($article) {
            $this->recalcPopularity($article);
        }

        return $response->withJson([], 200);
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
