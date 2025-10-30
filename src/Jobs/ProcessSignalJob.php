<?php

namespace SocialDept\Signal\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SocialDept\Signal\Events\SignalEvent;
use SocialDept\Signal\Signals\Signal;

class ProcessSignalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Signal      $signal,
        protected SignalEvent $event,
    ) {}

    public function handle(): void
    {
        $this->signal->handle($this->event);
    }

    public function failed(\Throwable $exception): void
    {
        $this->signal->failed($this->event, $exception);
    }
}
