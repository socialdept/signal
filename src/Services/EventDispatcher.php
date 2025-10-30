<?php

namespace SocialDept\Signal\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use SocialDept\Signal\Events\JetstreamEvent;
use SocialDept\Signal\Jobs\ProcessSignalJob;

class EventDispatcher
{
    protected SignalRegistry $signalRegistry;

    public function __construct(SignalRegistry $signalRegistry)
    {
        $this->signalRegistry = $signalRegistry;
    }

    /**
     * Dispatch event to matching signals.
     */
    public function dispatch(JetstreamEvent $event): void
    {
        $signals = $this->signalRegistry->getMatchingSignals($event);

        foreach ($signals as $signal) {
            try {
                if ($signal->shouldQueue()) {
                    $this->dispatchToQueue($signal, $event);
                } else {
                    $this->dispatchSync($signal, $event);
                }
            } catch (\Exception $e) {
                Log::error('Signal: Error dispatching to signal', [
                    'signal' => get_class($signal),
                    'error' => $e->getMessage(),
                ]);

                $signal->failed($event, $e);
            }
        }
    }

    /**
     * Dispatch signal synchronously.
     */
    protected function dispatchSync($signal, JetstreamEvent $event): void
    {
        $signal->handle($event);
    }

    /**
     * Dispatch signal to queue.
     */
    protected function dispatchToQueue($signal, JetstreamEvent $event): void
    {
        ProcessSignalJob::dispatch($signal, $event)
            ->onConnection($signal->queueConnection())
            ->onQueue($signal->queue());
    }
}
