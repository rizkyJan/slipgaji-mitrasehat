<?php
namespace App\Http\Controllers;
use App\Models\DoctorAssignment;
use App\Models\DoctorProcedure;
use App\Models\Employee;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class DoctorAssignmentController extends Controller {
 public function index(){
  $assignments=DoctorAssignment::with('employee')->orderBy('branch')->orderBy('employee_id')->get();
  $procedures=DoctorProcedure::orderBy('label')->get();
  return view('doctors.settings',compact('assignments','procedures'));
 }
 public function create(){return view('doctors.profile',['assignment'=>null,'employees'=>Employee::where('active',true)->orderBy('name')->get(),'presets'=>config('doctor_payroll.templates')]);}
 public function edit(DoctorAssignment $assignment){return view('doctors.profile',['assignment'=>$assignment,'employees'=>Employee::where('active',true)->orWhereKey($assignment->employee_id)->orderBy('name')->get(),'presets'=>config('doctor_payroll.templates')]);}
 private function fields(Request $request):array {
  return $request->validate([
   'employee_id'=>['required','exists:employees,id'],'branch'=>['required','string','max:100'],
   'profile_label'=>['required','string','max:100'],'pj_mode'=>['required',Rule::in(['income','deduction'])],'kind'=>['required',Rule::in(['general','dentist','usg','other'])],
   'base_salary'=>['required','integer','min:0','max:9999999999'],'absence_rate'=>['required','integer','min:0','max:9999999999'],'sip_fee'=>['required','integer','min:0','max:9999999999'],'pj_fee'=>['required','integer','min:0','max:9999999999'],
   'full_hours'=>['required','numeric','between:0.25,24'],'hourly_override'=>['required','integer','min:0','max:9999999'],
   'shift_hours'=>['required','array'],'shift_hours.*'=>['required','numeric','between:0.25,24'],
   'included'=>['required','integer','between:0,1000'],'poli2_included'=>['required','integer','between:0,1000'],'patient_mode'=>['required',Rule::in(['all','none'])],
   'bpjs_fee'=>['required','integer','min:0','max:99999999'],'general_fee'=>['required','integer','min:0','max:99999999'],
   'usg_fee'=>['required','integer','min:0','max:99999999'],'homecare_clinic'=>['required','integer','min:0','max:99999999'],
   'homecare_own'=>['required','integer','min:0','max:99999999'],'procedure_pct'=>['required','numeric','between:0,100'],
   'shift_rates'=>['required','array'],'shift_rates.*'=>['required','integer','min:0','max:99999999'],
   'slip_display'=>['required','array'],
   'slip_display.optional_zero'=>['required','boolean'],
   'slip_display.patient_zero'=>['required','boolean'],
   'slip_display.shift_zero'=>['required','boolean'],
   'active'=>['sometimes','boolean'],
  ]);
 }
 private function save(Request $request,?DoctorAssignment $assignment=null){
  $data=$this->fields($request);
  foreach(array_keys(config('doctor_payroll.shift_labels')) as $shiftKey){
   if(!array_key_exists($shiftKey,$data['shift_hours']??[]))return back()->withInput()->withErrors(['shift_hours'=>'Semua durasi shift penuh wajib diisi.']);
  }
  // Pengaturan tampilan ikut rules JSON agar masuk snapshot setiap slip dokter.
  $rules=array_intersect_key($data,array_flip(['shift_rates','shift_hours','full_hours','hourly_override','included','poli2_included','patient_mode','bpjs_fee','general_fee','usg_fee','homecare_clinic','homecare_own','procedure_pct','slip_display']));
  $fields=['employee_id'=>$data['employee_id'],'branch'=>trim($data['branch']),'profile_label'=>$data['profile_label'],'kind'=>$data['kind'],'base_salary'=>$data['base_salary'],'sip_fee'=>$data['sip_fee'],'pj_fee'=>$data['pj_fee'],'absence_rate'=>$data['absence_rate'],'pj_mode'=>$data['pj_mode'],'active'=>$request->boolean('active'),'rules'=>$rules];
  $duplicate=DoctorAssignment::where('employee_id',$fields['employee_id'])->where('branch',$fields['branch'])->when($assignment,fn($q)=>$q->where('id','<>',$assignment->id))->exists();
  if($duplicate)return back()->withInput()->withErrors(['branch'=>'Dokter ini sudah mempunyai profil di unit tersebut.']);
  if($assignment){$assignment->update($fields);}else{$assignment=DoctorAssignment::create($fields);}
  AuditLog::record('doctor.profile.saved',$assignment,['branch'=>$assignment->branch]);
  return redirect()->route('doctors.settings')->with('success','Profil dokter per unit berhasil disimpan. Slip lama tidak dihitung ulang.');
 }
 public function store(Request $request){return $this->save($request);}
 public function update(Request $request,DoctorAssignment $assignment){return $this->save($request,$assignment);}
 private function procedureFields(Request $request):array {
  return $request->validate(['label'=>['required','string','max:120'],'branch'=>['nullable','string','max:100'],'mode'=>['required',Rule::in(['fixed','percentage','prosthesis'])], 'fixed_fee'=>['required','integer','min:0','max:9999999999'],'percentage'=>['required','numeric','between:0,100']]);
 }
 public function procedureUpdate(Request $request,DoctorProcedure $procedure){
  $d=$this->procedureFields($request);
  $procedure->update(['label'=>$d['label'],'branch'=>$d['branch']?:null,'mode'=>$d['mode'],'fixed_fee'=>$d['fixed_fee'],'percentage'=>$d['percentage']]);
  AuditLog::record('doctor.procedure.updated',$procedure);
  return redirect()->route('doctors.settings')->with('success','Tarif tindakan diperbarui. Slip dokter yang sudah diterbitkan tetap memakai snapshot lama.');
 }
 public function procedureStatus(DoctorProcedure $procedure){
  $procedure->update(['active'=>!$procedure->active]);AuditLog::record('doctor.procedure.status',$procedure,['active'=>$procedure->active]);
  return redirect()->route('doctors.settings')->with('success','Status tindakan diperbarui.');
 }
 public function procedure(Request $request){
  $d=$this->procedureFields($request);
  DoctorProcedure::create(['label'=>$d['label'],'branch'=>$d['branch']?:null,'mode'=>$d['mode'],'fixed_fee'=>$d['fixed_fee'],'percentage'=>$d['percentage']]);
  return redirect()->route('doctors.settings')->with('success','Tindakan baru berhasil ditambahkan.');
 }
}
