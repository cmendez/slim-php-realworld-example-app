<?php

declare(strict_types=1);

namespace Conduit\Controllers\Article;

use Conduit\Models\Tag;
use Slim\Http\Request;
use Slim\Http\Response;

class TagController
{
    public function index(Request $request, Response $response): Response
    {
        return $response->withJson([
            'tags' => Tag::all('title')->pluck('title'),
        ]);
    }
}
