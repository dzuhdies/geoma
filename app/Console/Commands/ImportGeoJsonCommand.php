<?php

namespace App\Console\Commands;

use App\Services\GeoJsonImportService;
use Illuminate\Console\Command;

class ImportGeoJsonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geojson:import {path? : Path to GeoJSON file (default: storage/app/geojson/wilayah.geojson)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import GeoJSON regions into the database and calculate boundary-sharing spatial neighbors';

    /**
     * Execute the console command.
     */
    public function handle(GeoJsonImportService $importService): int
    {
        $filePath = $this->argument('path');

        $this->info('Starting GeoJSON import process...');

        try {
            $result = $importService->import($filePath, function (string $step, string $message) {
                $this->line("  <comment>[{$step}]</comment> {$message}");
            });

            $this->newLine();
            $this->info('✓ GeoJSON successfully imported and indexed!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Regions Imported', $result['total_regions']],
                    ['Direct Neighbor Pairs', $result['neighbor_pairs']],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to import GeoJSON: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
