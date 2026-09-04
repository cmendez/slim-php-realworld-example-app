<?php

declare(strict_types=1);

namespace Conduit\Controllers\Article;
use Carbon\Carbon;
use Conduit\Models\Article;
use Conduit\Models\Tag;
use Conduit\Transformers\ArticleTransformer;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;

class ArticleController
{
    public const DEFAULT_PAGE_SIZE = 20;

    /** @var \Conduit\Validation\Validator */
    protected $validator;
    /** @var \Illuminate\Database\Capsule\Manager */
    protected $db;
    /** @var \Conduit\Services\Auth\Auth */
    protected $auth;
    /** @var \League\Fractal\Manager */
    protected $fractal;

    /**
     * ArticleController constructor.
     *
     * @param \Slim\Container $container
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
        $builder = Article::query()->latest()->with(['tags', 'user'])->limit(self::DEFAULT_PAGE_SIZE);


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
            // Keep validation to ensure correct format
            'publishDate' => v::optional(v::date()), 
        ]);

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }
        
        // Here we use the constructor only for fields that don't cause problems
        $article = new Article([
            'title' => $data['title'],
            'description' => $data['description'],
            'body' => $data['body'],
        ]);
        
        $article->slug = str_slug($article->title);
        $article->user_id = $requestUser->id;

        // We verify if 'publishDate' exists and explicitly create a Carbon object.
        if (!empty($data['publishDate'])) {
            $article->publish_date = Carbon::parse($data['publishDate']);
        }

        $article->save();

        $tagsId = [];
        if (isset($data['tagList'])) {
            foreach ($data['tagList'] as $tag) {
                $tagsId[] = Tag::updateOrCreate(['title' => $tag], ['title' => $tag])->id;
            }
            $article->tags()->sync($tagsId);
        }

        $payload = $this->fractal->createData(new Item($article, new ArticleTransformer($requestUser->id)))->toArray();
        
        return $response->withJson(['article' => $payload]);
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

        // Get request data into $params variable
        $params = $request->getParam('article', []);

        // --- 1. ADD VALIDATION ---
        $this->validator->validateArray($params, [
            // Make all fields optional on update
            'title'       => v::optional(v::notEmpty()),
            'description' => v::optional(v::notEmpty()),
            'publishDate' => v::optional(v::date()),
        ]);

        if ($this->validator->failed()) {
            return $response->withJson(['errors' => $this->validator->getErrors()], 422);
        }

        // --- 2. UPDATE FIELDS INDIVIDUALLY ---
        // This is clearer and avoids mass assignment issues
        if (isset($params['title'])) {
            $article->title = $params['title'];
            $article->slug = str_slug($params['title']); // Update slug if title changes
        }

        if (isset($params['description'])) {
            $article->description = $params['description'];
        }

        if (isset($params['body'])) {
            $article->body = $params['body'];
        }

        // --- 3. HANDLE PUBLISH DATE WITH CARBON ---
        if (isset($params['publishDate'])) {
            // If the date is an empty string or null, set it to null in the DB.
            // Otherwise, parse it with Carbon.
            $article->publish_date = empty($params['publishDate']) ? null : Carbon::parse($params['publishDate']);
        }

        // --- 4. SAVE ALL CHANGES ---
        $article->save();

        $data = $this->fractal->createData(new Item($article, new ArticleTransformer($requestUser->id)))->toArray();

        return $response->withJson(['article' => $data]);
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

}