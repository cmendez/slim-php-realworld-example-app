<?php

declare(strict_types=1);

namespace Conduit\Models;

use Illuminate\Database\Eloquent\Model;


/**
 * @property string         title
 * @property \Carbon\Carbon created_at
 * @property \Carbon\Carbon updated_at
 */
class Tag extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
    ];


    /********************
     *  Relationships
     ********************/

    public function articles()
    {
        return $this->belongsToMany(Article::class);
    }
}