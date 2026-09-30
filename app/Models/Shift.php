<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    protected $table = 'shifts';

    protected $fillable = [
        'title',
        'site_id',
        'start_time',
        'end_time',
        'coverage',
        'status',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
