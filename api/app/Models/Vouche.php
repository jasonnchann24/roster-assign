<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vouche extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vouched_by_id',
        'vouched_for_id',
        'message',
    ];

    /**
     * Get the supplier who made the vouch.
     */
    public function vouchedBy(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'vouched_by_id');
    }

    /**
     * Get the supplier who was vouched for.
     */
    public function vouchedFor(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'vouched_for_id');
    }
}
