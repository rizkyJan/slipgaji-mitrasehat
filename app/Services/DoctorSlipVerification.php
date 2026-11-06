<?php
namespace App\Services;
use App\Models\DoctorPayroll;
use Illuminate\Support\Facades\DB;
use RuntimeException;
class DoctorSlipVerification {
 private function key():string {
  $key=(string)config('app.key'); if(str_starts_with($key,'base64:'))$key=base64_decode(substr($key,7),true)?:'';
  if(strlen($key)<32)throw new RuntimeException('APP_KEY belum valid');return $key;
 }
 private function signature(DoctorPayroll $p):string {
  $payload=['v'=>1,'id'=>(int)$p->id,'assignment_id'=>(int)$p->doctor_assignment_id,'period'=>$p->period,'number'=>$p->slip_number,'approved_at'=>$p->approved_at?->toIso8601String(),'input'=>$p->input_snapshot,'calculation'=>$p->calculation_snapshot,'gross'=>(int)$p->gross_total,'deductions'=>(int)$p->deduction_total,'net'=>(int)$p->net_total,'cash'=>(int)$p->cash_total,'transfer'=>(int)$p->transfer_due];
  return hash_hmac('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$this->key());
 }
 public function issue(DoctorPayroll $p):DoctorPayroll {
  if($p->status!=='approved')throw new RuntimeException('Slip dokter belum disetujui.');
  return DB::transaction(function()use($p){
   $p=DoctorPayroll::whereKey($p->id)->lockForUpdate()->firstOrFail();
   if($p->verification_signature){$this->assertIntact($p);return $p;}
   $p->slip_number='MSMS/DR/'.str_replace('-','/',$p->period).'/'.str_pad((string)$p->id,7,'0',STR_PAD_LEFT);
   $p->verification_token=bin2hex(random_bytes(24));$p->verification_signature=$this->signature($p);$p->issued_at=now();$p->save();
   return $p;
  });
 }
 public function validToken(DoctorPayroll $p,string $token):bool{return strlen($token)===48&&ctype_xdigit($token)&&is_string($p->verification_token)&&hash_equals($p->verification_token,strtolower($token));}
 public function assertIntact(DoctorPayroll $p):void { if($p->status!=='approved'||!is_string($p->verification_signature)||!hash_equals($p->verification_signature,$this->signature($p)))throw new RuntimeException('Integritas slip dokter tidak valid.'); }
 public function url(DoctorPayroll $p):string {return route('doctors.verify',['doctorPayroll'=>$p->id,'token'=>$p->verification_token]);}
}
