<?php

namespace App\Console\Commands;

use App\Services\RiderAssignmentService;
use Illuminate\Console\Command;

class RetryPendingDeliveries extends Command
{
    protected $signature = 'deliveries:retry-pending';

    protected $description = 'Retry assignment for ready orders without an available rider';

    public function handle(RiderAssignmentService $assignmentService): int
    {
        $assignedCount = $assignmentService->assignPendingDeliveries();

        $this->info("Assigned {$assignedCount} pending deliveries.");

        return self::SUCCESS;
    }
}
