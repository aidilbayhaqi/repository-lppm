<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResetAccountPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
