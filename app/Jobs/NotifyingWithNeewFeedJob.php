<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewFeedNotifyingEmail;
use Illuminate\Support\Facades\Log;

class NotifyingWithNeewFeedJob implements ShouldQueue
{
    use Queueable;

/**
 * Create a new job instance.
 *
 * @param array $data The data associated with the feed.
 * @param array $title The title of the feed.
 */

    /**
     * Create a new job instance.
     *
     * @param array $data The data associated with the feed.
     * @param array $title The title of the feed.
     */
    public function __construct(public array $data, public array $title)
    {
        $this->data = $data;
        $this->title = $title;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Mail::to(['karimelassaliff@gmail.com','fifakarim52@gmail.com'])->send(new NewFeedNotifyingEmail($this->data, $this->title));
            Log::info('The job succeed');
        } catch (\Exception $e) {
            Log::error('The job failed', ['error' => $e->getMessage()]);
        }
    }
}
