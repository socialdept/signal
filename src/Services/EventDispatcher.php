<?php

namespace SocialDept\AtpSignals\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use SocialDept\AtpSignals\Events\SignalEvent;
use SocialDept\AtpSignals\Jobs\ProcessSignalJob;

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
    public function dispatch(SignalEvent $event): void
    {
        $signals = $this->signalRegistry->getMatchingSignals($event);

        foreach ($signals as $signal) {
            try {
                $queued = $signal->shouldQueue();

                if ($this->shouldDebug()) {
                    Log::debug('[Signal] Dispatching', [
                        'signal' => class_basename($signal),
                        'kind' => $event->kind,
                        'collection' => $event->commit?->collection,
                        'operation' => $event->commit?->operation,
                        'did' => $event->did,
                        'queued' => $queued,
                    ]);
                }

                if ($queued) {
                    $this->dispatchToQueue($signal, $event);
                } else {
                    $this->dispatchSync($signal, $event);
                }
            } catch (\Throwable $e) {
                Log::error('[Signal] Error dispatching to signal', [
                    'signal' => get_class($signal),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $signal->failed($event, $e);
            }
        }
    }

    /**
     * Check if debug logging is enabled.
     */
    protected function shouldDebug(): bool
    {
        return config('atp-signals.debug', false);
    }

    /**
     * Dispatch signal synchronously.
     */
    protected function dispatchSync($signal, SignalEvent $event): void
    {
        $signal->handle($event);
    }

    /**
     * Dispatch signal to queue.
     */
    protected function dispatchToQueue($signal, SignalEvent $event): void
    {
        ProcessSignalJob::dispatch($signal, $event)
            ->onConnection($signal->queueConnection())
            ->onQueue($signal->queue());
    }
}
