<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array { return ['details' => 'array', 'created_at' => 'datetime']; }
    public static function record(string $action, ?Model $entity = null, array $details = []): void
    {
        static::query()->create([
            'user_id' => auth()->id(), 'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(), 'details' => $details,
        ]);
    }
}
