<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Services\NeighborService;
use App\Services\ChangeValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function __construct(
        protected NeighborService $neighborService,
        protected ChangeValidationService $validationService
    ) {}

    /**
     * Display the main interactive dashboard view.
     */
    public function index(): View
    {
        $kecamatanList = Region::select('nmkec')
            ->whereNotNull('nmkec')
            ->distinct()
            ->orderBy('nmkec')
            ->pluck('nmkec');

        $user = auth()->user();

        return view('dashboard', [
            'kecamatanList' => $kecamatanList,
            'isAuthenticated' => auth()->check(),
            'authUser' => $user ? [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
            ] : null,
        ]);
    }

    /**
     * Get cascading filter options.
     */
    public function filterOptions(Request $request): JsonResponse
    {
        $kec = $request->query('kecamatan');
        $desa = $request->query('desa');
        $rw = $request->query('rw');

        $query = Region::query();

        if ($kec) {
            $query->where('nmkec', $kec);
        }

        if ($desa) {
            $query->where('nmdesa', $desa);
        }

        if ($rw) {
            $query->where('rw', $rw);
        }

        // Return appropriate options depending on level
        $desas = $kec ? Region::where('nmkec', $kec)->whereNotNull('nmdesa')->distinct()->orderBy('nmdesa')->pluck('nmdesa') : [];
        $rws = ($kec && $desa) ? Region::where('nmkec', $kec)->where('nmdesa', $desa)->whereNotNull('rw')->distinct()->orderBy('rw')->pluck('rw') : [];
        
        $rts = [];
        if ($kec && $desa && $rw) {
            $rts = Region::with('changedRegion')
                ->where('nmkec', $kec)
                ->where('nmdesa', $desa)
                ->where('rw', $rw)
                ->orderBy('rt')
                ->orderBy('nmsls')
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'nmsls' => $r->nmsls,
                    'nmsubsls' => $r->nmsubsls,
                    'rt' => $r->rt,
                    'is_changed' => $r->changedRegion !== null,
                ]);
        }

        return response()->json([
            'desas' => $desas,
            'rws' => $rws,
            'rts' => $rts,
        ]);
    }

    /**
     * Get GeoJSON FeatureCollection based on filter parameters with changed status indicators.
     */
    public function geojson(Request $request): JsonResponse
    {
        $kec = $request->query('kecamatan');
        $desa = $request->query('desa');
        $rw = $request->query('rw');
        $rt = $request->query('rt');
        $regionId = $request->query('region_id');

        $query = Region::with('changedRegion');

        if ($regionId) {
            $query->where('id', $regionId);
        } else {
            if ($kec) {
                $query->where('nmkec', $kec);
            }
            if ($desa) {
                $query->where('nmdesa', $desa);
            }
            if ($rw) {
                $query->where('rw', $rw);
            }
            if ($rt) {
                $query->where('rt', $rt);
            }
        }

        $regions = $query->get();

        $features = $regions->map(function (Region $region) {
            $geometry = is_string($region->geometry) ? json_decode($region->geometry, true) : $region->geometry;
            return [
                'type' => 'Feature',
                'id' => $region->id,
                'properties' => [
                    'id' => $region->id,
                    'idsubsls' => $region->idsubsls,
                    'nmsls' => $region->nmsls,
                    'nmsubsls' => $region->nmsubsls,
                    'nmdesa' => $region->nmdesa,
                    'nmkec' => $region->nmkec,
                    'rw' => $region->rw,
                    'rt' => $region->rt,
                    'is_changed' => $region->changedRegion !== null,
                    'notes' => $region->changedRegion?->notes,
                ],
                'geometry' => $geometry,
            ];
        });

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Get detail of a specific region.
     */
    public function show(int $id): JsonResponse
    {
        $region = Region::with('changedRegion')->findOrFail($id);
        $neighbors = $this->neighborService->getNeighbors($region);
        $warnings = $this->validationService->getWarningsForRegion($region);
        $relatedIds = $region->getRelatedChangedRegionIds();

        return response()->json([
            'id' => $region->id,
            'idsubsls' => $region->idsubsls,
            'nmsls' => $region->nmsls,
            'nmsubsls' => $region->nmsubsls,
            'nmdesa' => $region->nmdesa,
            'nmkec' => $region->nmkec,
            'nmkab' => $region->nmkab,
            'nmprov' => $region->nmprov,
            'rw' => $region->rw,
            'rt' => $region->rt,
            'is_changed' => $region->changedRegion !== null,
            'notes' => $region->changedRegion?->notes,
            'neighbors' => $neighbors,
            'related_region_ids' => $relatedIds,
            'warnings' => $warnings,
            'geometry' => is_string($region->geometry) ? json_decode($region->geometry, true) : $region->geometry,
        ]);
    }

    /**
     * Get direct neighbors of a specific region.
     */
    public function neighbors(int $id): JsonResponse
    {
        $region = Region::findOrFail($id);
        $neighbors = $this->neighborService->getNeighbors($region);

        return response()->json([
            'region_id' => $region->id,
            'region_name' => $region->nmsls,
            'neighbors' => $neighbors,
        ]);
    }

    /**
     * Search regions across the entire database by idsubsls, idsls, name, rt, rw, etc.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $query = Region::with('changedRegion');

        $query->where(function ($sub) use ($q) {
            $sub->where('idsubsls', 'like', "%{$q}%")
                ->orWhere('idsls', 'like', "%{$q}%")
                ->orWhere('nmsls', 'like', "%{$q}%")
                ->orWhere('nmsubsls', 'like', "%{$q}%")
                ->orWhere('nmdesa', 'like', "%{$q}%")
                ->orWhere('nmkec', 'like', "%{$q}%");

            if (is_numeric($q)) {
                $sub->orWhere('id', (int)$q)
                    ->orWhere('objectid', (int)$q);
            }
        });

        $results = $query->limit(25)->get()->map(function (Region $region) {
            return [
                'id' => $region->id,
                'idsubsls' => $region->idsubsls,
                'idsls' => $region->idsls,
                'nmsls' => $region->nmsls,
                'nmsubsls' => $region->nmsubsls,
                'nmdesa' => $region->nmdesa,
                'nmkec' => $region->nmkec,
                'rw' => $region->rw,
                'rt' => $region->rt,
                'is_changed' => $region->changedRegion !== null,
            ];
        });

        return response()->json($results);
    }
}
