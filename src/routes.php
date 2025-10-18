<?php

use Conduit\Controllers\Article\ArticleController;
use Conduit\Controllers\Article\CommentController;
use Conduit\Controllers\Article\FavoriteController;
use Conduit\Controllers\Auth\LoginController;
use Conduit\Controllers\Auth\RegisterController;
use Conduit\Controllers\User\ProfileController;
use Conduit\Controllers\User\UserController;
use Conduit\Middleware\OptionalAuth;
use Conduit\Models\Tag;
use Slim\Http\Request;
use Slim\Http\Response;


// Api Routes
$app->group('/api',
    function () {
        $jwtMiddleware = $this->getContainer()->get('jwt');
        $optionalAuth = $this->getContainer()->get('optionalAuth');
        /** @var \Slim\App $this */

        // Auth Routes
        $this->post('/users', RegisterController::class . ':register')->setName('auth.register');
        $this->post('/users/login', LoginController::class . ':login')->setName('auth.login');

        // User Routes
        $this->get('/user', UserController::class . ':show')->add($jwtMiddleware)->setName('user.show');
        $this->put('/user', UserController::class . ':update')->add($jwtMiddleware)->setName('user.update');

        // Profile Routes
        $this->get('/profiles/{username}', ProfileController::class . ':show')
            ->add($optionalAuth)
            ->setName('profile.show');

        // Ruta para actualizar el perfil del usuario
        $this->put('/profiles/{username}', ProfileController::class . ':update')
            ->add($jwtMiddleware)
            ->setName('profile.update');

        // Rutas para seguir y dejar de seguir, ahora apuntando al controlador correcto
        $this->post('/profiles/{username}/follow', ProfileController::class . ':follow')
            ->add($jwtMiddleware)
            ->setName('profile.follow');
            
        $this->delete('/profiles/{username}/follow', ProfileController::class . ':unfollow')
            ->add($jwtMiddleware)
            ->setName('profile.unfollow');


        // Articles Routes
        // Define static routes before variable routes so FastRoute doesn't mark them as shadowed
        $this->get('/articles/feed', ArticleController::class . ':index')->add($optionalAuth)->setName('article.feed');

        $this->get('/articles/popular', PopularArticleController::class . ':index')->add($optionalAuth)->setName('article.popular');// agregado

        // List and create (static) routes
        $this->get('/articles', ArticleController::class . ':index')->add($optionalAuth)->setName('article.index');
        $this->post('/articles', ArticleController::class . ':store')->add($jwtMiddleware)->setName('article.store');

        // Variable article routes (slug) and related nested routes
        $this->get('/articles/{slug}', ArticleController::class . ':show')->add($optionalAuth)->setName('article.show');
        $this->put('/articles/{slug}', ArticleController::class . ':update')->add($jwtMiddleware)->setName('article.update');
        $this->delete('/articles/{slug}', ArticleController::class . ':destroy')->add($jwtMiddleware)->setName('article.destroy');

        // Comments
        $this->get('/articles/{slug}/comments', CommentController::class . ':index')
            ->add($optionalAuth)
            ->setName('comment.index');
        $this->post('/articles/{slug}/comments', CommentController::class . ':store')
            ->add($jwtMiddleware)
            ->setName('comment.store');
        $this->delete('/articles/{slug}/comments/{id}', CommentController::class . ':destroy')
            ->add($jwtMiddleware)
            ->setName('comment.destroy');

        // Favorite Article Routes
        $this->post('/articles/{slug}/favorite', FavoriteController::class . ':store')
            ->add($jwtMiddleware)
            ->setName('favorite.store');
        $this->delete('/articles/{slug}/favorite', FavoriteController::class . ':destroy')
            ->add($jwtMiddleware)
            ->setName('favorite.destroy');

        // Tags Route
        $this->get('/tags', function (Request $request, Response $response) {
            return $response->withJson([
                'tags' => Tag::all('title')->pluck('title'),
            ]);
        });
    });


// Routes

$app->get('/[{name}]',
    function (Request $request, Response $response, array $args) {
        // Sample log message
        $this->logger->info("Slim-Skeleton '/' route");

        // Render index view
        return $this->renderer->render($response, 'index.phtml', $args);
    });
