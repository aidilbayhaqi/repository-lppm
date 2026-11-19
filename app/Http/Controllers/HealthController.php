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
            Cache::get('repository:readiness');
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
