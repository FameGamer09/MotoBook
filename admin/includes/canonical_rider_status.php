<?php

declare(strict_types=1);

function canonicalRiderDatabase(): ?PDO
{
    static $pdo = null;
    static $attempted = false;

    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if ($attempted) {
        return null;
    }
    $attempted = true;

    $readEnvironment = static function (string $key): string {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? trim($value) : '';
    };

    $host = $readEnvironment('DB_HOST');
    $database = $readEnvironment('DB_DATABASE');
    $username = $readEnvironment('DB_USERNAME');
    $password = $readEnvironment('DB_PASSWORD');
    $port = $readEnvironment('DB_PORT') ?: '3306';
    if ($host === '' || $database === '' || $username === '') {
        return null;
    }

    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $pdo;
    } catch (Throwable $exception) {
        error_log('Canonical rider database connection failed: '.$exception->getMessage());

        return null;
    }
}

/**
 * @param  list<string>  $emails
 * @return array<string, array{rider_status: string, is_verified: bool, user_status: string, has_active_delivery: bool}>
 */
function canonicalRiderStatuses(array $emails): array
{
    $emails = array_values(array_unique(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        $emails
    ))));
    if ($emails === []) {
        return [];
    }

    $pdo = canonicalRiderDatabase();
    if (! $pdo instanceof PDO) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($emails), '?'));
    $sql = "SELECT LOWER(u.email) AS email, r.status AS rider_status, r.is_verified,
                u.status AS user_status,
                MAX(CASE WHEN d.status IN ('assigned','accepted','en_route_to_pickup','arrived_at_pickup','picked_up','en_route_to_customer','arrived_at_customer') THEN 1 ELSE 0 END) AS has_active_delivery
            FROM users u
            INNER JOIN riders r ON r.user_id = u.id
            LEFT JOIN deliveries d ON d.rider_id = r.id
            WHERE u.role = 'rider' AND LOWER(u.email) IN ($placeholders)
            GROUP BY LOWER(u.email), r.status, r.is_verified, u.status";
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($emails);
        $statuses = [];
        foreach ($statement->fetchAll() as $row) {
            $statuses[(string) $row['email']] = [
                'rider_status' => (string) $row['rider_status'],
                'is_verified' => (bool) $row['is_verified'],
                'user_status' => (string) $row['user_status'],
                'has_active_delivery' => (bool) $row['has_active_delivery'],
            ];
        }

        return $statuses;
    } catch (Throwable $exception) {
        error_log('Canonical rider status lookup failed: '.$exception->getMessage());

        return [];
    }
}

function verifyCanonicalRiderByEmail(string $email): bool
{
    $email = strtolower(trim($email));
    $pdo = canonicalRiderDatabase();

    if ($email === '' || ! $pdo instanceof PDO) {
        return false;
    }

    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare("SELECT r.id AS rider_id, u.id AS user_id
            FROM users u
            INNER JOIN riders r ON r.user_id = u.id
            WHERE u.role = 'rider' AND LOWER(u.email) = ?
            LIMIT 1 FOR UPDATE");
        $statement->execute([$email]);
        $rider = $statement->fetch();

        if (! $rider) {
            $pdo->rollBack();

            return false;
        }

        $pdo->prepare('UPDATE riders SET is_verified = 1 WHERE id = ?')->execute([(int) $rider['rider_id']]);
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'inactive'")
            ->execute([(int) $rider['user_id']]);
        $pdo->commit();

        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Canonical rider verification failed: '.$exception->getMessage());

        return false;
    }
}

/**
 * @param  array{rider_status: string, is_verified: bool, user_status: string, has_active_delivery: bool}|null  $status
 */
function canonicalManagementRiderHasActiveDelivery(PDO $managementPdo, int $riderId): bool
{
    $table = $managementPdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rider_orders'");
    $table->execute();
    if ((int) $table->fetchColumn() > 0) {
        $statement = $managementPdo->prepare("SELECT 1 FROM rider_orders WHERE rider_id = ? AND state IN ('ACCEPTED','NAVIGATING_TO_PICKUP','ARRIVED_AT_PICKUP','ORDER_VERIFIED','NAVIGATING_TO_DROP_OFF','ARRIVED_AT_DROP_OFF','PROOF_SUBMITTED')
            UNION ALL
            SELECT 1 FROM orders WHERE rider_id = ? AND order_status IN ('driver_assigned','out_for_delivery','picked_up')
            LIMIT 1");
        $statement->execute([$riderId, $riderId]);

        return (bool) $statement->fetchColumn();
    }

    $statement = $managementPdo->prepare("SELECT 1 FROM orders WHERE rider_id = ? AND order_status IN ('driver_assigned','out_for_delivery','picked_up') LIMIT 1");
    $statement->execute([$riderId]);

    return (bool) $statement->fetchColumn();
}

function canonicalRiderCanReceiveOrders(?array $status, bool $hasManagementActiveDelivery = false): bool
{
    return $status !== null
        && $status['user_status'] === 'active'
        && $status['is_verified']
        && $status['rider_status'] === 'online'
        && ! $status['has_active_delivery']
        && ! $hasManagementActiveDelivery;
}
