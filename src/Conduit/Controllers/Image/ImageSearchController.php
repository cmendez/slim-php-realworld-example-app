<?php
// src/Conduit/Controllers/Image/ImageSearchController.php

declare(strict_types=1);

namespace Conduit\Controllers\Image;

use Conduit\Controllers\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class ImageSearchController extends BaseController
{
    /**
     * Controller to search images on Unsplash (acting as a proxy)
     */
    public function search(Request $request, Response $response, array $args)
    {
        // Get logger and settings from the container (injected in BaseController)
        $logger = $this->container->get('logger');
        $settings = $this->container->get('settings');

        // 1. Get the search parameter
        $queryParams = $request->getQueryParams();
        $query = trim((string) ($queryParams['q'] ?? ''));

        if ($query === '') {
            return $response->withJson(['error' => 'Query parameter "q" is required'], 400);
        }

        if (strlen($query) > 100) {
            return $response->withJson(['error' => 'Query parameter "q" is too long'], 400);
        }

        // 2. Get the API Key from the configuration
        $accessKey = $settings['unsplash']['access_key'] ?? ''; // Assuming it is in settings
        if (empty($accessKey)) {
             $logger->error('Unsplash Access Key is not configured');
             return $response->withJson(['error' => 'Image search is not configured'], 500);
        }

        // 3. Build the URL for Unsplash
        $apiUrl = 'https://api.unsplash.com/search/photos?' . http_build_query([
            'query' => $query,
            'per_page' => 12,
            'orientation' => 'landscape',
            'content_filter' => 'high',
        ]);

        // 4. Call Unsplash from the backend so the access key is never exposed.
        $unsplashResponse = $this->callUnsplash($apiUrl, $accessKey);
        if ($unsplashResponse['error'] !== null) {
            $logger->error('cURL Error calling Unsplash: ' . $unsplashResponse['error']);
            return $response->withJson(['error' => 'Failed to fetch images from service'], 502);
        }

        // Do not leak an upstream 401 as if the student's session had expired.
        if ($unsplashResponse['status'] < 200 || $unsplashResponse['status'] >= 300) {
             $logger->error('Unsplash API returned status ' . $unsplashResponse['status']);
             return $response->withJson(['error' => 'Image service returned an error'], 502);
        }

        // 5. Decode and expose only the fields required by the frontend.
        $data = json_decode($unsplashResponse['body'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['results'])) {
             $logger->error('Failed to decode JSON response from Unsplash');
             return $response->withJson(['error' => 'Invalid response from image service'], 502);
        }

        $cleanedResults = [];
        foreach ($data['results'] as $image) {
            if (empty($image['id']) || empty($image['urls']['small']) || empty($image['urls']['regular'])) {
                continue;
            }

            $cleanedResults[] = [
                'id' => $image['id'],
                'alt' => $image['alt_description'] ?? $image['description'] ?? '',
                'url_small' => $image['urls']['small'],
                'url_regular' => $image['urls']['regular'],
                'user_name' => $image['user']['name'] ?? 'Unsplash contributor',
                'user_url' => $this->withReferralParameters(
                    $image['user']['links']['html'] ?? 'https://unsplash.com'
                ),
                'photo_url' => $this->withReferralParameters(
                    $image['links']['html'] ?? 'https://unsplash.com'
                ),
                'download_location' => $image['links']['download_location'] ?? null,
            ];
        }

        return $response->withJson(['images' => $cleanedResults]);
    }

    /**
     * Notify Unsplash when the application uses a selected photo.
     */
    public function trackDownload(Request $request, Response $response, array $args)
    {
        $logger = $this->container->get('logger');
        $settings = $this->container->get('settings');
        $accessKey = $settings['unsplash']['access_key'] ?? '';
        $imageId = (string) ($args['id'] ?? '');

        if ($accessKey === '') {
            return $response->withJson(['error' => 'Image search is not configured'], 500);
        }

        if (!preg_match('/^[A-Za-z0-9_-]+$/', $imageId)) {
            return $response->withJson(['error' => 'Invalid image id'], 400);
        }

        $apiUrl = 'https://api.unsplash.com/photos/' . rawurlencode($imageId) . '/download';
        $unsplashResponse = $this->callUnsplash($apiUrl, $accessKey);

        if ($unsplashResponse['error'] !== null
            || $unsplashResponse['status'] < 200
            || $unsplashResponse['status'] >= 300) {
            $logger->error('Failed to track Unsplash download for image ' . $imageId);
            return $response->withJson(['error' => 'Failed to track image selection'], 502);
        }

        return $response->withStatus(204);
    }

    private function callUnsplash($url, $accessKey)
    {
        if (!function_exists('curl_init')) {
            return ['body' => false, 'status' => 0, 'error' => 'PHP cURL extension is not installed'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['body' => false, 'status' => 0, 'error' => 'Could not initialize cURL'];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Client-ID ' . $accessKey,
            'Accept-Version: v1',
            'User-Agent: Conduit-Realworld-Classroom',
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return ['body' => $body, 'status' => $status, 'error' => $error];
    }

    private function withReferralParameters($url)
    {
        $separator = strpos($url, '?') === false ? '?' : '&';

        return $url . $separator . 'utm_source=conduit_realworld&utm_medium=referral';
    }
}