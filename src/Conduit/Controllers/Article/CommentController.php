<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
use Conduit\Models\Comment;
use Conduit\Services\Article\PopularityService; // <-- IMPORTAMOS EL SERVICIO
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
    /** @var \Conduit\Services\Article\PopularityService */ // <-- AÑADIMOS LA PROPIEDAD PARA EL SERVICIO
    protected $popularityService;

    public function __construct(\Slim\Container $container)
    {
        $this->auth      = $container->get('auth');
        $this->fractal   = $container->get('fractal');
        $this->validator = $container->get('validator');
        $this->db        = $container->get('db');
        $this->popularityService = $container->get('popularityService'); // <-- LO OBTENEMOS DEL CONTENEDOR
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

        // USAMOS EL SERVICIO EN LUGAR DEL MÉTODO ANTIGUO
        $this->popularityService->updateScore($article);

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
            // USAMOS EL SERVICIO EN LUGAR DEL MÉTODO ANTIGUO
            $this->popularityService->updateScore($article);
        }

        return $response->withJson([], 200);
    }

}