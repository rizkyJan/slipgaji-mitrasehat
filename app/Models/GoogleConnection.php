<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GoogleConnection extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['access_token', 'refresh_token'];
    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'expires_at' => 'datetime'];
    }
}
