<?php

declare(strict_types=1);

/**
 * Offers are broadcast to available riders and claimed by the first eligible
 * rider who confirms them in the API. This compatibility function intentionally
 * does not pre-assign offers to a rider.
 */
function autoAssignPendingRiderOrders(PDO $pdo): int
{
    return 0;
}
