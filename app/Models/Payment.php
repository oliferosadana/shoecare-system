<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'amount_paid',
        'requested_amount',
        'method',
        'provider',
        'status',
        'reference_number',
        'provider_transaction_id',
        'provider_order_id',
        'qr_string',
        'qr_url',
        'proof_photo_path',
        'paid_at',
        'expires_at',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'integer',
            'requested_amount' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
