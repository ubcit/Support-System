<?php

namespace Modules\Audit\Support;

use Modules\Audit\Observers\AuditableObserver;

trait AuditsActivity
{
    public static function bootAuditsActivity(): void
    {
        static::observe(AuditableObserver::class);
    }
}
