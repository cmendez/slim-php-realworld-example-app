<?php

namespace Conduit\Controllers\Auth;

use Conduit\Models\User;
use Conduit\Transformers\UserTransformer;
use Interop\Container\ContainerInterface;
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

    /**
     * RegisterController constructor.
     *
     * @param \Interop\Container\ContainerInterface $container
     */
    public function __construct(\Slim\Container $container)
    {
        $this->auth = $container->get('auth');
        $this->validator = $container->get('validator');
        $this->db = $container->get('db');
        $this->fractal = $container->get('fractal');
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
            // 1. Genera el token de PHP (como antes)
            $user->token = $this->auth->generateToken($user);

            // --- NUEVO: INICIO DE LA LLAMADA AL API DE PYTHON ---
            
            $pythonApiUrl = 'http://host.docker.internal:8080/api/users/login';
            $pythonToken = null; // Token por defecto

            // Prepara los datos POST para Python
            $postData = [
                'username' => $userParams['email'], // El API de Python espera el email en el campo 'username'
                'password' => $userParams['password']
            ];

            // Inicializa cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $pythonApiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout de 5 segundos

            // Ejecuta la llamada
            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Procesa la respuesta de Python
            if ($httpCode == 200) {
                $body = json_decode($apiResponse);
                if (isset($body->access_token)) {
                    $pythonToken = $body->access_token;
                }
            } else {
                // Si Python falla, al menos loguea el error pero no detengas el login de PHP
                error_log('Fallo el login de Python. Código: ' . $httpCode . ' Respuesta: ' . $apiResponse);
            }
            
            // --- NUEVO: FIN DE LA LLAMADA AL API DE PYTHON ---

            // 2. Transforma los datos del usuario (como antes)
            $data = $this->fractal->createData(new Item($user, new UserTransformer()))->toArray();
            
            // 3. NUEVO: Añade el token de Python al array de datos final
            $data['python_token'] = $pythonToken;

            // 4. Devuelve la respuesta JSON con AMBOS tokens
            return $response->withJson(['user' => $data]);
        };

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