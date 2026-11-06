<?php
namespace App\Http\Controllers;
use App\Models\DoctorPayroll;
use App\Services\DoctorSlipVerification;
class DoctorVerifyController extends Controller {
 public function show(DoctorPayroll $doctorPayroll,string $token,DoctorSlipVerification $v){
  if(!$v->validToken($doctorPayroll,$token))abort(404);
  $valid=true;try{$v->assertIntact($doctorPayroll);}catch(\Throwable){$valid=false;}
  return response()->view('doctors.verify',['p'=>$doctorPayroll,'valid'=>$valid])->header('Cache-Control','no-store, private')->header('X-Robots-Tag','noindex, nofollow');
 }
}
