<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'objectid',
        'kdprov',
        'kdkab',
        'kdkec',
        'kddesa',
        'kdsls',
        'nmprov',
        'nmkab',
        'nmkec',
        'nmdesa',
        'nmsubsls',
        'idsls',
        'iddesa',
        'nmsls',
        'idsubsls',
        'jenis',
        'rw',
        'rt',
        'geometry',
    ];

    protected $casts = [
        'objectid' => 'integer',
        'geometry' => 'array',
    ];

    /**
     * Relationship to ChangedRegion (if explicitly marked changed)
     */
    public function changedRegion(): HasOne
    {
        return $this->hasOne(ChangedRegion::class, 'region_id');
    }

    /**
     * Relationship to direct geometric neighbors
     */
    public function neighbors(): BelongsToMany
    {
        return $this->belongsToMany(
            Region::class,
            'region_neighbors',
            'region_id',
            'neighbor_id'
        )->withPivot('shared_points_count')->withTimestamps();
    }

    /**
     * Change relations where this region is region_a
     */
    public function changeRelationsAsA(): HasMany
    {
        return $this->hasMany(ChangeRelation::class, 'region_id_a');
    }

    /**
     * Change relations where this region is region_b
     */
    public function changeRelationsAsB(): HasMany
    {
        return $this->hasMany(ChangeRelation::class, 'region_id_b');
    }

    /**
     * Check if this region is explicitly marked as changed
     */
    public function isChanged(): bool
    {
        return $this->relationLoaded('changedRegion')
            ? $this->changedRegion !== null
            : $this->changedRegion()->exists();
    }

    /**
     * Check if this region is neighbor to another region ID
     */
    public function isNeighborOf(int|Region $other): bool
    {
        $otherId = $other instanceof Region ? $other->id : $other;
        return $this->neighbors()->where('neighbor_id', $otherId)->exists();
    }

    /**
     * Get all related regions in change relations declared by this region
     */
    public function getRelatedChangedRegionIds(): array
    {
        return $this->changeRelationsAsA()->pluck('region_id_b')->all();
    }
}
