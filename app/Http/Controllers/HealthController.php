<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => 'ok',
            'database' => 'fail',
            'cache' => 'fail',
            'queue' => config('queue.default'),
        ];

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            return response()->json([
                'status' => 'unhealthy',
                'checks' => $checks,
            ], 503);
        }

        try {
            Cache::put('health_probe', '1', 10);
            $checks['cache'] = Cache::get('health_probe') === '1' ? 'ok' : 'fail';
        } catch (\Throwable) {
            $checks['cache'] = 'fail';
        }

        $unhealthy = in_array('fail', $checks, true);

        return response()->json([
            'status' => $unhealthy ? 'degraded' : 'healthy',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $unhealthy ? 503 : 200);
    }
}
