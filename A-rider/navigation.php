<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/db.php';

$hasSession = !empty($_SESSION['rider_id']);
if (!$hasSession) {
    $redirect = UNIFIED_LOGIN_URL . '?pool=rider&redirect=' . rawurlencode(
        (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://') .
        ($_SERVER['HTTP_HOST'] ?? 'localhost') .
        ($_SERVER['REQUEST_URI'] ?? '/')
    );
    header('Location: ' . $redirect, true, 302);
    exit;
}

try {
    $rider = riderRequireAuth();
} catch (Throwable $_) {
    header('Location: ' . UNIFIED_LOGIN_URL, true, 302);
    exit;
}

$orderId = isset($_GET['order']) ? (int)$_GET['order'] : (isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0);
if ($orderId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Missing ?order= parameter";
    exit;
}

$LARAVEL_ROOT = realpath(__DIR__ . '/..');
if (!is_file($LARAVEL_ROOT . '/vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Laravel vendor/autoload.php not found. Run: composer install\n";
    exit;
}
require_once $LARAVEL_ROOT . '/vendor/autoload.php';

$app = require $LARAVEL_ROOT . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;

$order = Order::with(['customer:id,name', 'rider:id,name'])->find($orderId);
if (!$order) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Order #{$orderId} not found in unified orders table.";
    exit;
}

if ((int)$rider['id'] !== (int)$order->rider_id) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Access denied: this order is not assigned to rider #" . (int)$rider['id'];
    exit;
}

$laravelUser = \App\Models\User::where('email', $rider['email'])->first()
    ?? \App\Models\User::where('id', (int)$rider['id'])->first();
if ($laravelUser) {
    Auth::setUser($laravelUser);
}

Config::set('app.url', rtrim(
    (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') .
    (PROJECT_URL_BASE === '/' ? '' : PROJECT_URL_BASE),
    '/'
));

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

echo View::make('rider.navigation', compact('order'))->render();
