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
        // Definir las palabras positivas y negativas
        $negative_words=['malo','pesimo','inutil','error','odio','horrible','terrible','desastre','decepcionate','incorrecto','falso','confuso','equivocado','pobre','mediocre','basura','frustrante','problema','dificil','lento'];
        $positive_words=['increible','excelente','bueno','util','genial','fantastico','maravilloso','perfecto','impresionante','claro','preciso','valioso','interesante','recomendado','gracias','felicitaciones','mejora','acierto','facil','correcto'];
        
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

        // Obtener el body del comentario
        $body = $data['body'];
        //Normalizar el contenido del comentario, quitar tildes y mayusculas
        $body = strtolower($body);
        $caracteres_con_tilde = ['á', 'é', 'í', 'ó', 'ú'];
        $caracteres_sin_tilde = ['a', 'e', 'i', 'o', 'u'];
        $body = str_replace($caracteres_con_tilde, $caracteres_sin_tilde, $body);
        //sumar, restar puntaje si encuentra las palabras en las listas
        $score = 1; // puntaje base solo por crear el comentario

        // Dividir el cuerpo del comentario en palabras
        $words = explode(' ', $body);

        // Recorrer todas las palabras del comentario
        foreach ($words as $word) {
            if (in_array($word, $positive_words)) {
                $score += 2;
            }
            if (in_array($word, $negative_words)) {
                $score -= 2;
            }
        }
        // Actualizar el popularity_score del artículo
        $article->popularity_score = $article->popularity_score + $score;
        $article->save();
        //---
        $comment = Comment::create([
            'body'       => $body,
            'user_id'    => $requestUser->id,
            'article_id' => $article->id,
            'score'      => $score,
        ]);

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

        // Antes de borrar restablecer el puntaje del artículo
        // Recalcular el score
        $article = $comment->article;
        $article->popularity_score = $article->popularity_score - $comment->score;
        $article->save();
        
        $comment->delete();

        return $response->withJson([], 200);
    }

}