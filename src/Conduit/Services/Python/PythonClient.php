<?php

declare(strict_types=1);

namespace Conduit\Services\Python;

use Conduit\Exceptions\PythonServiceException;
use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;

class PythonClient
{
    public const JWT_ALGORITHM = 'HS256';
    public const TOKEN_ISSUER = 'php-api';
    public const TOKEN_AUDIENCE = 'python-service';
    public const TOKEN_SUBJECT = 'php-service';

    /** @var array */
    private $settings;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(array $settings, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function getArticles()
    {
        return $this->request('GET', '/internal/articles');
    }

    private function request($method, $path)
    {
        $baseUrl = rtrim((string) ($this->settings['url'] ?? ''), '/');
        $secret = (string) ($this->settings['service_secret'] ?? '');

        if ($baseUrl === '' || $secret === '') {
            throw new PythonServiceException(
                'Python service is not configured',
                503
            );
        }

        if (!function_exists('curl_init')) {
            throw new PythonServiceException(
                'PHP cURL extension is not installed',
                503
            );
        }

        $now = time();
        $token = JWT::encode([
            'iss' => self::TOKEN_ISSUER,
            'sub' => self::TOKEN_SUBJECT,
            'aud' => self::TOKEN_AUDIENCE,
            'iat' => $now,
            'exp' => $now + 60,
            'jti' => base64_encode(random_bytes(16)),
        ], $secret, self::JWT_ALGORITHM);

        $ch = curl_init($baseUrl . $path);
        if ($ch === false) {
            throw new PythonServiceException(
                'Could not initialize the Python service request',
                503
            );
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        // Allow enough time for a sleeping Render Free service to wake up.
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrorNumber = curl_errno($ch);
        $curlError = $curlErrorNumber ? curl_error($ch) : null;
        curl_close($ch);

        if ($body === false || $curlError !== null) {
            $this->logger->error(
                'Python service request failed: ' . ($curlError ?: 'unknown error')
            );

            $httpStatus = $curlErrorNumber === CURLE_OPERATION_TIMEDOUT ? 504 : 503;
            throw new PythonServiceException(
                $httpStatus === 504
                    ? 'Python service timed out'
                    : 'Python service is unavailable',
                $httpStatus
            );
        }

        if ($status < 200 || $status >= 300) {
            $this->logger->error(
                'Python service returned HTTP status ' . $status
            );
            throw new PythonServiceException(
                'Python service returned an error',
                502
            );
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $this->logger->error('Python service returned invalid JSON');
            throw new PythonServiceException(
                'Python service returned an invalid response',
                502
            );
        }

        return $decoded;
    }
}
