<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayslipDelivery extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
    public function payroll() { return $this->belongsTo(Payroll::class); }
}
