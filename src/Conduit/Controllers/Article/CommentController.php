<?php

namespace Conduit\Controllers\Article;

use Conduit\Models\Article;
use Conduit\Models\Comment;
use Conduit\Transformers\ArticleTransformer;
use Conduit\Transformers\CommentTransformer;
use Interop\Container\ContainerInterface;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;

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
        $this->validator = $container->get('validator');
        $this->db = $container->get('db');
    }

    /**
     * Return a all Comment for an article
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

        $article = Article::query()->with('comments')->where('slug', $args['slug'])->firstOrFail();

        $data = $this->fractal->createData(new Collection($article->comments,
            new CommentTransformer($requestUserId)))->toArray();

        return $response->withJson(['comments' => $data['data']]);
    }

    /**
     * Create a new comment
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

    $this->validator->validateArray($data = $request->getParam('comment'),
        [
            'body' => v::notEmpty(),
        ]);

    if ($this->validator->failed()) {
        return $response->withJson(['errors' => $this->validator->getErrors()], 422);
    }

    $ListGood = ['increíble', 'excelente', 'bueno', 'util', 'genial', 'fantástico', 'maravilloso', 'perfecto', 'impresionante', 'claro', 'preciso', 'valioso', 'interesante', 'recomendado', 'gracias', 'felicitaciones', 'mejora', 'acierto', 'fácil', 'correcto'];
    $ListBad = ['malo', 'pesimo', 'inutil', 'error', 'odio', 'horrible', 'terrible', 'desastre', 'decepcionante', 'incorrecto', 'falso', 'confuso', 'equivocado', 'pobre', 'mediocre', 'basura', 'frustrante', 'problema', 'dificil', 'lento'];
    $bodyNormalized = strtolower($data['body']);
    $bodyNormalized = strtr($bodyNormalized, [
    'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
    ]);
    $words = preg_split('/\s+/', $bodyNormalized);
    $commentScore = 0;
    foreach ($words as $word) {
        if (in_array($word, $ListGood)) {
            $commentScore += 2;
        }
        if (in_array($word, $ListBad)) {
            $commentScore -= 2;
        }
    }
    $commentScore += 1;
    $comment = Comment::create([
        'body'       => $data['body'],
        'user_id'    => $requestUser->id,
        'article_id' => $article->id,
        'score'      => $commentScore,
    ]);
    $article->popularity_score = ($article->popularity_score ?? 0) + $commentScore;
    $article->save();   
    $data = $this->fractal->createData(new Item($comment, new CommentTransformer()))->toArray();
    return $response->withJson(['comment' => $data]);
}

    /**
     * Delete A Comment Endpoint
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     * @param array               $args
     *
     * @return \Slim\Http\Response
     */
    public function destroy(Request $request, Response $response, array $args)
    {
        $comment = Comment::query()->findOrFail($args['id']);
        $requestUser = $this->auth->requestUser($request);

        if (is_null($requestUser)) {
            return $response->withJson([], 401);
        }

        if ($requestUser->id != $comment->user_id) {
            return $response->withJson(['message' => 'Forbidden'], 403);
        }
        $article = $comment->article;
        if ($article) {
            $article->popularity_score = ($article->popularity_score ?? 0) - ($comment->score ?? 0);
            $article->save();
        }

        $comment->delete();

        return $response->withJson([], 200);
    }

}