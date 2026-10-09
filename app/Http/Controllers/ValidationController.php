<?php

namespace App\Http\Controllers;

use App\Services\ChangeValidationService;
use Illuminate\Http\JsonResponse;

class ValidationController extends Controller
{
    public function __construct(
        protected ChangeValidationService $validationService
    ) {}

    /**
     * Get system-wide validation summary and warnings list.
     */
    public function index(): JsonResponse
    {
        $report = $this->validationService->getSystemValidationReport();

        return response()->json($report);
    }

    /**
     * Export all validation warnings to CSV
     */
    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $report = $this->validationService->getSystemValidationReport();
        $warnings = $report['warnings'] ?? [];

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="warning_validasi_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream(function () use ($warnings) {
            $output = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fputs($output, "\xEF\xBB\xBF");
            // Header row
            fputcsv($output, ['ID SLS', 'Kecamatan', 'Kelurahan', 'RW', 'RT', 'Status', 'Dengan ID SLS', 'Dengan RW', 'Dengan RT', 'Dengan Kelurahan', 'Warning Message']);

            foreach ($warnings as $w) {
                $statusText = match($w['type'] ?? '') {
                    'unreciprocated_relation' => 'Pasangan Belum Cocok',
                    'no_relations_selected' => 'Belum Pilih Pasangan',
                    'partner_not_changed' => 'Pasangan Belum Berubah',
                    'self_not_changed' => 'Wilayah Belum Berubah',
                    'both_not_changed' => 'Kedua Wilayah Belum Berubah',
                    default => 'Warning'
                };

                $idslsB = $w['idsubsls_b'] ?? $w['idsls_b'] ?? '-';
                $rwB = $w['rw_b'] ?? '-';
                $rtB = $w['rt_b'] ?? '-';
                $desaB = $w['desa_b'] ?? '-';

                fputcsv($output, [
                    $w['idsubsls_a'] ?? $w['idsls_a'] ?? '',
                    $w['kec_a'] ?? '',
                    $w['desa_a'] ?? '',
                    $w['rw_a'] ?? '',
                    $w['rt_a'] ?? '',
                    $statusText,
                    $idslsB ?: '-',
                    $rwB ?: '-',
                    $rtB ?: '-',
                    $desaB ?: '-',
                    $w['message'] ?? '',
                ]);
            }
            fclose($output);
        }, 200, $headers);
    }
}
