<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRelation extends Model
{
    use HasFactory;

    protected $fillable = [
        'region_id_a',
        'region_id_b',
        'notes',
    ];

    /**
     * Relationship to region A
     */
    public function regionA(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id_a');
    }

    /**
     * Relationship to region B
     */
    public function regionB(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id_b');
    }

    /**
     * Create or find a directional relation from region A to region B
     */
    public static function createDirectional(int $regionA, int $regionB, ?string $notes = null): self
    {
        return self::firstOrCreate(
            ['region_id_a' => $regionA, 'region_id_b' => $regionB],
            ['notes' => $notes]
        );
    }

    /**
     * Find directional relation from region A to region B
     */
    public static function findDirectional(int $regionA, int $regionB): ?self
    {
        return self::where('region_id_a', $regionA)->where('region_id_b', $regionB)->first();
    }
}
