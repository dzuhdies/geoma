<?php

namespace App\Services;

use App\Models\ChangedRegion;
use App\Models\ChangeRelation;
use App\Models\Region;

class ChangeValidationService
{
    public function __construct(
        protected NeighborService $neighborService
    ) {}

    /**
     * Validate and create a directional change relation from region A to neighbor B.
     * Throws an exception or returns error if they are not direct neighbors.
     */
    public function validateAndCreateRelation(int $regionAId, int $regionBId, ?string $notes = null): ChangeRelation
    {
        if ($regionAId === $regionBId) {
            throw new \InvalidArgumentException('Sebuah wilayah tidak dapat dihubungkan dengan dirinya sendiri.');
        }

        $regionA = Region::findOrFail($regionAId);
        $regionB = Region::findOrFail($regionBId);

        // Rule: Relation must be between direct geometric neighbors
        if (!$this->neighborService->areNeighbors($regionAId, $regionBId)) {
            $nameA = $regionA->nmsls ?: $regionA->nmsubsls ?: "ID {$regionA->id}";
            $nameB = $regionB->nmsls ?: $regionB->nmsubsls ?: "ID {$regionB->id}";
            throw new \InvalidArgumentException("{$nameB} bukan merupakan tetangga langsung {$nameA} berdasarkan data GeoJSON.");
        }

        return ChangeRelation::createDirectional($regionAId, $regionBId, $notes);
    }

    /**
     * Delete a directional relation from region A to neighbor B
     */
    public function deleteRelation(int $regionAId, int $regionBId): bool
    {
        $relation = ChangeRelation::findDirectional($regionAId, $regionBId);
        if ($relation) {
            return (bool)$relation->delete();
        }
        return false;
    }

