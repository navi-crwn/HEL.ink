<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The last good GitHub numbers of one repo (written by hop:github-stats). */
class GithubStat extends Model
{
    protected $fillable = ['repo', 'payload', 'etags', 'fetched_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'etags' => 'array', 'fetched_at' => 'datetime'];
    }
}
