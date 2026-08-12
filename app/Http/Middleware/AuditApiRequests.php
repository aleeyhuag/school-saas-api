<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditApiRequests
{
    public function __construct(protected AuditLogService $auditLogs) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Keep the audit trail useful rather than logging every GET/read.
        // Authenticated school users' creates, updates, deletes and other
        // state-changing API requests are recorded after the response so
        // failed validation can still be distinguished by HTTP status.
        if ($request->is('api/*') && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $this->auditLogs->recordRequest($request, $response->getStatusCode());
        }

        return $response;
    }
}
