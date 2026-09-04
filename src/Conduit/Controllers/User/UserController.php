<?php

declare(strict_types=1);

namespace Conduit\Controllers\User;

use Conduit\Transformers\UserTransformer;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;

class UserController
{

    /** @var \Conduit\Services\Auth\Auth */
    protected $auth;
    /** @var \League\Fractal\Manager */
    protected $fractal;
    /** @var \Illuminate\Database\Capsule\Manager */
    protected $db;
    /** @var \Conduit\Validation\Validator */
    protected $validator;

    /**
     * UserController constructor.
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

    public function show(Request $request, Response $response)
    {
        if ($user = $this->auth->requestUser($request)) {
            $data = $this->fractal->createData(new Item($user, new UserTransformer()))->toArray();

            return $response->withJson(['user' => $data]);
        }
    }

    public function update(Request $request, Response $response)
    {
        if ($user = $this->auth->requestUser($request)) {
            $requestParams = $request->getParam('user');

            $validation = $this->validateUpdateRequest($requestParams, $user->id);

            if ($validation->failed()) {
                return $response->withJson(['errors' => $validation->getErrors()], 422);
            }

            $user->update([
                'email'    => $requestParams['email'] ?? $user->email,
                'username' => $requestParams['username'] ?? $user->username,
                'bio'      => $requestParams['bio'] ?? $user->bio,
                'image'    => $requestParams['image'] ?? $user->image,
                'password' => $requestParams['password'] ?? $user->password,
            ]);

            $data = $this->fractal->createData(new Item($user, new UserTransformer()))->toArray();

            return $response->withJson(['user' => $data]);
        }
    }

    /**
     * @param array
     *
     * @return \Conduit\Validation\Validator
     */
    protected function validateUpdateRequest($values, $userId)
    {
        return $this->validator->validateArray($values,
            [
                'email'    => v::optional(
                    v::noWhitespace()
                        ->notEmpty()
                        ->email()
                        ->existsWhenUpdate($this->db->table('users'), 'email', $userId)
                ),
                'username' => v::optional(
                    v::noWhitespace()
                        ->notEmpty()
                        ->existsWhenUpdate($this->db->table('users'), 'username', $userId)
                ),
                'password' => v::optional(v::noWhitespace()->notEmpty()),
            ]);
    }
}