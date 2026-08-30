<?php

declare(strict_types=1);

namespace Conduit\Controllers\User;

use Conduit\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProfileController
{
    protected $auth;

    public function __construct(\Slim\Container $container)
    {
        $this->auth = $container->get('auth');
    }

    public function show(Request $request, Response $response, array $args)
    {
        $user = User::where('username', $args['username'])->firstOrFail();
        $requestUser = $this->auth->requestUser($request);
        $followingStatus = $requestUser ? $requestUser->isFollowing($user->id) : false;

        // Return all profile fields directly from the user
        $profileData = [
            'username' => $user->username,
            'bio' => $user->bio,
            'image' => $user->image,
            'twitter_url' => $user->twitter_url,
            'linkedin_url' => $user->linkedin_url,
            'following' => $followingStatus,
        ];

        return $response->withJson(['profile' => $profileData]);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $userToUpdate = User::where('username', $args['username'])->firstOrFail();

        // Use the auth service, just like the other controllers
        $currentUser = $this->auth->requestUser($request);

        // If the token is not valid or not sent, $currentUser will be null
        if (!$currentUser) {
            return $response->withStatus(401); // Unauthorized
        }

        // Security Validation: You can only edit your own profile
        if ($currentUser->id !== $userToUpdate->id) {
            return $response->withStatus(403); // Forbidden
        }

        $data = $request->getParsedBody()['user'] ?? [];
        $userToUpdate->update($data);

        return $this->show($request, $response, $args);
    }

    public function follow(Request $request, Response $response, array $args)
    {
        $requestUser = $this->auth->requestUser($request);
        $user = User::query()->where('username', $args['username'])->firstOrFail();
        $requestUser->follow($user->id);
        return $this->show($request, $response, $args);
    }

    public function unfollow(Request $request, Response $response, array $args)
    {
        $requestUser = $this->auth->requestUser($request);
        $user = User::query()->where('username', $args['username'])->firstOrFail();
        $requestUser->unFollow($user->id);
        return $this->show($request, $response, $args);
    }
}