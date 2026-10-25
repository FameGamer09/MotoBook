<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SharedPanelAuthentication
{
    /**
     * Authenticate an account stored in the shared management database.
     *
     * @return array{ok: true, panel: string, role: string, url: string, data: array<string, mixed>}|null
     */
    public function authenticate(string $identifier, string $password): ?array
    {
        require_once base_path('admin/includes/sso.php');

        $applicationPath = parse_url((string) config('app.url'), PHP_URL_PATH);
        $baseUrl = is_string($applicationPath) ? rtrim($applicationPath, '/') : '';
        $authentication = ssoAuthenticate(
            DB::connection('management')->getPdo(),
            $identifier,
            $password,
            $baseUrl
        );

        return ($authentication['ok'] ?? false) ? $authentication : null;
    }

    /**
     * Start the session used by the authenticated legacy panel.
     *
     * @param  array{panel: string, role: string, data: array<string, mixed>}  $authentication
     */
    public function startPanelSession(array $authentication): void
    {
        require_once base_path('admin/includes/sso.php');

        ssoSeedPanelSession($authentication);
    }
}
