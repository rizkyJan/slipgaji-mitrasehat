<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'input_snapshot' => 'array', 'calculation_snapshot' => 'array',
            'approved_at' => 'datetime', 'paid_at' => 'date',
            'verification_token' => 'encrypted', 'verified_issued_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class)->withTrashed(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function deliveries(): HasMany { return $this->hasMany(PayslipDelivery::class); }
}
