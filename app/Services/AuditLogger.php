<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    private const EXCLUDED_ATTRIBUTES = [
        'id',
        'email',
        'phone',
        'cedula',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
    ];

    public function record(string $event, Model $model, array $before = []): AuditLog
    {
        $action = str($event)->afterLast('.')->toString();
        $current = $action === 'deleted' ? [] : $this->snapshot($model->getAttributes());
        $previous = $this->snapshot($before ?: ($action === 'deleted' ? $model->getAttributes() : []));

        if ($action === 'updated') {
            $changed = collect(array_unique([...array_keys($previous), ...array_keys($current)]))
                ->filter(fn (string $key) => ($previous[$key] ?? null) !== ($current[$key] ?? null))
                ->all();

            $previous = array_intersect_key($previous, array_flip($changed));
            $current = array_intersect_key($current, array_flip($changed));
        }

        return AuditLog::query()->create([
            'actor_id' => Auth::id(),
            'event' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $action === 'created' ? null : $previous,
            'new_values' => $action === 'deleted' ? null : $current,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function snapshot(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::EXCLUDED_ATTRIBUTES));
    }
}
