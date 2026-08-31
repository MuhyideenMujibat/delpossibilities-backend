<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOrder extends Model
{
    protected $fillable = [
        'user_id',
        'subtotal',
        'delivery_fee',
        'referral_credit_applied',
        'total_amount',
        'hostel_address',
        'location_type',
        'status',
        'paystack_reference',
        'payment_authorization_url',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'referral_credit_applied' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ProductOrderItem::class);
    }

    // The gas order this (unpaid) cart was bundled into, if any (non-subscriber
    // path — one Paystack charge covers both).
    public function order()
    {
        return $this->hasOne(Order::class);
    }

    // The gas order this cart is attached to for delivery after having been
    // paid for standalone (see Order::attachedProductOrder). Distinct from
    // order() so an already-paid cart riding along on a refill still counts
    // as "linked" and can't be attached twice.
    public function attachingOrder()
    {
        return $this->hasOne(Order::class, 'attached_product_order_id');
    }

    // The subscriber refill this cart is riding along with, if any
    // (subscriber path — refill is free, this cart is still paid for on
    // its own, but the two are fulfilled/tracked together).
    public function refill()
    {
        return $this->hasOne(Refill::class);
    }

    // True once this cart has been bundled into either a gas order or a
    // subscriber refill — status then follows whichever it's attached to
    // (see OrderController::updateStatus / RefillController::update)
    // rather than being editable on its own.
    public function isLinked(): bool
    {
        return $this->order()->exists()
            || $this->attachingOrder()->exists()
            || $this->refill()->exists();
    }

    // eazy_market items pay a delivery fee tiered purely by their own
    // subtotal; gas_services items are always free delivery — this is the
    // one place that rule is encoded. Static, mirroring Subscriber's own
    // style (calendarFor()/calendarReady()) of putting cross-cutting
    // lookup logic directly on the model rather than a separate service
    // class.
    public static function eazyMarketFeeForSubtotal(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0;
        }

        $tier = EazyMarketDeliveryTier::where('is_active', true)
            ->where('min_amount', '<=', $subtotal)
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $subtotal))
            ->orderByDesc('min_amount')
            ->first();

        return $tier ? (float) $tier->fee : 0;
    }

    // Eazy Market items may never be the entire contents of a *standalone*
    // paid cart — they need a Gas Services (accessories) item alongside them
    // to be worth a dedicated delivery trip. Only relevant for standalone
    // checkout (see ProductOrderController::pay); bundling into a gas
    // refill/order is exempt, since the refill is inherently the companion.
    public function isEazyMarketOnly(): bool
    {
        $totals = static::subtotalsByGroup($this->items);

        return $totals['eazy_market'] > 0 && $totals['gas_services'] <= 0;
    }

    // Splits a set of {group, line_total} rows into gas_services vs
    // eazy_market subtotals. Accepts plain arrays (used by
    // ProductOrderController::store before any row is persisted) as well
    // as ProductOrderItem models, so the same helper serves both.
    public static function subtotalsByGroup(iterable $items): array
    {
        $totals = ['gas_services' => 0.0, 'eazy_market' => 0.0];

        foreach ($items as $item) {
            $group = is_array($item) ? $item['group'] : $item->group;
            $lineTotal = is_array($item) ? $item['line_total'] : $item->line_total;
            $totals[$group] = ($totals[$group] ?? 0) + (float) $lineTotal;
        }

        return $totals;
    }
}
