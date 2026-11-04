<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date', 'meal_is_fixed' => 'boolean', 'bpjs_health' => 'boolean',
            'bpjs_jht' => 'boolean', 'bpjs_jkk' => 'boolean', 'bpjs_jkm' => 'boolean',
            'bpjs_jp' => 'boolean', 'active' => 'boolean',
        ];
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }
}
