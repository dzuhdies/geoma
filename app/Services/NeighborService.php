<?php

namespace App\Services;

use App\Models\ChangeRelation;
use App\Models\Region;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class NeighborService
{
    /**
     * Get direct geometric neighbors for a region with change status and relation status.
     */
    public function getNeighbors(Region|int $region): \Illuminate\Support\Collection
    {
        $regionModel = $region instanceof Region ? $region : Region::findOrFail($region);

        // Fetch neighbors with their changed status
        $neighbors = $regionModel->neighbors()
            ->with('changedRegion')
            ->select('regions.*')
            ->get();

        // Get existing change relation pairs involving this region
        $relatedIds = $regionModel->getRelatedChangedRegionIds();

        return $neighbors->map(function (Region $neighbor) use ($relatedIds, $regionModel) {
            $isDifferentDesa = ($neighbor->nmdesa !== $regionModel->nmdesa);
            $isDifferentKec = ($neighbor->nmkec !== $regionModel->nmkec);
            $isDifferentRw = ($neighbor->rw !== $regionModel->rw);

            return [
                'id' => $neighbor->id,
                'idsubsls' => $neighbor->idsubsls,
                'idsls' => $neighbor->idsls,
                'nmsls' => $neighbor->nmsls,
                'nmsubsls' => $neighbor->nmsubsls,
                'nmdesa' => $neighbor->nmdesa,
                'nmkec' => $neighbor->nmkec,
                'rw' => $neighbor->rw,
                'rt' => $neighbor->rt,
                'is_changed' => $neighbor->changedRegion !== null,
                'notes' => $neighbor->changedRegion?->notes,
                'is_relation' => in_array($neighbor->id, $relatedIds, true),
                'shared_points_count' => $neighbor->pivot->shared_points_count ?? 1,
                'is_cross_border_desa' => $isDifferentDesa,
                'is_cross_border_kec' => $isDifferentKec,
                'is_cross_border_rw' => $isDifferentRw,
                'geometry' => is_string($neighbor->geometry) ? json_decode($neighbor->geometry, true) : $neighbor->geometry,
            ];
        });
    }

    /**
     * Check if two regions are direct geometric neighbors
     */
    public function areNeighbors(int $regionA, int $regionB): bool
    {
        if ($regionA === $regionB) {
            return false;
        }

        return DB::table('region_neighbors')
            ->where('region_id', $regionA)
            ->where('neighbor_id', $regionB)
            ->exists();
    }
}
