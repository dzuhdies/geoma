<?php

namespace App\Services;

use App\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GeoJsonImportService
{
    /**
     * Import GeoJSON file into regions table and recalculate neighbor topology.
     *
     * @param string|null $filePath Absolute or relative path to geojson file
     * @param callable|null $progressCallback Optional callback for console progress output
     * @return array Summary of import results
     */
    public function import(?string $filePath = null, ?callable $progressCallback = null): array
    {
        @ini_set('memory_limit', '512M');

        $path = $filePath ?: storage_path('app/geojson/wilayah.geojson');

        if (!File::exists($path)) {
            // Check fallback in root directory
            $rootCandidates = glob(base_path('*.geojson'));
            if (!empty($rootCandidates) && File::exists($rootCandidates[0])) {
                $path = $rootCandidates[0];
            } else {
                throw new \RuntimeException("GeoJSON file not found at: {$path}");
            }
        }

        if ($progressCallback) {
            $progressCallback('reading_file', 'Reading GeoJSON file from ' . $path);
        }

        $jsonContent = File::get($path);
        $data = json_decode($jsonContent, true);
        unset($jsonContent);

        if (!$data || !isset($data['features']) || !is_array($data['features'])) {
            throw new \RuntimeException("Invalid GeoJSON structure. 'features' array missing.");
        }

        $features = $data['features'];
        $totalFeatures = count($features);

        if ($progressCallback) {
            $progressCallback('features_loaded', "Loaded {$totalFeatures} features from GeoJSON.");
        }

        // 1. Process and upsert regions in batches
        $batchSize = 200;
        $now = now();
        $importedCount = 0;

        $regionsToUpsert = [];
        foreach ($features as $index => $feature) {
            $props = $feature['properties'] ?? [];
            $geometry = $feature['geometry'] ?? null;
            $idsubsls = (string)($props['idsubsls'] ?? ($props['IDSUBSLS'] ?? ''));

            if (empty($idsubsls)) {
                continue;
            }

            $nmsls = $props['nmsls'] ?? ($props['NMSLS'] ?? '');
            $nmsubsls = $props['nmsubsls'] ?? ($props['NMSUBSLS'] ?? '');

            // Parse RT and RW helpers from nmsls / nmsubsls
            $rw = $this->extractRw($nmsls, $nmsubsls, $props);
            $rt = $this->extractRt($nmsls, $nmsubsls, $props);

            $regionsToUpsert[] = [
                'objectid' => $props['OBJECTID'] ?? ($props['objectid'] ?? null),
                'kdprov' => $props['kdprov'] ?? ($props['KDPROV'] ?? null),
                'kdkab' => $props['kdkab'] ?? ($props['KDKAB'] ?? null),
                'kdkec' => $props['kdkec'] ?? ($props['KDKEC'] ?? null),
                'kddesa' => $props['kddesa'] ?? ($props['KDDESA'] ?? null),
                'kdsls' => $props['kdsls'] ?? ($props['KDSLS'] ?? null),
                'nmprov' => $props['nmprov'] ?? ($props['NMPROV'] ?? null),
                'nmkab' => $props['nmkab'] ?? ($props['NMKAB'] ?? null),
                'nmkec' => $props['nmkec'] ?? ($props['NMKEC'] ?? null),
                'nmdesa' => $props['nmdesa'] ?? ($props['NMDESA'] ?? null),
                'nmsubsls' => $nmsubsls,
                'idsls' => $props['idsls'] ?? ($props['IDSLS'] ?? null),
                'iddesa' => $props['iddesa'] ?? ($props['IDDESA'] ?? null),
                'nmsls' => $nmsls,
                'idsubsls' => $idsubsls,
                'jenis' => $props['jenis'] ?? ($props['JENIS'] ?? null),
                'rw' => $rw,
                'rt' => $rt,
                'geometry' => json_encode($geometry),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($regionsToUpsert) >= $batchSize) {
                Region::upsert(
                    $regionsToUpsert,
                    ['idsubsls'],
                    [
                        'objectid', 'kdprov', 'kdkab', 'kdkec', 'kddesa', 'kdsls',
                        'nmprov', 'nmkab', 'nmkec', 'nmdesa', 'nmsubsls', 'idsls',
                        'iddesa', 'nmsls', 'jenis', 'rw', 'rt', 'geometry', 'updated_at'
                    ]
                );
                $importedCount += count($regionsToUpsert);
                $regionsToUpsert = [];

                if ($progressCallback) {
                    $progressCallback('upsert_progress', "Imported {$importedCount}/{$totalFeatures} regions...");
                }
            }
        }

        if (!empty($regionsToUpsert)) {
            Region::upsert(
                $regionsToUpsert,
                ['idsubsls'],
                [
                    'objectid', 'kdprov', 'kdkab', 'kdkec', 'kddesa', 'kdsls',
                    'nmprov', 'nmkab', 'nmkec', 'nmdesa', 'nmsubsls', 'idsls',
                    'iddesa', 'nmsls', 'jenis', 'rw', 'rt', 'geometry', 'updated_at'
                ]
            );
            $importedCount += count($regionsToUpsert);
        }

        if ($progressCallback) {
            $progressCallback('regions_upserted', "All {$importedCount} regions stored in database.");
            $progressCallback('computing_neighbors', "Computing spatial neighbor topology based on geometry boundaries...");
        }

        // 2. Compute spatial neighbor relationships
        $neighborPairsCount = $this->calculateAndStoreNeighbors($features, $progressCallback);

        if ($progressCallback) {
            $progressCallback('completed', "Import complete: {$importedCount} regions, {$neighborPairsCount} neighbor relationships indexed.");
        }

        return [
            'total_regions' => $importedCount,
            'neighbor_pairs' => $neighborPairsCount,
        ];
    }

    /**
     * Calculate boundary sharing topology across all features and store in region_neighbors
     */
    protected function calculateAndStoreNeighbors(array $features, ?callable $progressCallback = null): int
    {
        // Load region mapping: idsubsls -> database id
        $idMap = Region::pluck('id', 'idsubsls')->all();

        $pointToDbIds = [];
        $precision = 6; // Coordinate rounding for planar boundary vertices

        foreach ($features as $feature) {
            $idsubsls = (string)($feature['properties']['idsubsls'] ?? ($feature['properties']['IDSUBSLS'] ?? ''));
            if (!isset($idMap[$idsubsls])) {
                continue;
            }

            $dbId = $idMap[$idsubsls];
            $coords = $feature['geometry']['coordinates'] ?? [];
            $this->extractPoints($coords, $dbId, $pointToDbIds, $precision);
        }

        // Find shared points between regions
        $pairSharedCount = [];
        foreach ($pointToDbIds as $ptKey => $dbIds) {
            $uniqueDbIds = array_values(array_unique($dbIds));
            $count = count($uniqueDbIds);
            if ($count > 1) {
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $id1 = min($uniqueDbIds[$i], $uniqueDbIds[$j]);
                        $id2 = max($uniqueDbIds[$i], $uniqueDbIds[$j]);
                        $pairKey = "{$id1}_{$id2}";
                        $pairSharedCount[$pairKey] = ($pairSharedCount[$pairKey] ?? 0) + 1;
                    }
                }
            }
        }

        // Free memory from pointToDbIds before writing to DB
        unset($pointToDbIds);

        // Clear existing neighbors and insert in batches
        DB::table('region_neighbors')->truncate();

        $now = now();
        $batch = [];
        $totalPairs = 0;

        foreach ($pairSharedCount as $pairKey => $sharedCount) {
            [$id1, $id2] = explode('_', $pairKey);
            $id1 = (int)$id1;
            $id2 = (int)$id2;

            // Forward (A -> B)
            $batch[] = [
                'region_id' => $id1,
                'neighbor_id' => $id2,
                'shared_points_count' => $sharedCount,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            // Backward (B -> A)
            $batch[] = [
                'region_id' => $id2,
                'neighbor_id' => $id1,
                'shared_points_count' => $sharedCount,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $totalPairs++;

            if (count($batch) >= 1000) {
                DB::table('region_neighbors')->insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('region_neighbors')->insert($batch);
            $batch = [];
        }

        unset($pairSharedCount);

        return $totalPairs;
    }

    /**
     * Recursively extract coordinate points and index them to DB IDs
     */
    protected function extractPoints(array $coordinates, int $dbId, array &$pointToDbIds, int $precision): void
    {
        if (
            count($coordinates) === 2 &&
            is_numeric($coordinates[0]) &&
            is_numeric($coordinates[1])
        ) {
            $key = round($coordinates[0], $precision) . ':' . round($coordinates[1], $precision);
            $pointToDbIds[$key][] = $dbId;
            return;
        }

        foreach ($coordinates as $item) {
            if (is_array($item)) {
                $this->extractPoints($item, $dbId, $pointToDbIds, $precision);
            }
        }
    }

    /**
     * Helper to extract standard RW string
     */
    protected function extractRw(?string $nmsls, ?string $nmsubsls, array $props): ?string
    {
        $text = ($nmsls ?? '') . ' ' . ($nmsubsls ?? '');
        if (preg_match('/RW\s*0*([0-9]+)/i', $text, $matches)) {
            return 'RW ' . str_pad($matches[1], 3, '0', STR_PAD_LEFT);
        }
        return null;
    }

    /**
     * Helper to extract standard RT string
     */
    protected function extractRt(?string $nmsls, ?string $nmsubsls, array $props): ?string
    {
        $text = ($nmsls ?? '') . ' ' . ($nmsubsls ?? '');
        if (preg_match('/RT\s*0*([0-9]+)/i', $text, $matches)) {
            return 'RT ' . str_pad($matches[1], 3, '0', STR_PAD_LEFT);
        }
        return null;
    }
}
