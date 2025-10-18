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

        $comment = Comment::create([
            'body'       => $data['body'],
            'user_id'    => $requestUser->id,
            'article_id' => $article->id,
        ]);

        // Calcular y sumar puntaje al artículo
        $puntaje = $this->calcularPuntajeComentario($comment->body);
        $article->increment('popularity_score', $puntaje);

        // Devolver respuesta
        $data = $this->fractal->createData(
            new Item($comment, new CommentTransformer($requestUser->id))
        )->toArray();

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

        // Obtener el artículo antes de eliminar
        $article = Article::find($comment->article_id);

        // Calcular y restar puntaje
        $puntaje = $this->calcularPuntajeComentario($comment->body);
        if ($article) {
            $article->decrement('popularity_score', $puntaje);
        }
        // Eliminar el comentario

        $comment->delete();

        return $response->withJson([], 200);
    }


    private function calcularPuntajeComentario($texto)
    {
        $positivas = [
            'increible', 'excelente', 'bueno', 'util', 'genial', 'fantastico',
            'maravilloso', 'perfecto', 'impresionante', 'claro', 'preciso',
            'valioso', 'interesante', 'recomendado', 'gracias', 'felicitaciones',
            'mejora', 'acierto', 'facil', 'correcto'
        ];

        $negativas = [
            'malo', 'pesimo', 'inutil', 'error', 'odio', 'horrible', 'terrible',
            'desastre', 'decepcionante', 'incorrecto', 'falso', 'confuso',
            'equivocado', 'pobre', 'mediocre', 'basura', 'frustrante',
            'problema', 'dificil', 'lento'
        ];

        // Normalizar texto a minúsculas
        $texto = mb_strtolower($texto, 'UTF-8');
        
        // Remover tildes
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $texto
        );
        
        // Remover signos de puntuación
        $texto = preg_replace('/[.,!?;:¡¿()"]/', ' ', $texto);
        
        // Separar palabras y eliminar espacios vacíos
        $palabras = array_filter(explode(' ', $texto), function($p) {
            return trim($p) !== '';
        });

        $puntaje = 1; // base
        
        foreach ($palabras as $palabra) {
            $palabra = trim($palabra);
            if (in_array($palabra, $positivas)) {
                $puntaje += 2;
            }
            if (in_array($palabra, $negativas)) {
                $puntaje -= 2;
            }
        }
        
        return $puntaje;
    }


}