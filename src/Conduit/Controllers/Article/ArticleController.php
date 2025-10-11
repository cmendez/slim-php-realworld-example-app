<?php

namespace Conduit\Controllers\Article;
use Carbon\Carbon;
use Conduit\Models\Article;
use Conduit\Models\Tag;
use Conduit\Transformers\ArticleTransformer;
use Interop\Container\ContainerInterface;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;

class ArticleController
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
     * Return List of Articles
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     * @param array               $args
     *
     * @return \Slim\Http\Response
     */
    public function index(Request $request, Response $response, array $args)
    {
        // TODO Extract the logic of filtering articles to its own class

        $requestUserId = optional($requestUser = $this->auth->requestUser($request))->id;
        $builder = Article::query()->latest()->with(['tags', 'user'])->limit(20);


        if ($request->getUri()->getPath() == '/api/articles/feed') {
            if (is_null($requestUser)) {
                return $response->withJson([], 401);
            }
            $ids = $requestUser->followings->pluck('id');
            $builder->whereIn('user_id', $ids);
        }

        if ($author = $request->getParam('author')) {
            $builder->whereHas('user', function ($query) use ($author) {
                $query->where('username', $author);
            });
        }

        if ($tag = $request->getParam('tag')) {
            $builder->whereHas('tags', function ($query) use ($tag) {
                $query->where('title', $tag);
            });
        }

        if ($favoriteByUser = $request->getParam('favorited')) {
            $builder->whereHas('favorites', function ($query) use ($favoriteByUser) {
                $query->where('username', $favoriteByUser);
            });
        }

        if ($limit = $request->getParam('limit')) {
            $builder->limit($limit);
        }

        if ($offset = $request->getParam('offset')) {
            $builder->offset($offset);
        }

        $articlesCount = $builder->count();
        $articles = $builder->get();

        $data = $this->fractal->createData(new Collection($articles,
            new ArticleTransformer($requestUserId)))->toArray();

        return $response->withJson(['articles' => $data['data'], 'articlesCount' => $articlesCount]);
    }

    /**
     * Return a single Article to get article endpoint
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     * @param array               $args
     *
     * @return \Slim\Http\Response
     */
    public function show(Request $request, Response $response, array $args)
    {
        $requestUserId = optional($this->auth->requestUser($request))->id;

        $article = Article::query()->where('slug', $args['slug'])->firstOrFail();

        $data = $this->fractal->createData(new Item($article, new ArticleTransformer($requestUserId)))->toArray();

        return $response->withJson(['article' => $data]);
    }

    /**
     * Create and store a new Article
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     *
     * @return Response
     */
    public function store(Request $request, Response $response)
    {
        $requestUser = $this->auth->requestUser($request);

        if (is_null($requestUser)) {
            return $response->withJson([], 401);
        }

        $data = $request->getParam('article');

        $this->validator->validateArray($data, [
            'title'       => v::notEmpty(),
            'description' => v::notEmpty(),
            'body'        => v::notEmpty(),
            // Mantenemos la validación para asegurarnos de que el formato es correcto
            'publishDate' => v::optional(v::date()), 
        ]);

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }
        
        // Aquí usamos el constructor solo para los campos que no dan problemas
        $article = new Article([
            'title' => $data['title'],
            'description' => $data['description'],
            'body' => $data['body'],
        ]);
        
        $article->slug = str_slug($article->title);
        $article->user_id = $requestUser->id;

        // Verificamos si 'publishDate' existe y creamos un objeto Carbon explícitamente.
        if (!empty($data['publishDate'])) {
            $article->publish_date = Carbon::parse($data['publishDate']);
        }   
        $article->reading_time = $this->calcReadingTime($data['body'] ?? null);

        $article->save();

        $tagsId = [];
        if (isset($data['tagList'])) {
            foreach ($data['tagList'] as $tag) {
                $tagsId[] = Tag::updateOrCreate(['title' => $tag], ['title' => $tag])->id;
            }
            $article->tags()->sync($tagsId);
        }

        $data = $this->fractal->createData(new Item($article, new ArticleTransformer($requestUser->id)))->toArray();
        
        return $response->withJson(['article' => $data]);
    }

    /**
     * Update Article Endpoint
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     * @param array               $args
     *
     * @return \Slim\Http\Response
     */
    public function update(Request $request, Response $response, array $args)
    {
        $article = Article::query()->where('slug', $args['slug'])->firstOrFail();
        $requestUser = $this->auth->requestUser($request);

        if (is_null($requestUser)) {
            return $response->withJson([], 401);
        }

        if ($requestUser->id != $article->user_id) {
            return $response->withJson(['message' => 'Forbidden'], 403);
        }

        // Obtenemos los datos del request en la variable $params
        $params = $request->getParam('article', []);

        // --- 1. AÑADIR VALIDACIÓN ---
        $this->validator->validateArray($params, [
            // Hacemos que todos los campos sean opcionales en la actualización
            'title'       => v::optional(v::notEmpty()),
            'description' => v::optional(v::notEmpty()),
            'publishDate' => v::optional(v::date()),
        ]);

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }

        // --- 2. ACTUALIZAR CAMPOS INDIVIDUALMENTE ---
        // Esto es más claro y evita problemas con la asignación masiva
        if (isset($params['title'])) {
            $article->title = $params['title'];
            $article->slug = str_slug($params['title']); // Actualizamos el slug si cambia el título
        }

        if (isset($params['description'])) {
            $article->description = $params['description'];
        }

        if (isset($params['body'])) {
            $article->body = $params['body'];
            $article->reading_time = $this->calcReadingTime($params['body']);
        }
        // --- 3. MANEJAR LA FECHA DE PUBLICACIÓN CON CARBON ---
        if (isset($params['publishDate'])) {
            // Si la fecha es un string vacío o nulo, la establecemos como null en la BD.
            // Si no, la parseamos con Carbon.
            $article->publish_date = empty($params['publishDate']) ? null : Carbon::parse($params['publishDate']);
        }

        // --- 4. GUARDAR TODOS LOS CAMBIOS ---
        $article->save();

        $data = $this->fractal->createData(new Item($article, new ArticleTransformer($requestUser->id)))->toArray();

        return $response->withJson(['article' => $data]);
    }

    public function popular(Request $request, Response $response, array $args)
    {
        $requestUserId = optional($this->auth->requestUser($request))->id;

        $limit  = (int) ($request->getParam('limit') ?? 20);
        $offset = (int) ($request->getParam('offset') ?? 0);

        $builder = Article::query()
            ->with(['tags', 'user'])
            ->orderBy('popularity_score', 'desc')
            ->orderBy('title', 'asc')
            ->limit($limit)
            ->offset($offset);

        $articles      = $builder->get();
        $articlesCount = Article::query()->count(); // o count específico si lo prefieres

        $data = $this->fractal->createData(
            new Collection($articles, new ArticleTransformer($requestUserId))
        )->toArray();

        return $response->withJson(['articles' => $data['data'], 'articlesCount' => $articlesCount]);
    }

    /**
     * Delete Article Endpoint
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

        if ($requestUser->id != $article->user_id) {
            return $response->withJson(['message' => 'Forbidden'], 403);
        }

        $article->delete();

        return $response->withJson([], 200);
    }

    private function calcReadingTime(?string $body): int
    {
        if (!$body) return 1;
        $words = str_word_count(strip_tags($body));
        return max(1, (int) ceil($words / 200));
    }


}