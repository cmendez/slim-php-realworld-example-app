<?php

declare(strict_types=1);

namespace Conduit\Transformers;

use Conduit\Models\Article;
use Conduit\Models\Comment;
use Conduit\Models\User;
use League\Fractal\ItemResource;
use League\Fractal\TransformerAbstract;

class CommentTransformer extends TransformerAbstract
{

    /**
     * Include resources without needing it to be requested.
     *
     * @var array
     */
    protected $defaultIncludes = [
        'author',
    ];

    /**
     * @var integer|null
     */
    protected $requestUserId;

    /**
     * CommentTransformer constructor.
     *
     * @param int $requestUserId
     */
    public function __construct($requestUserId = null)
    {
        $this->requestUserId = $requestUserId;
    }

    public function transform(Comment $comment)
    {
        return [
            'id'        => $comment->id,
            'createdAt' => $comment->created_at->toIso8601String(),
            'updatedAt' => isset($comment->updated_at) ? $comment->updated_at->toIso8601String() : $comment->updated_at,
            'body'      => $comment->body,
        ];
    }

    /**
     * Include Author
     *
     * @param \Conduit\Models\Comment $comment
     *
     * @return \League\Fractal\Resource\Item
     */
    public function includeAuthor(Comment $comment)
    {
        $author = $comment->user;

        return $this->item($author, new AuthorTransformer($this->requestUserId));
    }
}