    /**
     * Get consistency warnings for a specific region
     */
    public function getWarningsForRegion(Region|int $region): array
    {
        $regionModel = $region instanceof Region ? $region : Region::findOrFail($region);
        $warnings = [];

        $currentName = $regionModel->nmsls ?: $regionModel->nmsubsls ?: "RT {$regionModel->id}";
        $isChanged = $regionModel->isChanged();

        // 1. Outgoing relations (A -> B)
        $outgoingRelations = ChangeRelation::with('regionB.changedRegion')
            ->where('region_id_a', $regionModel->id)
            ->get();

        if ($isChanged && $outgoingRelations->isEmpty()) {
            $warnings[] = [
                'type' => 'no_relations_selected',
                'relation_id' => null,
                'region_id' => $regionModel->id,
                'region_name' => $currentName,
                'current_desa' => $regionModel->nmdesa,
                'current_kec' => $regionModel->nmkec,
                'current_rw' => $regionModel->rw,
                'target_region_id' => null,
                'target_region_name' => null,
                'target_desa' => null,
                'target_kec' => null,
                'target_rw' => null,
                'message' => "{$currentName} ditandai sebagai berubah, tetapi belum menentukan RT pasangan yang berubah dengannya.",
            ];
        }

        foreach ($outgoingRelations as $rel) {
            $target = $rel->regionB;
            if (!$target) continue;

            $targetName = $target->nmsls ?: $target->nmsubsls ?: "RT {$target->id}";
            $targetIsChanged = ($target->changedRegion !== null);

            if (!$isChanged) {
                $warnings[] = [
                    'type' => 'self_not_changed',
                    'relation_id' => $rel->id,
                    'region_id' => $regionModel->id,
                    'region_name' => $currentName,
                    'current_desa' => $regionModel->nmdesa,
                    'current_kec' => $regionModel->nmkec,
                    'current_rw' => $regionModel->rw,
                    'target_region_id' => $target->id,
                    'target_region_name' => $targetName,
                    'target_desa' => $target->nmdesa,
                    'target_kec' => $target->nmkec,
                    'target_rw' => $target->rw,
                    'message' => "{$currentName} memiliki relasi perubahan dengan {$targetName}, tetapi {$currentName} belum ditandai sebagai berubah.",
                ];
            } elseif (!$targetIsChanged) {
                // Partner not changed
                $warnings[] = [
                    'type' => 'partner_not_changed',
                    'relation_id' => $rel->id,
                    'region_id' => $regionModel->id,
                    'region_name' => $currentName,
                    'current_desa' => $regionModel->nmdesa,
                    'current_kec' => $regionModel->nmkec,
                    'current_rw' => $regionModel->rw,
                    'target_region_id' => $target->id,
                    'target_region_name' => $targetName,
                    'target_desa' => $target->nmdesa,
                    'target_kec' => $target->nmkec,
                    'target_rw' => $target->rw,
                    'message' => "{$currentName} menyatakan berubah dengan {$targetName}, tetapi {$targetName} belum ditandai sebagai berubah.",
                ];
            } else {
                // Partner is changed, but did partner check A back?
                $reciprocalExists = ChangeRelation::where('region_id_a', $target->id)
                    ->where('region_id_b', $regionModel->id)
                    ->exists();

                if (!$reciprocalExists) {
                    $warnings[] = [
                        'type' => 'unreciprocated_relation',
                        'relation_id' => $rel->id,
                        'region_id' => $regionModel->id,
                        'region_name' => $currentName,
                        'current_desa' => $regionModel->nmdesa,
                        'current_kec' => $regionModel->nmkec,
                        'current_rw' => $regionModel->rw,
                        'target_region_id' => $target->id,
                        'target_region_name' => $targetName,
                        'target_desa' => $target->nmdesa,
                        'target_kec' => $target->nmkec,
                        'target_rw' => $target->rw,
                        'message' => "{$currentName} menyatakan berubah dengan {$targetName}, namun {$targetName} belum memilih {$currentName} sebagai pasangannya.",
                    ];
                }
            }
        }

        // 2. Incoming relations (B -> A) where A has not selected B
        $incomingRelations = ChangeRelation::with('regionA.changedRegion')
            ->where('region_id_b', $regionModel->id)
            ->get();

        foreach ($incomingRelations as $rel) {
            $source = $rel->regionA;
            if (!$source) continue;

            $sourceName = $source->nmsls ?: $source->nmsubsls ?: "RT {$source->id}";
            $alreadyHandled = $outgoingRelations->contains('region_id_b', $source->id);

            if (!$alreadyHandled && $isChanged) {
                $warnings[] = [
                    'type' => 'incoming_unconfirmed',
                    'relation_id' => $rel->id,
                    'region_id' => $regionModel->id,
                    'region_name' => $currentName,
                    'current_desa' => $regionModel->nmdesa,
                    'current_kec' => $regionModel->nmkec,
                    'current_rw' => $regionModel->rw,
                    'target_region_id' => $source->id,
                    'target_region_name' => $sourceName,
                    'target_desa' => $source->nmdesa,
                    'target_kec' => $source->nmkec,
                    'target_rw' => $source->rw,
                    'message' => "{$sourceName} menyatakan berubah dengan {$currentName}, tetapi {$currentName} belum mencentang {$sourceName}.",
                ];
            }
        }

        return $warnings;
    }

