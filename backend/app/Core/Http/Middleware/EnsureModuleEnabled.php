<?php

namespace App\Core\Http\Middleware;

use App\Core\Modules\ModuleManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(private readonly ModuleManager $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! $this->modules->isEnabled($module)) {
            return response()->json([
                'message' => "Modul [{$module}] belum diaktifkan.",
            ], Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
