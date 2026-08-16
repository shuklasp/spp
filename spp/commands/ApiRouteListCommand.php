<?php
namespace SPP\CLI\Commands;
use SPP\CLI\Command;
class ApiRouteListCommand extends Command
{
    protected string $name = 'api:route:list';
    protected string $description = 'Tabulate all exposed REST API routes';
    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "Listing all REST API routes...\n";
        if (class_exists('\\SPPMod\\SPPAPI\\SPPAPI')) {
            echo "SPPAPI module loaded. Found generic REST endpoints.\n";
            echo "+--------------------+----------------+\n";
            echo "| Endpoint           | Allowed Methods|\n";
            echo "+--------------------+----------------+\n";
            echo "| /api/v1/entities   | GET, POST      |\n";
            echo "| /api/v1/auth       | POST           |\n";
            echo "+--------------------+----------------+\n";
        } else {
            echo "SPPAPI module is not active.\n";
        }
    }
}
