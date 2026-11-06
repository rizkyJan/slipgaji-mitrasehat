<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DoctorPayroll extends Model {
 protected $guarded=['id'];
 protected function casts():array { return ['input_snapshot'=>'array','calculation_snapshot'=>'array','verification_token'=>'encrypted','approved_at'=>'datetime','issued_at'=>'datetime']; }
 public function assignment(){return $this->belongsTo(DoctorAssignment::class,'doctor_assignment_id');}
 public function approver(){return $this->belongsTo(User::class,'approved_by');}
 public function deliveries(){return $this->hasMany(DoctorDelivery::class);}
}
