<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('select 1');
            // A successful cache read by itself may hide an unavailable Redis.
            // Exercise both operations without exposing any dependency details.
            $token = bin2hex(random_bytes(12));
            $cacheKey = 'repository:readiness:'.$token;
            if (Cache::put($cacheKey, $token, 10) !== true) {
                throw new \RuntimeException('Readiness cache write failed.');
            }
            try {
                if (Cache::get($cacheKey) !== $token) {
                    throw new \RuntimeException('Readiness cache read failed.');
                }
            } finally {
                Cache::forget($cacheKey);
            }
            $disk = config('repository.documents_disk');
            $storage = Storage::disk($disk);
            $ready = config('filesystems.disks.'.$disk.'.driver') !== 'local'
                ? $storage->directoryExists('documents')
                : is_writable($storage->path(''));

            return response()->json(['status' => $ready ? 'ok' : 'unavailable'], $ready ? 200 : 503)->header('Cache-Control', 'no-store');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['status' => 'unavailable'], 503)->header('Cache-Control', 'no-store');
        }
    }
}
