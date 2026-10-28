<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One probe of one app by the watchdog (hop:watch). */
class ServiceCheck extends Model
{
    public $timestamps = false;

    protected $fillable = ['app_slug', 'kind', 'ok', 'status_code', 'latency_ms', 'error', 'checked_at'];

    protected function casts(): array
    {
        return ['ok' => 'boolean', 'checked_at' => 'datetime'];
    }
}
