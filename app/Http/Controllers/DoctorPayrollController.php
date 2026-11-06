<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;
use App\Models\DoctorAssignment;
use App\Models\DoctorPayroll;
use App\Models\DoctorProcedure;
use App\Models\DoctorDelivery;
use App\Services\DoctorPayrollCalculator;
use App\Services\DoctorPayslipPdf;
use App\Services\DoctorSlipVerification;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
class DoctorPayrollController extends Controller {
 public function index(Request $request){
  $period=(string)$request->query('period',now()->format('Y-m'));
  if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$period))$period=now()->format('Y-m');
  $branch=trim((string)$request->query('branch',''));$status=(string)$request->query('status','');
  if(!in_array($status,['','draft','approved'],true))$status='';
  $branches=DoctorAssignment::distinct()->orderBy('branch')->pluck('branch');
  $query=DoctorPayroll::where('period',$period)->when($branch!=='',fn($q)=>$q->whereHas('assignment',fn($a)=>$a->where('branch',$branch)))->when($status!=='',fn($q)=>$q->where('status',$status));
  $stats=(clone $query)->selectRaw('COUNT(*) total_slips, COALESCE(SUM(CASE WHEN status=? THEN 1 ELSE 0 END),0) draft_count,COALESCE(SUM(CASE WHEN status=? THEN 1 ELSE 0 END),0) approved_count, COALESCE(SUM(net_total),0) net_sum, COALESCE(SUM(cash_total),0) cash_sum, COALESCE(SUM(transfer_due),0) transfer_sum',['draft','approved'])->first();
  $payrolls=$query->with('assignment.employee')->latest()->paginate(20)->withQueryString();
  return view('doctors.index',compact('period','branch','status','branches','stats','payrolls'));
 }
 private function mergeOld(array $base):array { $old=old(); foreach(['base_salary','sip_fee','pj_fee','absence_days','input_mode','recap_transport','recap_counts','shifts','actions','extras','cash','notes'] as $key) if(array_key_exists($key,$old))$base[$key]=$old[$key]; return $base; }
 private function baseInput(DoctorAssignment $a):array {return ['base_salary'=>$a->base_salary,'sip_fee'=>$a->sip_fee,'pj_fee'=>$a->pj_fee,'absence_days'=>0,'input_mode'=>'recap','recap_transport'=>[],'recap_counts'=>[],'shifts'=>[],'actions'=>[],'extras'=>[],'cash'=>[],'notes'=>''];}
 public function create(Request $request){
  $assignments=DoctorAssignment::with('employee')->where('active',true)->orderBy('branch')->get();
  $assignment=$request->query('assignment')?DoctorAssignment::with('employee')->where('active',true)->findOrFail($request->query('assignment')):null;
  $period=(string)$request->query('period',now()->format('Y-m'));
  if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$period))$period=now()->format('Y-m');
  $input=$assignment?$this->mergeOld($this->baseInput($assignment)):[];
  if($assignment && ($already=DoctorPayroll::where('doctor_assignment_id',$assignment->id)->where('period',$period)->first())) return redirect()->route('doctors.show',$already);
  $procedures=DoctorProcedure::where('active',true)->where(fn($q)=>$q->whereNull('branch')->orWhere('branch',$assignment?->branch??''))->orderBy('label')->get();
  return view('doctors.form',compact('assignments','assignment','period','input','procedures'));
 }
 public function edit(DoctorPayroll $doctorPayroll){
  abort_unless($doctorPayroll->status==='draft',403,'Slip sudah disetujui dan terkunci');
  $assignment=$doctorPayroll->assignment()->with('employee')->firstOrFail();$assignments=DoctorAssignment::with('employee')->where('active',true)->get();$period=$doctorPayroll->period;
  $input=$this->mergeOld($doctorPayroll->input_snapshot);
  $procedures=DoctorProcedure::where('active',true)->where(fn($q)=>$q->whereNull('branch')->orWhere('branch',$assignment->branch))->orderBy('label')->get();
  return view('doctors.form',compact('assignments','assignment','period','input','procedures','doctorPayroll'));
 }
 private function fields(Request $request):array {
  $data=$request->validate([
   'doctor_assignment_id'=>['required','integer','exists:doctor_assignments,id'],'period'=>['required','date_format:Y-m'],
   'input_mode'=>['nullable',Rule::in(['recap','daily'])],
   'recap_transport'=>['nullable','array','max:80'],
   'recap_transport.*.shift_type'=>['required',Rule::in(array_keys(config('doctor_payroll.shift_labels')))],
   'recap_transport.*.qty'=>['required','integer','between:0,100'],
   'recap_transport.*.hours_per_visit'=>['nullable','numeric','between:0.25,24'],
   'recap_transport.*.start_time'=>['nullable','date_format:H:i'],
   'recap_transport.*.end_time'=>['nullable','date_format:H:i'],
   'recap_counts'=>['nullable','array'],
   'recap_counts.bpjs_regular'=>['nullable','integer','between:0,100000'],
   'recap_counts.umum_regular'=>['nullable','integer','between:0,100000'],
   'recap_counts.poli2_bpjs_paid'=>['nullable','integer','between:0,100000'],
   'recap_counts.poli2_umum_paid'=>['nullable','integer','between:0,100000'],
   'recap_counts.usg'=>['nullable','integer','between:0,100000'],
   'recap_counts.homecare_clinic'=>['nullable','integer','between:0,100000'],
   'recap_counts.homecare_own'=>['nullable','integer','between:0,100000'],
   'base_salary'=>['required','integer','min:0','max:9999999999'],'sip_fee'=>['required','integer','min:0','max:9999999999'],'pj_fee'=>['required','integer','min:0','max:9999999999'],'absence_days'=>['required','integer','between:0,31'],
   'shifts'=>['nullable','array','max:150'], 'shifts.*.work_date'=>['required','date_format:Y-m-d'],
   'shifts.*.shift_type'=>['required',Rule::in(array_keys(config('doctor_payroll.shift_labels')))],
   'shifts.*.start_time'=>['nullable','date_format:H:i'],'shifts.*.end_time'=>['nullable','date_format:H:i'],
   'shifts.*.standard_hours'=>['nullable','numeric','between:0.25,24'],
   'shifts.*.transport_override'=>['nullable','integer','min:0','max:999999999'],
   'shifts.*.bpjs_count'=>['required','integer','between:0,1000'],'shifts.*.general_count'=>['required','integer','between:0,1000'],
   'shifts.*.bpjs_excess'=>['nullable','integer','between:0,1000'],'shifts.*.general_excess'=>['nullable','integer','between:0,1000'],
   'shifts.*.usg_count'=>['required','integer','between:0,1000'],
   'shifts.*.homecare_clinic'=>['required','integer','between:0,1000'],'shifts.*.homecare_own'=>['required','integer','between:0,1000'],
   'actions'=>['nullable','array','max:150'],'actions.*.work_date'=>['nullable','date_format:Y-m-d'],'actions.*.procedure_id'=>['required','integer','exists:doctor_procedures,id'],
   'actions.*.qty'=>['required','integer','between:1,1000'],'actions.*.fixed_fee'=>['nullable','integer','min:0','max:999999999'],
   'actions.*.percentage'=>['nullable','numeric','between:0,100'],'actions.*.price'=>['required','integer','min:0','max:999999999'],
   'actions.*.material'=>['required','integer','min:0','max:999999999'],'actions.*.claim'=>['required','integer','min:0','max:999999999'],
   'actions.*.patient'=>['required','integer','min:0','max:999999999'],
   'extras'=>['nullable','array','max:100'],'extras.*.label'=>['required','string','max:120'],'extras.*.type'=>['required',Rule::in(['income','deduction'])],
   'extras.*.amount'=>['required','integer','min:0','max:9999999999'],
   'cash'=>['nullable','array','max:150'],'cash.*.date'=>['required','date_format:Y-m-d'],'cash.*.amount'=>['required','integer','min:1','max:9999999999'],'cash.*.note'=>['nullable','string','max:150'],
   'notes'=>['nullable','string','max:2000'],
  ]);
  // Tidak boleh mencampur mode rekap dan detail: hindari penghitungan ganda.
  $mode=$data['input_mode']??'daily';
  if($mode==='recap' && !empty($data['shifts'])){
   throw \Illuminate\Validation\ValidationException::withMessages(['input_mode'=>'Rekap bulanan tidak boleh dicampur dengan jadwal harian.']);
  }
  if($mode==='daily' && (!empty($data['recap_transport']) || !empty($data['recap_counts']))){
   throw \Illuminate\Validation\ValidationException::withMessages(['input_mode'=>'Input harian tidak boleh dicampur dengan rekap bulanan.']);
  }
  if($mode==='daily'){
   foreach(($data['actions']??[]) as $i=>$action){
    if(empty($action['work_date'])) throw \Illuminate\Validation\ValidationException::withMessages(["actions.$i.work_date"=>'Tanggal tindakan wajib untuk mode detail harian.']);
   }
  }
  return $data;
 }
 public function preview(Request $request,DoctorPayrollCalculator $calc){$d=$this->fields($request);$a=DoctorAssignment::with('employee')->findOrFail($d['doctor_assignment_id']);return response()->json($calc->calculate($a,$d['period'],$d));}
 public function store(Request $request,DoctorPayrollCalculator $calc){
  $d=$this->fields($request);$a=DoctorAssignment::with('employee')->where('active',true)->findOrFail($d['doctor_assignment_id']);
  $c=$calc->calculate($a,$d['period'],$d);
  if(DoctorPayroll::where('doctor_assignment_id',$a->id)->where('period',$d['period'])->exists())return back()->withErrors(['period'=>'Slip dokter untuk unit dan periode ini sudah ada.']);
  $p=DoctorPayroll::create(['doctor_assignment_id'=>$a->id,'period'=>$d['period'],'status'=>'draft','input_snapshot'=>$c['input'],'calculation_snapshot'=>$c,'gross_total'=>$c['gross_total'],'deduction_total'=>$c['deduction_total'],'net_total'=>$c['net_total'],'cash_total'=>$c['cash_total'],'transfer_due'=>$c['transfer_due'],'notes'=>$d['notes']??null,'created_by'=>auth()->id()]);
  AuditLog::record('doctor.payroll.created',$p,['period'=>$p->period]);return redirect()->route('doctors.show',$p)->with('success','Draft slip dokter disimpan. Periksa rincian sebelum persetujuan.');
 }
 public function update(Request $request,DoctorPayroll $doctorPayroll,DoctorPayrollCalculator $calc){
  abort_unless($doctorPayroll->status==='draft',403);$d=$this->fields($request);
  abort_unless((int)$d['doctor_assignment_id']===(int)$doctorPayroll->doctor_assignment_id && $d['period']===$doctorPayroll->period,422);
  $c=$calc->calculate($doctorPayroll->assignment()->with('employee')->firstOrFail(),$d['period'],$d);
  $doctorPayroll->update(['input_snapshot'=>$c['input'],'calculation_snapshot'=>$c,'gross_total'=>$c['gross_total'],'deduction_total'=>$c['deduction_total'],'net_total'=>$c['net_total'],'cash_total'=>$c['cash_total'],'transfer_due'=>$c['transfer_due'],'notes'=>$d['notes']??null]);
  AuditLog::record('doctor.payroll.updated',$doctorPayroll);return redirect()->route('doctors.show',$doctorPayroll)->with('success','Draft berhasil diperbarui.');
 }
 public function show(DoctorPayroll $doctorPayroll,DoctorSlipVerification $v){
  if($doctorPayroll->status==='approved')$doctorPayroll=$v->issue($doctorPayroll);
  $doctorPayroll->load(['assignment.employee','approver','deliveries']);
  return view('doctors.show',['p'=>$doctorPayroll]);
 }
 public function approve(DoctorPayroll $doctorPayroll,DoctorSlipVerification $v){
  abort_unless($doctorPayroll->status==='draft',403);
  abort_if($doctorPayroll->net_total<0 || $doctorPayroll->transfer_due<0,422,'Total kas atau potongan melampaui hak dokter.');
  DB::transaction(function()use($doctorPayroll,$v){$p=DoctorPayroll::whereKey($doctorPayroll->id)->lockForUpdate()->firstOrFail();
   abort_unless($p->status==='draft',409);$p->update(['status'=>'approved','approved_at'=>now(),'approved_by'=>auth()->id()]);$v->issue($p);
  });
  AuditLog::record('doctor.payroll.approved',$doctorPayroll);return redirect()->route('doctors.show',$doctorPayroll)->with('success','Slip dokter disetujui dan terkunci.');
 }
 public function destroy(DoctorPayroll $doctorPayroll){abort_unless($doctorPayroll->status==='draft',403);AuditLog::record('doctor.payroll.deleted',$doctorPayroll);$doctorPayroll->delete();return redirect()->route('doctors.index')->with('success','Draft dihapus.');}
 public function pdf(DoctorPayroll $doctorPayroll,DoctorPayslipPdf $pdf){$bin=$pdf->render($doctorPayroll->load('assignment.employee'));
  return response($bin,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="slip-dokter-'.str_replace('/','-',(string)$doctorPayroll->slip_number).'-'.$doctorPayroll->id.'.pdf"','Cache-Control'=>'private, no-store']);}
 public function send(DoctorPayroll $doctorPayroll,DoctorPayslipPdf $pdf,GmailService $gmail,DoctorSlipVerification $v){
  abort_unless($doctorPayroll->status==='approved',403);$v->assertIntact($doctorPayroll);
  if(DoctorDelivery::where('doctor_payroll_id',$doctorPayroll->id)->where('status','sent')->exists())return back()->with('error','Slip ini sudah berhasil dikirim. Pengiriman ganda dicegah.');
  $connection=auth()->user()->googleConnection;if(!$connection)return back()->with('error','Hubungkan akun Google di Pengaturan terlebih dahulu.');
  $recipient=$doctorPayroll->assignment->employee?->email;
  if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))return back()->with('error','Email dokter belum valid.');
  try{$bin=$pdf->render($doctorPayroll);$mid=$gmail->sendPdfDocument($connection,$recipient,'Slip Honor Dokter '.$doctorPayroll->period.' - '.$doctorPayroll->assignment->employee->name,
    'Yth. Dokter,<br>Terlampir slip honor periode '.e($doctorPayroll->period).' dari PT. Mitra Sehat Maju Sentosa.<br>Dokumen bersifat rahasia.',
    'slip-dokter-'.$doctorPayroll->period.'.pdf',$bin);
   DoctorDelivery::create(['doctor_payroll_id'=>$doctorPayroll->id,'user_id'=>auth()->id(),'recipient_email'=>$recipient,'status'=>'sent','gmail_message_id'=>$mid,'sent_at'=>now()]);AuditLog::record('doctor.payroll.sent',$doctorPayroll,['recipient'=>$recipient]);
   return back()->with('success','Slip dokter berhasil dikirim ke '.$recipient);
  }catch(\Throwable $e){report($e);DoctorDelivery::create(['doctor_payroll_id'=>$doctorPayroll->id,'user_id'=>auth()->id(),'recipient_email'=>$recipient,'status'=>'failed','error'=>Str::limit($e->getMessage(),200)]);return back()->with('error','Pengiriman gagal. Periksa koneksi Gmail dan log server.');}
 }
}
