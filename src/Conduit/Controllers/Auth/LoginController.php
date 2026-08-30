<?php

declare(strict_types=1);

namespace Conduit\Controllers\Auth;

use Conduit\Models\User;
use Conduit\Transformers\UserTransformer;
use League\Fractal\Resource\Item;
use Slim\Http\Request;
use Slim\Http\Response;
use Respect\Validation\Validator as v;

class LoginController
{

    /** @var \Conduit\Validation\Validator */
    protected $validator;
    /** @var \Illuminate\Database\Capsule\Manager */
    protected $db;
    /** @var \League\Fractal\Manager */
    protected $fractal;
    /** @var \Conduit\Services\Auth\Auth */
    private $auth;
    /** @var array */
    private $settings;

    /**
     * LoginController constructor.
     *
     * @param \Slim\Container $container
     */
    public function __construct(\Slim\Container $container)
    {
        $this->auth = $container->get('auth');
        $this->validator = $container->get('validator');
        $this->db = $container->get('db');
        $this->fractal = $container->get('fractal');
        $this->settings = $container->get('settings');
    }

    /**
     * Return token after successful login
     *
     * @param \Slim\Http\Request  $request
     * @param \Slim\Http\Response $response
     *
     * @return \Slim\Http\Response
     */
    public function login(Request $request, Response $response)
    {
        $validation = $this->validateLoginRequest($userParams = $request->getParam('user'));

        if ($validation->failed()) {
            return $response->withJson(['errors' => ['email or password' => ['is invalid']]], 422);
        }

        if ($user = $this->auth->attempt($userParams['email'], $userParams['password'])) {
            // 1. Generate PHP token
            $user->token = $this->auth->generateToken($user);

            // --- START PYTHON API CALL ---
            
            $pythonApiUrl = $this->settings['python_api']['url'] . '/api/users/login';
            $pythonToken = null; // Default token

            // Prepare POST data for Python
            $postData = [
                'username' => $userParams['email'], // Python API expects email in 'username' field
                'password' => $userParams['password']
            ];

            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $pythonApiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 seconds timeout

            // Execute call
            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Process Python response
            if ($httpCode == 200) {
                $body = json_decode($apiResponse);
                if (isset($body->access_token)) {
                    $pythonToken = $body->access_token;
                }
            } else {
                // If Python fails, log error but don't stop PHP login
                error_log('Python login failed. Code: ' . $httpCode . ' Response: ' . $apiResponse);
            }
            
            // --- END PYTHON API CALL ---

            // 2. Transform user data
            $data = $this->fractal->createData(new Item($user, new UserTransformer()))->toArray();
            
            // 3. Return JSON response
            // The frontend explicitly expects python_token in the body.
            $data['python_token'] = $pythonToken;

            return $response->withJson(['user' => $data]);
        }

        return $response->withJson(['errors' => ['email or password' => ['is invalid']]], 422);
    }

    /**
     * @param array
     *
     * @return \Conduit\Validation\Validator
     */
    protected function validateLoginRequest($values)
    {
        return $this->validator->validateArray($values,
            [
                'email'    => v::noWhitespace()->notEmpty(),
                'password' => v::noWhitespace()->notEmpty(),
            ]);
    }
}