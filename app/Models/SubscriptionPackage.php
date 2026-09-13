<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'monthly_amount',
        'yearly_amount',
        'is_active',
        'sort_order',
        'features',
        'is_offer_active',
        'offer_badge',
        'offer_monthly_amount',
        'offer_yearly_amount',
        'offer_max_claims',
        'offer_claimed_count',
        'offer_expires_at',
    ];

    protected $casts = [
        'monthly_amount' => 'decimal:2',
        'yearly_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'features' => 'array',
        'is_offer_active' => 'boolean',
        'offer_monthly_amount' => 'decimal:2',
        'offer_yearly_amount' => 'decimal:2',
        'offer_max_claims' => 'integer',
        'offer_claimed_count' => 'integer',
        'offer_expires_at' => 'date',
    ];

    /**
     * Check if the promotional launch offer is currently valid and within quota
     */
    public function isOfferValid(): bool
    {
        if (!$this->is_offer_active) {
            return false;
        }

        if ($this->offer_expires_at && Carbon::now()->startOfDay()->gt($this->offer_expires_at->endOfDay())) {
            return false;
        }

        if ($this->offer_max_claims !== null && $this->offer_claimed_count >= $this->offer_max_claims) {
            return false;
        }

        return $this->offer_monthly_amount !== null || $this->offer_yearly_amount !== null;
    }

    /**
     * Get the effective monthly price (offer price if active, else standard price)
     */
    public function getEffectiveMonthlyAmount(): float
    {
        if ($this->isOfferValid() && $this->offer_monthly_amount !== null) {
            return (float) $this->offer_monthly_amount;
        }

        return (float) $this->monthly_amount;
    }

    /**
     * Get the effective yearly price (offer price if active, else standard price)
     */
    public function getEffectiveYearlyAmount(): float
    {
        if ($this->isOfferValid() && $this->offer_yearly_amount !== null) {
            return (float) $this->offer_yearly_amount;
        }

        return (float) $this->yearly_amount;
    }

    /**
     * Get remaining available claims for the offer
     */
    public function getRemainingOfferClaims(): ?int
    {
        if ($this->offer_max_claims === null) {
            return null;
        }

        return max(0, (int)$this->offer_max_claims - (int)$this->offer_claimed_count);
    }

    /**
     * Increment the claimed count after successful checkout
     */
    public function incrementOfferClaim(): void
    {
        if ($this->is_offer_active) {
            $this->increment('offer_claimed_count');
        }
    }
}
