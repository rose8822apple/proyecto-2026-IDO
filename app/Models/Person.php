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
        'cedula',
        'role',
        'phone',
        'status',
        'coordination_title',
        'minimum_rest_hours',
    ];

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class, 'person_id');
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'person_id');
    }
}
