<?php

namespace App\Console\Commands;

use App\Models\PublishAttempt;
use App\Services\PublishingService;
use Illuminate\Console\Command;

class RecoverPublishing extends Command
{
    protected $signature = 'repository:recover-publishing';

    protected $description = 'Menandai attempt penerbitan lama yang terputus sebagai gagal, tanpa memaksa status terbit.';

    public function handle(PublishingService $publishing): int
    {
        $cutoff = now()->subMinutes(10);
        $recovered = 0;
        $errors = 0;

        PublishAttempt::query()
            ->whereIn('status', ['queued', 'processing'])
            ->where('created_at', '<', $cutoff)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('started_at')
                    ->orWhere('started_at', '<', $cutoff);
            })
            ->whereHas('publikasi', fn ($query) => $query->where('repository_status', 'publishing'))
            ->chunkById(100, function ($attempts) use ($publishing, &$recovered, &$errors) {
                foreach ($attempts as $candidate) {
                    try {
                        // PublishingService::fail memperoleh row lock, lalu cek ulang
                        // status. Attempt yang sudah selesai tidak akan diubah.
                        $publishing->fail(
                            $candidate->id,
                            'PUBLISH_INTERRUPTED',
                            'Proses penerbitan sebelumnya terputus. Silakan ulangi penerbitan dari halaman detail.'
                        );
                        if ($candidate->fresh()?->status === 'failed') {
                            $recovered++;
                        }
                    } catch (\Throwable $exception) {
                        report($exception);
                        $errors++;
                    }
                }
            });

        $this->info("$recovered penerbitan lama dipulihkan sebagai Gagal Terbit. $errors error.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
