<?php

namespace App\Console\Commands;

use App\Models\DocumentVersion;
use App\Models\Dokumen;
use App\Services\MalwareScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ScanDocuments extends Command
{
    protected $signature = 'repository:scan-documents {--all : Rescan previously clean files}';

    protected $description = 'Scan legacy private documents and versions before enabling downloads';

    public function handle(MalwareScanner $scanner): int
    {
        $failed = 0;
        foreach ([Dokumen::class, DocumentVersion::class] as $class) {
            $class::query()->when(! $this->option('all'), fn ($q) => $q->where('scan_status', '!=', 'clean'))->chunkById(50, function ($records) use ($scanner, &$failed): void {
                foreach ($records as $record) {
                    $stream = null;
                    try {
                        $stream = Storage::disk($record->storage_disk)->readStream($record->path_file);
                        $scanner->scan($stream);
                        // Do not mark a concurrently replaced version as scanned.
                        $record->newQuery()->whereKey($record->id)->where('path_file', $record->path_file)->update(['scan_status' => 'clean', 'scanned_at' => now()]);
                        $this->line(class_basename($record).' #'.$record->id.': clean');
                    } catch (Throwable $exception) {
                        $failed++;
                        $record->newQuery()->whereKey($record->id)->where('path_file', $record->path_file)->update(['scan_status' => 'unscanned', 'scanned_at' => null]);
                        $this->error(class_basename($record).' #'.$record->id.': belum lolos');
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }
            });
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
