<?php

namespace App\Jobs;

use App\Services\PublishingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PublishPublication implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return (string) $this->attemptId;
    }

    public int $tries = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $attemptId) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(PublishingService $service): void
    {
        $service->process($this->attemptId);
    }

    public function failed(?Throwable $exception): void
    {
        app(PublishingService::class)->fail($this->attemptId, 'PUBLISH_FAILED', 'Penerbitan gagal setelah percobaan worker. Periksa dokumen dan penyimpanan, lalu ulangi.');
    }
}
