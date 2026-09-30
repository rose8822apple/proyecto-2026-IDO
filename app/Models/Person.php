<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    protected $table = 'people';

    protected $fillable = [
        'name',
        'email',
        'role',
        'phone',
        'status',
    ];

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class, 'person_id');
    }
}
