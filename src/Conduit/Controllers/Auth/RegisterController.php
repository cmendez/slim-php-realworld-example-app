<?php

namespace Conduit\Controllers\Auth;

use Conduit\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RegisterController
{
    protected $auth;
    protected $fractal;

    public function __construct(\Slim\Container $container)
    {
        $this->auth = $container->get('auth');
        $this->fractal = $container->get('fractal');
    }

    public function register(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody()['user'] ?? [];

        // Asegurarnos de que los campos necesarios existen
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
             return $response->withJson(['errors' => ['body' => ['Invalid data provided']]], 422);
        }

        $user = new User([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'], // El mutator en User.php se encargará de hashear esto
        ]);

        $user->save();

        // Generar el token para el nuevo usuario
        $token = $this->auth->generateToken($user);

        $userData = [
            'user' => [
                'email' => $user->email,
                'token' => $token,
                'username' => $user->username,
                'bio' => $user->bio,
                'image' => $user->image,
            ],
        ];

        $response->getBody()->write(json_encode($userData));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }
}