    /**
     * Get system-wide validation report and metrics
     */
    public function getSystemValidationReport(): array
    {
        $totalRegions = Region::count();
        $totalChanged = ChangedRegion::count();
        $totalRelations = ChangeRelation::count();

        $warnings = [];

        // 1. Changed regions with 0 declared relations
        $changedRegions = ChangedRegion::with(['region.changeRelationsAsA'])->get();
        foreach ($changedRegions as $changed) {
            $reg = $changed->region;
            if (!$reg) continue;

            if ($reg->changeRelationsAsA->isEmpty()) {
                $name = $reg->nmsls ?: $reg->nmsubsls ?: "RT {$reg->id}";
                $warnings[] = [
                    'relation_id' => null,
                    'type' => 'no_relations_selected',
                    'region_id_a' => $reg->id,
                    'region_id_b' => null,
                    'idsubsls_a' => $reg->idsubsls,
                    'idsls_a' => $reg->idsls,
                    'idsubsls_b' => null,
                    'idsls_b' => null,
                    'name_a' => $name,
                    'name_b' => null,
                    'desa_a' => $reg->nmdesa,
                    'desa_b' => null,
                    'kec_a' => $reg->nmkec,
                    'kec_b' => null,
                    'rw_a' => $reg->rw,
                    'rw_b' => null,
                    'rt_a' => $reg->rt,
                    'rt_b' => null,
                    'target_unmarked_id' => null,
                    'target_unmarked_name' => null,
                    'message' => "{$name} ditandai berubah, namun belum memilih pasangan RT perubahannya.",
                ];
            }
        }

        // 2. Relations checks
        $relations = ChangeRelation::with(['regionA.changedRegion', 'regionB.changedRegion'])->get();
        
        $pairSet = [];
        foreach ($relations as $rel) {
            $pairSet["{$rel->region_id_a}_{$rel->region_id_b}"] = true;
        }

        foreach ($relations as $rel) {
            $a = $rel->regionA;
            $b = $rel->regionB;
            if (!$a || !$b) continue;

            $aChanged = ($a->changedRegion !== null);
            $bChanged = ($b->changedRegion !== null);

            $nameA = $a->nmsls ?: $a->nmsubsls ?: "RT ID {$rel->region_id_a}";
            $nameB = $b->nmsls ?: $b->nmsubsls ?: "RT ID {$rel->region_id_b}";

            if (!$aChanged && $bChanged) {
                $warnings[] = [
                    'relation_id' => $rel->id,
                    'type' => 'self_not_changed',
                    'region_id_a' => $rel->region_id_a,
                    'region_id_b' => $rel->region_id_b,
                    'idsubsls_a' => $a->idsubsls,
                    'idsls_a' => $a->idsls,
                    'idsubsls_b' => $b->idsubsls,
                    'idsls_b' => $b->idsls,
                    'name_a' => $nameA,
                    'name_b' => $nameB,
                    'desa_a' => $a->nmdesa,
                    'desa_b' => $b->nmdesa,
                    'kec_a' => $a->nmkec,
                    'kec_b' => $b->nmkec,
                    'rw_a' => $a->rw,
                    'rw_b' => $b->rw,
                    'rt_a' => $a->rt,
                    'rt_b' => $b->rt,
                    'target_unmarked_id' => $rel->region_id_a,
                    'target_unmarked_name' => $nameA,
                    'message' => "{$nameA} memiliki relasi perubahan dengan {$nameB}, tetapi {$nameA} belum ditandai sebagai berubah.",
                ];
            } elseif ($aChanged && !$bChanged) {
                $warnings[] = [
                    'relation_id' => $rel->id,
                    'type' => 'partner_not_changed',
                    'region_id_a' => $rel->region_id_a,
                    'region_id_b' => $rel->region_id_b,
                    'idsubsls_a' => $a->idsubsls,
                    'idsls_a' => $a->idsls,
                    'idsubsls_b' => $b->idsubsls,
                    'idsls_b' => $b->idsls,
                    'name_a' => $nameA,
                    'name_b' => $nameB,
                    'desa_a' => $a->nmdesa,
                    'desa_b' => $b->nmdesa,
                    'kec_a' => $a->nmkec,
                    'kec_b' => $b->nmkec,
                    'rw_a' => $a->rw,
                    'rw_b' => $b->rw,
                    'rt_a' => $a->rt,
                    'rt_b' => $b->rt,
                    'target_unmarked_id' => $rel->region_id_b,
                    'target_unmarked_name' => $nameB,
                    'message' => "{$nameA} menyatakan berubah dengan {$nameB}, tetapi {$nameB} belum ditandai sebagai berubah.",
                ];
            } elseif ($aChanged && $bChanged) {
                $reciprocalKey = "{$rel->region_id_b}_{$rel->region_id_a}";
                if (!isset($pairSet[$reciprocalKey])) {
                    $warnings[] = [
                        'relation_id' => $rel->id,
                        'type' => 'unreciprocated_relation',
                        'region_id_a' => $rel->region_id_a,
                        'region_id_b' => $rel->region_id_b,
                        'idsubsls_a' => $a->idsubsls,
                        'idsls_a' => $a->idsls,
                        'idsubsls_b' => $b->idsubsls,
                        'idsls_b' => $b->idsls,
                        'name_a' => $nameA,
                        'name_b' => $nameB,
                        'desa_a' => $a->nmdesa,
                        'desa_b' => $b->nmdesa,
                        'kec_a' => $a->nmkec,
                        'kec_b' => $b->nmkec,
                        'rw_a' => $a->rw,
                        'rw_b' => $b->rw,
                        'rt_a' => $a->rt,
                        'rt_b' => $b->rt,
                        'target_unmarked_id' => $rel->region_id_b,
                        'target_unmarked_name' => $nameB,
                        'message' => "{$nameA} menyatakan berubah dengan {$nameB}, namun {$nameB} belum memilih {$nameA} sebagai pasangannya.",
                    ];
                }
            } elseif (!$aChanged && !$bChanged) {
                $warnings[] = [
                    'relation_id' => $rel->id,
                    'type' => 'both_not_changed',
                    'region_id_a' => $rel->region_id_a,
                    'region_id_b' => $rel->region_id_b,
                    'idsubsls_a' => $a->idsubsls,
                    'idsls_a' => $a->idsls,
                    'idsubsls_b' => $b->idsubsls,
                    'idsls_b' => $b->idsls,
                    'name_a' => $nameA,
                    'name_b' => $nameB,
                    'desa_a' => $a->nmdesa,
                    'desa_b' => $b->nmdesa,
                    'kec_a' => $a->nmkec,
                    'kec_b' => $b->nmkec,
                    'rw_a' => $a->rw,
                    'rw_b' => $b->rw,
                    'rt_a' => $a->rt,
                    'rt_b' => $b->rt,
                    'target_unmarked_id' => $rel->region_id_a,
                    'target_unmarked_name' => $nameA,
                    'message' => "Hubungan antara {$nameA} dan {$nameB} ada, namun keduanya belum ditandai sebagai berubah.",
                ];
            }
        }

        return [
            'summary' => [
                'total_regions' => $totalRegions,
                'total_changed' => $totalChanged,
                'total_relations' => $totalRelations,
                'total_warnings' => count($warnings),
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * Get detailed list of all changed regions with their relations and status
     */
    public function getChangedRegionsList(): array
    {
        $changedList = ChangedRegion::with([
            'region.changeRelationsAsA.regionB.changedRegion',
            'region.changeRelationsAsB.regionA.changedRegion',
        ])->get();

        $allRelations = ChangeRelation::all();
        $pairSet = [];
        foreach ($allRelations as $rel) {
            $pairSet["{$rel->region_id_a}_{$rel->region_id_b}"] = true;
        }

        $result = [];
        foreach ($changedList as $cr) {
            $r = $cr->region;
            if (!$r) continue;

            $name = $r->nmsls ?: $r->nmsubsls ?: "RT {$r->id}";
            $partners = [];

            // Outgoing partners (declared by this region)
            foreach ($r->changeRelationsAsA as $rel) {
                $target = $rel->regionB;
                if (!$target) continue;

                $targetName = $target->nmsls ?: $target->nmsubsls ?: "RT {$target->id}";
                $targetIsChanged = ($target->changedRegion !== null);
                $isReciprocal = isset($pairSet["{$target->id}_{$r->id}"]);

                $partners[] = [
                    'id' => $target->id,
                    'idsubsls' => $target->idsubsls,
                    'idsls' => $target->idsls,
                    'name' => $targetName,
                    'nmsls' => $target->nmsls,
                    'nmsubsls' => $target->nmsubsls,
                    'nmdesa' => $target->nmdesa,
                    'nmkec' => $target->nmkec,
                    'rw' => $target->rw,
                    'rt' => $target->rt,
                    'is_changed' => $targetIsChanged,
                    'is_reciprocal' => $isReciprocal,
                    'direction' => 'outgoing',
                    'notes' => $rel->notes,
                ];
            }

            // Also check incoming partners (who selected this region, but this region hasn't selected them back)
            $outgoingPartnerIds = collect($partners)->pluck('id')->all();
            foreach ($r->changeRelationsAsB as $relB) {
                $source = $relB->regionA;
                if (!$source) continue;

                if (!in_array($source->id, $outgoingPartnerIds)) {
                    $sourceName = $source->nmsls ?: $source->nmsubsls ?: "RT {$source->id}";
                    $sourceIsChanged = ($source->changedRegion !== null);

                    $partners[] = [
                        'id' => $source->id,
                        'idsubsls' => $source->idsubsls,
                        'idsls' => $source->idsls,
                        'name' => $sourceName,
                        'nmsls' => $source->nmsls,
                        'nmsubsls' => $source->nmsubsls,
                        'nmdesa' => $source->nmdesa,
                        'nmkec' => $source->nmkec,
                        'rw' => $source->rw,
                        'rt' => $source->rt,
                        'is_changed' => $sourceIsChanged,
                        'is_reciprocal' => false,
                        'direction' => 'incoming_unreciprocated',
                        'notes' => $relB->notes,
                    ];
                }
            }

            // Determine status for this changed region
            $status = 'valid';
            if (empty($partners)) {
                $status = 'no_relations';
            } else {
                $hasUnreciprocated = false;
                $hasUnmarkedPartner = false;
                foreach ($partners as $p) {
                    if (!$p['is_changed']) {
                        $hasUnmarkedPartner = true;
                    } elseif (!$p['is_reciprocal']) {
                        $hasUnreciprocated = true;
                    }
                }
                if ($hasUnmarkedPartner) {
                    $status = 'partner_not_marked';
                } elseif ($hasUnreciprocated) {
                    $status = 'pending_reciprocal';
                }
            }

            $result[] = [
                'id' => $r->id,
                'region_id' => $r->id,
                'idsubsls' => $r->idsubsls,
                'idsls' => $r->idsls,
                'name' => $name,
                'nmsls' => $r->nmsls,
                'nmsubsls' => $r->nmsubsls,
                'nmdesa' => $r->nmdesa,
                'nmkec' => $r->nmkec,
                'rw' => $r->rw,
                'rt' => $r->rt,
                'notes' => $cr->notes,
                'status' => $status,
                'partners_count' => count($partners),
                'partners' => $partners,
                'created_at' => $cr->created_at?->toIso8601String(),
                'updated_at' => $cr->updated_at?->toIso8601String(),
            ];
        }

        // Sort by Kecamatan, Kelurahan, RW, RT
        usort($result, function ($a, $b) {
            $cmpKec = strcmp($a['nmkec'] ?? '', $b['nmkec'] ?? '');
            if ($cmpKec !== 0) return $cmpKec;
            $cmpDesa = strcmp($a['nmdesa'] ?? '', $b['nmdesa'] ?? '');
            if ($cmpDesa !== 0) return $cmpDesa;
            $cmpRw = strcmp($a['rw'] ?? '', $b['rw'] ?? '');
            if ($cmpRw !== 0) return $cmpRw;
            return strcmp($a['rt'] ?? '', $b['rt'] ?? '');
        });

        return $result;
    }
}
