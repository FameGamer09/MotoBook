<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EmailSent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Public variable to store incoming data
    public $data;

    /**
     * Create a new job instance.
     */
    public function __construct($data)
    {
        // Store value received by the constructor parameter into object's property
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Set a 3-second sleep
        sleep(3);

        // Log the event using data from $this->data
        Log::info("The email is sent to " . $this->data['email']);
    }
}