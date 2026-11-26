<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorFeeDay extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['confirmed' => 'boolean', 'payment_checked' => 'boolean', 'work_date' => 'date:Y-m-d']; }
    public function payroll() { return $this->belongsTo(DoctorPayroll::class, 'doctor_payroll_id'); }
    public function payments() { return $this->hasMany(DoctorFeePayment::class, 'doctor_fee_day_id'); }
    public function activePayments() { return $this->payments()->whereNull('voided_at'); }
    public function getPaidAmountAttribute(): int { return (int) $this->activePayments->sum('amount'); }
    public function getRemainingAttribute(): ?int {
        return $this->confirmed && $this->payment_checked && $this->fee_amount !== null
            ? max(0, (int)$this->fee_amount - $this->paid_amount) : null;
    }
    public function getReconciliationStatusAttribute(): string {
        if (!$this->confirmed || !$this->payment_checked || $this->fee_amount === null) return 'pending';
        if ($this->remaining === 0) return 'paid';
        return $this->paid_amount > 0 ? 'partial' : 'unpaid';
    }
}
