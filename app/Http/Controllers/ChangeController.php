<?php

namespace App\Http\Controllers;

use App\Models\ChangedRegion;
use App\Models\ChangeRelation;
use App\Models\Region;
use App\Services\ChangeValidationService;
use App\Services\NeighborService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangeController extends Controller
{
    public function __construct(
        protected ChangeValidationService $validationService,
        protected NeighborService $neighborService
    ) {}

    /**
     * Get list of all changed regions with relations and details.
     */
    public function index(): JsonResponse
    {
        $changedRegions = $this->validationService->getChangedRegionsList();

        return response()->json([
            'total' => count($changedRegions),
            'data' => $changedRegions,
        ]);
    }

    /**
     * Export all changed regions to CSV
     */
    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $changedRegions = $this->validationService->getChangedRegionsList();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="wilayah_berubah_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream(function () use ($changedRegions) {
            $output = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fputs($output, "\xEF\xBB\xBF");
            // Header row
            fputcsv($output, ['ID SLS', 'Kecamatan', 'Kelurahan', 'RT RW Berubah', 'RT RW Berubah Dengan', 'Status']);

            foreach ($changedRegions as $item) {
                $statusText = match($item['status'] ?? '') {
                    'valid' => 'Lengkap (2 Arah)',
                    'no_relations' => 'Belum Pilih Pasangan',
                    'partner_not_marked' => 'Pasangan Belum Ditandai',
                    'pending_reciprocal' => 'Menunggu Timbal Balik (1 Arah)',
                    default => 'Berubah'
                };

                // Format "RT RW Berubah"
                $rtRwBerubah = $item['nmsls'] ?: $item['nmsubsls'] ?: ('RT ' . ($item['rt'] ?? '-') . ' RW ' . ($item['rw'] ?? '-'));

                // Format "RT RW Berubah Dengan"
                if (!empty($item['partners'])) {
                    $partnerStrings = array_map(function ($p) use ($item) {
                        $pName = $p['name'] ?: ('RT ' . ($p['rt'] ?? '-') . ' RW ' . ($p['rw'] ?? '-'));
                        if (!empty($p['nmdesa']) && $p['nmdesa'] !== ($item['nmdesa'] ?? '')) {
                            $pName .= ' [Kel. ' . $p['nmdesa'] . ']';
                        }
                        return $pName;
                    }, $item['partners']);
                    $berubahDengan = implode('; ', $partnerStrings);
                } else {
                    $berubahDengan = '-';
                }

                fputcsv($output, [
                    $item['idsubsls'] ?? $item['idsls'] ?? '',
                    $item['nmkec'] ?? '',
                    $item['nmdesa'] ?? '',
                    $rtRwBerubah,
                    $berubahDengan,
                    $statusText,
                ]);
            }
            fclose($output);
        }, 200, $headers);
    }

    /**
     * Mark a region as changed.
     */
    public function markChanged(Request $request, int $id): JsonResponse
    {
        $region = Region::findOrFail($id);
        $notes = $request->input('notes');

        $changed = ChangedRegion::updateOrCreate(
            ['region_id' => $region->id],
            ['notes' => $notes]
        );

        $warnings = $this->validationService->getWarningsForRegion($region);

        return response()->json([
            'success' => true,
            'message' => "{$region->nmsls} berhasil ditandai sebagai Berubah.",
            'region_id' => $region->id,
            'is_changed' => true,
            'notes' => $changed->notes,
            'warnings' => $warnings,
        ]);
    }

    /**
     * Unmark a region as changed (remove from changed_regions).
     * Also handles related change relations if requested.
     */
    public function unmarkChanged(Request $request, int $id): JsonResponse
    {
        $region = Region::findOrFail($id);

        // Delete from changed_regions
        ChangedRegion::where('region_id', $region->id)->delete();

        // Check if operator wants to delete associated relations or keep them (which will trigger warning)
        $cleanupRelations = $request->boolean('cleanup_relations', false);
        if ($cleanupRelations) {
            ChangeRelation::where('region_id_a', $region->id)
                ->orWhere('region_id_b', $region->id)
                ->delete();
        }

        $warnings = $this->validationService->getWarningsForRegion($region);

        return response()->json([
            'success' => true,
            'message' => "{$region->nmsls} dihapus dari daftar perubahan.",
            'region_id' => $region->id,
            'is_changed' => false,
            'warnings' => $warnings,
        ]);
    }

    /**
     * Add change relation between region A and neighbor B.
     */
    public function addRelation(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'target_region_id' => 'required|integer|exists:regions,id',
            'notes' => 'nullable|string',
        ]);

        $targetId = (int)$request->input('target_region_id');

        try {
            $relation = $this->validationService->validateAndCreateRelation(
                $id,
                $targetId,
                $request->input('notes')
            );

            $region = Region::findOrFail($id);
            $warnings = $this->validationService->getWarningsForRegion($region);

            return response()->json([
                'success' => true,
                'message' => 'Hubungan perubahan berhasil disimpan.',
                'relation' => $relation,
                'warnings' => $warnings,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove change relation between region A and neighbor B.
     */
    public function removeRelation(Request $request, int $id): JsonResponse
    {
        $targetId = (int)$request->input('target_region_id');

        if (!$targetId) {
            return response()->json([
                'success' => false,
                'message' => 'Target region ID diperlukan.',
            ], 400);
        }

        $deleted = $this->validationService->deleteRelation($id, $targetId);

        $region = Region::findOrFail($id);
        $warnings = $this->validationService->getWarningsForRegion($region);

        return response()->json([
            'success' => $deleted,
            'message' => $deleted ? 'Hubungan perubahan berhasil dihapus.' : 'Hubungan tidak ditemukan.',
            'warnings' => $warnings,
        ]);
    }
}
