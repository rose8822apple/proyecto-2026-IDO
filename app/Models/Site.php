<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $table = 'sites';

    protected $fillable = [
        'name',
        'type',
        'state',
        'municipality',
        'parish',
        'coverage',
        'status',
        'code',
    ];

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'site_id');
    }
}
