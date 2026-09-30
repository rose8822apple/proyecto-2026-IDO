<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends Model
{
    protected $table = 'availabilities';

    protected $fillable = [
        'person_id',
        'date',
        'start_time',
        'end_time',
        'status',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
