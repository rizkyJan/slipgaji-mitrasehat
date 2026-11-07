<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorImportBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rows' => 'encrypted:array', 'summary' => 'array'];
    }
}
