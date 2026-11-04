<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AppSetting extends Model
{
    protected $table = 'app_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    public static function setting(string $key, string $default = ''): string
    {
        return (string) (static::query()->where('key', $key)->value('value') ?? $default);
    }
}
