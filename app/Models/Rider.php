<?php

namespace App\Models;

use Database\Factories\RiderProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rider extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return RiderProfileFactory::new();
    }

    protected $fillable = [
        'user_id', 'vehicle_type', 'plate_number', 'license_number', 'license_photo',
        'status', 'current_latitude', 'current_longitude', 'rating', 'level',
        'total_deliveries', 'acceptance_rate', 'cancellation_rate', 'is_verified',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'rating' => 'decimal:1',
        'acceptance_rate' => 'decimal:2',
        'cancellation_rate' => 'decimal:2',
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
    ];

    // ---- Relationships ----

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function locationLogs()
    {
        return $this->hasMany(RiderLocationLog::class);
    }

    // ---- Helpers ----

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }
}
