<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait RecordsAudit
{
    protected function audit(Request $request, string $action, Model $model, ?array $before, ?string $reason, ?array $after = null): void
    {
        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'before' => $before,
            'after' => $after ?? $model->fresh()?->toArray(),
            'reason' => $reason,
            'ip_address' => $request->ip(),
        ]);
    }
}
