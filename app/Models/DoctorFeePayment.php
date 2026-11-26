<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorFeePayment extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array {
        return ['payment_date' => 'date:Y-m-d', 'voided_at' => 'datetime'];
    }
    public function day() { return $this->belongsTo(DoctorFeeDay::class, 'doctor_fee_day_id'); }
}
