<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    public function record(Request $request, string $action, string $description, array $metadata = []): ?AuditLog
    {
        $user = $request->user();

        if (! $user || ! $user->school_id) {
            return null;
        }

        $route = $request->route();
        $parameters = $route?->parameters() ?? [];
        $resource = null;
        $resourceId = null;

        foreach ($parameters as $key => $value) {
            if (is_object($value) && isset($value->id)) {
                $resource = class_basename($value);
                $resourceId = (string) $value->id;
                break;
            }
            if (is_scalar($value) && Str::endsWith($key, ['Id', '_id', 'id'])) {
                $resourceId = (string) $value;
                $resource = Str::headline(Str::beforeLast($key, '_id')) ?: Str::headline($key);
                break;
            }
        }

        return AuditLog::create([
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'action' => $action,
            'method' => $request->method(),
            'route' => $request->route()?->uri() ?? $request->path(),
            'resource_type' => $resource,
            'resource_id' => $resourceId,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000),
            'metadata' => $metadata ?: null,
        ]);
    }

    public function recordRequest(Request $request, int $status): void
    {
        if (! $request->user() || ! $request->user()->school_id) {
            return;
        }

        $this->record(
            $request,
            $this->actionFor($request),
            $this->descriptionFor($request, $status),
            ['status' => $status]
        );
    }

    protected function actionFor(Request $request): string
    {
        return match ($request->method()) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => strtolower($request->method()),
        };
    }

    protected function descriptionFor(Request $request, int $status): string
    {
        $label = Str::headline($request->route()?->uri() ?? $request->path());
        return sprintf('%s request to %s (%s).', $label, $request->method(), $status);
    }
}
