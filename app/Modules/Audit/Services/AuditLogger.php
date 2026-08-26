<?php

namespace Modules\Audit\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Models\AuditLog;

class AuditLogger
{
    /** @var list<string> */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'api_key',
        'api_secret',
        'secret',
        'token',
        'access_token',
        'refresh_token',
    ];

    private static bool $recording = false;

    public function record(Model $model, string $action, ?array $oldValues = null, ?array $newValues = null): void
    {
        if (static::$recording || $model instanceof AuditLog) {
            return;
        }

        static::$recording = true;

        try {
            $user = auth()->user();
            $employee = $user && method_exists($user, 'resolveEmployee')
                ? $user->resolveEmployee()
                : null;

            $workspaceId = $model->getAttribute('workspace_id')
                ?? $employee?->workspace_id
                ?? null;

            $request = request();

            AuditLog::create([
                'workspace_id' => $workspaceId,
                'user_id' => $user?->id,
                'employee_id' => $employee?->id,
                'action' => $action,
                'auditable_type' => $model->getMorphClass(),
                'auditable_id' => $model->getKey(),
                'old_values' => $this->redact($oldValues),
                'new_values' => $this->redact($newValues),
                'url' => $request?->fullUrl(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'created_at' => now(),
            ]);
        } finally {
            static::$recording = false;
        }
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    public function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $redacted = [];

        foreach ($values as $key => $value) {
            $normalized = strtolower((string) $key);

            if (in_array($normalized, self::REDACTED_KEYS, true)) {
                $redacted[$key] = '[redacted]';
                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }
}
