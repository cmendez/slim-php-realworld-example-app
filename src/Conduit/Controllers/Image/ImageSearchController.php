<?php
// src/Conduit/Controllers/Image/ImageSearchController.php

namespace Conduit\Controllers\Image;

use Conduit\Controllers\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class ImageSearchController extends BaseController
{
    /**
     * Controlador para buscar imágenes en Unsplash (actuando como proxy)
     */
    public function search(Request $request, Response $response, array $args)
    {
        // Obtenemos el logger y settings desde el contenedor (inyectado en BaseController)
        $logger = $this->container->get('logger');
        $settings = $this->container->get('settings');

        // 1. Obtener el parámetro de búsqueda
        $queryParams = $request->getQueryParams();
        $query = $queryParams['q'] ?? '';

        if (empty($query)) {
            return $response->withJson(['error' => 'Query parameter "q" is required'], 400);
        }

        // 2. Obtener la API Key desde la configuración
        $accessKey = $settings['unsplash']['access_key'] ?? ''; // Asumiendo que está en settings
        if (empty($accessKey)) {
             $logger->error('Unsplash Access Key is not configured');
             return $response->withJson(['error' => 'Image search is not configured'], 500);
        }

        // 3. Construir la URL para Unsplash
        $apiUrl = 'https://api.unsplash.com/search/photos?' . http_build_query([
            'query' => $query,
            'per_page' => 12,
            'orientation' => 'landscape'
        ]);

        // 4. Inicializar y configurar cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Client-ID ' . $accessKey,
            'Accept-Version: v1',
            'User-Agent: Conduit-Realworld-Clone'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // 5. Ejecutar la llamada
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // 6. Manejar errores de cURL
        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            $logger->error('cURL Error calling Unsplash: ' . $errorMsg);
            return $response->withJson(['error' => 'Failed to fetch images from service'], 502);
        }
        curl_close($ch);

        // 7. Manejar errores de la API (ej: 401, 403)
        if ($httpCode >= 400) {
             $logger->error("Unsplash API returned status $httpCode");
             return $response->withJson(['error' => 'Image service returned an error'], $httpCode);
        }

        // 8. Decodificar y limpiar la respuesta
        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['results'])) {
             $logger->error('Failed to decode JSON response from Unsplash');
             return $response->withJson(['error' => 'Invalid response from image service'], 502);
        }

        $cleanedResults = array_map(function ($image) {
            return [
                'id' => $image['id'],
                'alt' => $image['alt_description'],
                'url_small' => $image['urls']['small'],
                'url_regular' => $image['urls']['regular'],
                'user_name' => $image['user']['name'],
            ];
        }, $data['results']);

        // 9. Devolver los resultados limpios al frontend
        return $response->withJson(['images' => $cleanedResults]);
    }
}