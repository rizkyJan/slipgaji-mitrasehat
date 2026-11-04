<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PayrollRule extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['effective_from' => 'date', 'tk_use_umk_floor' => 'boolean']; }
}
