<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DoctorAssignment extends Model {
 protected $guarded=['id'];
 protected function casts():array { return ['rules'=>'array','active'=>'boolean']; }
 public function employee(){ return $this->belongsTo(Employee::class)->withTrashed(); }
 public function payrolls(){ return $this->hasMany(DoctorPayroll::class); }
}
