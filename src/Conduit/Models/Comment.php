<?php

namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;


/**
 * @property integer                 id
 * @property string                  body
 * @property integer                 sentiment_score
 * @property integer                 article_id
 * @property integer                 user_id
 * @property \Conduit\Models\User    user
 * @property \Conduit\Models\Article article
 * @property \Carbon\Carbon          created_at
 * @property \Carbon\Carbon          update_at
 */
class Comment extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'body',
        'user_id',
        'article_id',
        'sentiment_score',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'sentiment_score' => 'integer',
    ];

    /********************
     *  Relationships
     ********************/

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}