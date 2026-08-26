<?php

namespace Modules\Audit\Observers;

use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Services\AuditLogger;

class AuditableObserver
{
    public function __construct(private readonly AuditLogger $logger) {}

    public function created(Model $model): void
    {
        $this->logger->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = $model->getOriginal($key);
        }

        $this->logger->record($model, 'updated', $old, $changes);
    }

    public function deleted(Model $model): void
    {
        $action = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
            ? 'force_deleted'
            : 'deleted';

        $this->logger->record($model, $action, $model->getAttributes(), null);
    }

    public function restored(Model $model): void
    {
        $this->logger->record($model, 'restored', null, $model->getAttributes());
    }
}
