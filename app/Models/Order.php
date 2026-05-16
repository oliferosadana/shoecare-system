<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'received_by',
        'status',
        'received_at',
        'estimated_finished_at',
        'finished_at',
        'picked_up_at',
        'payment_status',
        'payment_method',
        'pickup_delivery_type',
        'pickup_delivery_fee',
        'subtotal',
        'discount_amount',
        'total_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'estimated_finished_at' => 'datetime',
            'finished_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'subtotal' => 'integer',
            'pickup_delivery_fee' => 'integer',
            'discount_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(OrderTimeline::class);
    }

    public function usesDelivery(): bool
    {
        return in_array($this->pickup_delivery_type, ['delivery', 'pickup_delivery'], true);
    }

    public function displayStatusLabel(): string
    {
        return match ($this->status) {
            'proses' => 'Proses',
            'selesai' => 'Selesai',
            'diambil' => 'Diambil',
            'menunggu_diambil' => $this->usesDelivery() ? 'Siap Diantar' : 'Menunggu Diambil',
            'dibatalkan' => 'Dibatalkan',
            default => 'Diterima',
        };
    }

    public function readyOrderLabel(): string
    {
        return $this->usesDelivery() ? 'Order Siap Diantar' : 'Order Siap Diambil';
    }

    public function readyOrderDescription(): string
    {
        return $this->usesDelivery()
            ? 'Order selesai dan siap diantar ke pelanggan.'
            : 'Order selesai dan siap diambil pelanggan.';
    }

    public function readyWhatsappDescription(): string
    {
        return $this->usesDelivery()
            ? 'Sepatu Kakak sudah selesai dan siap kami antarkan.'
            : 'Sepatu Kakak sudah selesai dan siap diambil di outlet.';
    }
}
