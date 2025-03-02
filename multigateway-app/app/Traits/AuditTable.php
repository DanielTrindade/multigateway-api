<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait AuditTable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            self::logAction($model, 'created');
        });

        static::updated(function (Model $model) {
            if ($model->wasChanged()) {
                self::logAction($model, 'updated', $model->getChanges());
            }
        });

        static::deleted(function (Model $model) {
            self::logAction($model, 'deleted');
        });
    }

    protected static function logAction(Model $model, string $action, array $changes = [])
    {
        $user = Auth::user();

        AuditLog::create([
            'user_id' => $user ? $user->id : null,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'action' => $action,
            'changes' => !empty($changes) ? json_encode($changes) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}
