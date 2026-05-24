<?php
namespace SPPMod\SPPMigrate;

class SPPMigrate {
    public static function isMigrateRequest(): bool {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        // Match API requests or UI requests
        return str_contains($path, '/api/sppmigrate/') || str_contains($path, '/sppadmin/migrate') || str_contains($path, '/lekhak/admin/migrate');
    }

    public static function handle(): void {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        if (str_contains($path, '/api/sppmigrate/')) {
            self::handleApi($path);
        } else {
            self::handleUi($path);
        }
    }

    private static function handleApi(string $path): void {
        \SPPMod\SPPMigrate\Api\Receiver::handle($path);
    }

    private static function handleUi(string $path): void {
        \SPPMod\SPPMigrate\Ui\Dashboard::render();
        exit;
    }
}
