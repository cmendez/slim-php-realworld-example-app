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
    protected $validator;
    protected $db;
    protected $auth;
    protected $fractal;

    public function __construct(\Slim\Container $container)
    {
        $this->auth = $container->get('auth');
        $this->fractal = $container->get('fractal');
        $this->validator = $container->get('validator');
        $this->db = $container->get('db');
    }

    /**
     * Mostrar todos los comentarios de un artículo
     */
    public function index(Request $request, Response $response, array $args)
    {
        $requestUserId = optional($this->auth->requestUser($request))->id;
        $article = Article::query()->with('comments')->where('slug', $args['slug'])->firstOrFail();

        $data = $this->fractal->createData(
            new Collection($article->comments, new CommentTransformer($requestUserId))
        )->toArray();

        return $response->withJson(['comments' => $data['data']]);
    }

    /**
     * Crear un nuevo comentario y ajustar el puntaje de popularidad
     */
    public function store(Request $request, Response $response, array $args)
    {
        // Palabras positivas y negativas
        $negative_words = ['malo','pesimo','inutil','error','odio','horrible','terrible','desastre','decepcionante','incorrecto','falso','confuso','equivocado','pobre','mediocre','basura','frustrante','problema','dificil','lento'];
        $positive_words = ['increible','excelente','bueno','util','genial','fantastico','maravilloso','perfecto','impresionante','claro','preciso','valioso','interesante','recomendado','gracias','felicitaciones','mejora','acierto','facil','correcto'];

        $article = Article::query()->where('slug', $args['slug'])->firstOrFail();
        $requestUser = $this->auth->requestUser($request);

        if (is_null($requestUser)) {
            return $response->withJson([], 401);
        }

        $this->validator->validateArray($data = $request->getParam('comment'), [
            'body' => v::notEmpty(),
        ]);

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }

        // ✅ Guardar el texto original (para mostrarlo intacto al usuario)
        $original_body = $data['body'];

        // --- Versión limpia para análisis ---
        $body_clean = mb_strtolower($original_body, 'UTF-8');

        // Quitar tildes
        $caracteres_con_tilde = ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'];
        $caracteres_sin_tilde = ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'];
        $body_clean = str_replace($caracteres_con_tilde, $caracteres_sin_tilde, $body_clean);

        // Quitar puntuación y caracteres especiales
        $body_clean = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $body_clean);

        // Quitar espacios múltiples
        $body_clean = trim(preg_replace('/\s+/u', ' ', $body_clean));

        // Tokenizar
        $words = preg_split('/\s+/', $body_clean, -1, PREG_SPLIT_NO_EMPTY);

        // --- Calcular el puntaje ---
        $score = 1; // puntaje base solo por comentar

        foreach ($words as $word) {
            if (in_array($word, $positive_words)) {
                $score += 2;
            }
            if (in_array($word, $negative_words)) {
                $score -= 2;
            }
        }

        // Actualizar el puntaje del artículo
        $article->popularity_score = $article->popularity_score + $score;
        $article->save();

        // ✅ Crear el comentario usando el texto original (no el limpio)
        $comment = Comment::create([
            'body'       => $original_body,
            'user_id'    => $requestUser->id,
            'article_id' => $article->id,
            'score'      => $score,
        ]);

        $data = $this->fractal->createData(new Item($comment, new CommentTransformer()))->toArray();

        return $response->withJson(['comment' => $data]);
    }

    /**
     * Eliminar un comentario y ajustar el puntaje del artículo
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

        // Revertir el puntaje
        $article = $comment->article;
        $article->popularity_score = $article->popularity_score - $comment->score;
        $article->save();

        $comment->delete();

        return $response->withJson([], 200);
    }
}