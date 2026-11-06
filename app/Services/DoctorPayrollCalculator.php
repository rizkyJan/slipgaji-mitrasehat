<?php
namespace App\Services;
use App\Models\DoctorAssignment;
use App\Models\DoctorProcedure;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
class DoctorPayrollCalculator {
 private function nonnegative($value):int { return max(0,(int)($value??0)); }
 private function hours(array $row, float $default):float {
  if (empty($row['start_time']) || empty($row['end_time'])) return $default;
  $start=explode(':',$row['start_time']); $end=explode(':',$row['end_time']);
  $a=((int)$start[0]*60+(int)$start[1]);$b=((int)$end[0]*60+(int)$end[1]);
  if ($b<=$a)$b+=1440; return ($b-$a)/60;
 }
 /** Formula fee tindakan; sengaja dipisah agar bisa diuji tanpa database. */
 public function procedureFee(string $mode,int $qty,int $fixed,float $pct,int $price,int $material,int $claim,int $patient):int {
  $qty=max(0,$qty);$pct=max(0,min(100,$pct));
  return match($mode){
   'percentage'=>(int)round(max(0,$price-$material)*$pct/100)*$qty,
   'prosthesis'=>(int)round((max(0,$claim)+max(0,$patient-$material))*$pct/100)*$qty,
   default=>max(0,$fixed)*$qty,
  };
 }
 public function calculate(DoctorAssignment $assignment,string $period,array $data):array {
  $rules=$assignment->rules ?: [];
  $rates=$rules['shift_rates']??[];
  $mode=$rules['patient_mode']??'all';
  if(!in_array($mode,['excess','all','none'],true)) throw ValidationException::withMessages(['profile'=>'Mode pasien dokter tidak valid.']);
  $income=[]; $shiftDetails=[]; $actionDetails=[]; $cashDetails=[];
  $base=$this->nonnegative($data['base_salary']??$assignment->base_salary);
  $sip=$this->nonnegative($data['sip_fee']??$assignment->sip_fee);
  $pj=$this->nonnegative($data['pj_fee']??$assignment->pj_fee);
  foreach (['Gaji Pokok'=>$base,'Fee SIP'=>$sip] as $k=>$v) if($v>0)$income[$k]=$v;
  $deductions=[]; $absence=$this->nonnegative($data['absence_days']??0);
  if($absence>0 && $assignment->absence_rate>0)$deductions['Tidak Hadir ('.$absence.' hari)']=$absence*(int)$assignment->absence_rate;
  if($pj>0){if($assignment->pj_mode==='deduction')$deductions['Potongan PJ']=$pj;else $income['Fee PJ']=$pj;}
  $totTransport=0;$bpjsFee=0;$umumFee=0;$usgFee=0;$homecareFee=0;
  $inputMode=$data['input_mode']??'daily';
  $recapDetails=null;
  if($inputMode==='recap'){
   // Mode rekap bulanan: tidak membuat tanggal kunjungan/jadwal fiktif.
   $recapDetails=['transport'=>[],'counts'=>[]];$poliShifts=0;
   foreach(($data['recap_transport']??[]) as $ix=>$entry){
    $qty=$this->nonnegative($entry['qty']??0);
    if($qty===0)continue;
    $kind=(string)$entry['shift_type'];
    if(!array_key_exists($kind,config('doctor_payroll.shift_labels')))throw ValidationException::withMessages(["recap_transport.$ix.shift_type"=>'Jenis jadwal tidak dikenal.']);
    $fullRate=$this->nonnegative($rates[$kind]??0);
    $standard=max(0.25,(float)($rules['shift_hours'][$kind]??$rules['full_hours']??5));
    $startTime=$entry['start_time']??null;$endTime=$entry['end_time']??null;
    if((empty($startTime)) !== (empty($endTime))) throw ValidationException::withMessages(["recap_transport.$ix.end_time"=>'Isi jam mulai dan selesai sekaligus, atau kosongkan keduanya.']);
    $worked=$this->hours($entry,(isset($entry['hours_per_visit'])&&$entry['hours_per_visit']!=='')?(float)$entry['hours_per_visit']:$standard);
    if($worked>24)throw ValidationException::withMessages(["recap_transport.$ix.end_time"=>'Durasi kerja maksimal 24 jam.']);
    $hourly=$this->nonnegative($rules['hourly_override']??0);
    $perVisit=$worked+0.00001 >= $standard?$fullRate:(int)round(min($fullRate,($hourly>0?$hourly:$fullRate/$standard)*$worked));
    $subtotal=$perVisit*$qty;
    $totTransport+=$subtotal;
    if($kind==='poli2')$poliShifts+=$qty;
    $recapDetails['transport'][]=['type'=>$kind,'count'=>$qty,'full_rate'=>$fullRate,'standard_hours'=>$standard,'hours_per_visit'=>$worked,'start_time'=>$startTime,'end_time'=>$endTime,'per_visit'=>$perVisit,'subtotal'=>$subtotal,'mode'=>$worked+0.00001 >= $standard?'full':($hourly>0?'hourly':'prorata')];
   }
   $raw=$data['recap_counts']??[];
   $counts=[];
   foreach(['bpjs_regular','umum_regular','poli2_bpjs_paid','poli2_umum_paid','usg','homecare_clinic','homecare_own'] as $key){$counts[$key]=$this->nonnegative($raw[$key]??0);}
   if(($counts['poli2_bpjs_paid']+$counts['poli2_umum_paid'])>0 && $poliShifts===0){
    throw ValidationException::withMessages(['recap_counts.poli2_bpjs_paid'=>'Isi jumlah kedatangan Poli 2 sebelum menambahkan pasien berfee sesudah kuota.']);
   }
   $recapDetails['counts']=$counts;
   $recapDetails['poli2_shifts']=$poliShifts;
   $recapDetails['poli2_quota']=$this->nonnegative($rules['poli2_included']??20);
   $recapDetails['patient_note']='Jumlah pasien berfee Poli 2 diambil dari rekap setelah kuota per kedatangan, bukan hasil pengurangan 20 dari seluruh pasien sebulan.';
   if($mode!=='none'){
    $bpjsFee=($counts['bpjs_regular']+$counts['poli2_bpjs_paid'])*$this->nonnegative($rules['bpjs_fee']??0);
    $umumFee=($counts['umum_regular']+$counts['poli2_umum_paid'])*$this->nonnegative($rules['general_fee']??0);
   }
   $usgFee=$counts['usg']*$this->nonnegative($rules['usg_fee']??0);
   $homecareFee=$counts['homecare_clinic']*$this->nonnegative($rules['homecare_clinic']??0)+$counts['homecare_own']*$this->nonnegative($rules['homecare_own']??0);
  }
  foreach(($data['shifts']??[]) as $ix=>$r){
   $type=$r['shift_type'];$date=$r['work_date'];
   if((empty($r['start_time'])) !== (empty($r['end_time'])))throw ValidationException::withMessages(["shifts.$ix.end_time"=>'Isi jam masuk dan selesai sekaligus, atau kosongkan keduanya untuk shift penuh.']);
   if(substr($date,0,7)!==$period)throw ValidationException::withMessages(["shifts.$ix.work_date"=>'Tanggal jadwal harus berada dalam periode gaji.']);
   $rate=$this->nonnegative($rates[$type]??0);
   // Durasi penuh berbeda tiap jenis shift; override pada baris jadwal bersifat opsional.
   $profileFull=(float)($rules['shift_hours'][$type]??$rules['full_hours']??5);
   $standard=max(0.25,(float)((isset($r['standard_hours']) && $r['standard_hours']!=='')?$r['standard_hours']:$profileFull));
   $worked=$this->hours($r,$standard);
   if($worked>24)throw ValidationException::withMessages(["shifts.$ix.end_time"=>'Jam praktik tidak boleh lebih dari 24 jam.']);
   $hourly=$this->nonnegative($rules['hourly_override']??0);
   $hasManual=isset($r['transport_override']) && $r['transport_override']!=='' && $r['transport_override']!==null;
   $modeTransport=$hasManual?'manual':($worked+0.00001 >= $standard?'full':($hourly>0?'hourly':'prorata'));
   $transport=$hasManual?$this->nonnegative($r['transport_override']):
    ($modeTransport==='full'?$rate:(int)round(min($rate,($hourly>0?$hourly:$rate/$standard)*$worked)));
   $bpjs=$this->nonnegative($r['bpjs_count']??0); $umum=$this->nonnegative($r['general_count']??0);
   // R4.4: hanya Poli 2 yang memiliki kuota pasien termasuk transport.
   // Untuk shift lain, semua pasien BPJS/umum dibayar dari pasien pertama.
   // Mode 'none' dipertahankan untuk profil yang memang tidak memiliki fee pasien (mis. hanya USG).
   // Nilai mode lama 'excess' tetap diterima, tetapi tidak lagi menerapkan kuota pada shift biasa.
   $poli2=($type==='poli2');
   $quota=$poli2?$this->nonnegative($rules['poli2_included']??20):0;
   $appliedMode=$mode==='none'?'none':($poli2?'excess':'all');
   $overBpjs=0;$overUmum=0;
   if($appliedMode==='all'){$overBpjs=$bpjs;$overUmum=$umum;}
   elseif($appliedMode==='excess'){
    $excess=max(0,$bpjs+$umum-$quota);
    if($excess>0){
     if($bpjs===0){$overUmum=$excess;}
     elseif($umum===0){$overBpjs=$excess;}
     else{
      $overBpjs=$this->nonnegative($r['bpjs_excess']??0);
      $overUmum=$this->nonnegative($r['general_excess']??0);
      if($overBpjs+$overUmum!==$excess || $overBpjs>$bpjs || $overUmum>$umum)
       throw ValidationException::withMessages(["shifts.$ix.bpjs_excess"=>"Poli 2: ada {$excess} pasien setelah kuota {$quota}. Isi pembagian BPJS dan umum yang benar-benar dilayani mulai pasien ke-".($quota+1).'.']);
     }
    }
   }
   $bfee=$overBpjs*$this->nonnegative($rules['bpjs_fee']??0);
   $ufee=$overUmum*$this->nonnegative($rules['general_fee']??0);
   $usg=$this->nonnegative($r['usg_count']??0)*$this->nonnegative($rules['usg_fee']??0);
   $homeClinic=$this->nonnegative($r['homecare_clinic']??0);$homeOwn=$this->nonnegative($r['homecare_own']??0);
   $home=$homeClinic*$this->nonnegative($rules['homecare_clinic']??0)+$homeOwn*$this->nonnegative($rules['homecare_own']??0);
   $totTransport+=$transport;$bpjsFee+=$bfee;$umumFee+=$ufee;$usgFee+=$usg;$homecareFee+=$home;
   $shiftDetails[]=['date'=>$date,'type'=>$type,'hours'=>round($worked,3),'standard_hours'=>$standard,'start_time'=>$r['start_time']??null,'end_time'=>$r['end_time']??null,'full_rate'=>$rate,'transport_mode'=>$modeTransport,'hourly_rate'=>round($hourly>0?$hourly:$rate/$standard,2),'transport'=>$transport,'bpjs'=>$bpjs,'general'=>$umum,'bpjs_paid'=>$overBpjs,'general_paid'=>$overUmum,'patient_mode_applied'=>$appliedMode,'patient_quota'=>$quota,'patients_included'=>($appliedMode==='excess'?min($bpjs+$umum,$quota):0),'usg'=>$this->nonnegative($r['usg_count']??0),'homecare_clinic'=>$homeClinic,'homecare_own'=>$homeOwn,'patient_fee'=>$bfee+$ufee,'usg_fee'=>$usg,'homecare_fee'=>$home,'total'=>$transport+$bfee+$ufee+$usg+$home];
  }
  foreach(['Uang Transport'=>$totTransport,'Uang Pasien BPJS'=>$bpjsFee,'Uang Pasien Umum'=>$umumFee,'Uang Pasien USG'=>$usgFee,'Fee Homecare'=>$homecareFee] as $k=>$v)if($v>0)$income[$k]=$v;
  $actionTotal=0;
  foreach(($data['actions']??[]) as $ix=>$a){
   $actionDate=trim((string)($a['work_date']??''));
   if($actionDate!=='' && substr($actionDate,0,7)!==$period)throw ValidationException::withMessages(["actions.$ix.work_date"=>'Tanggal tindakan tidak sesuai periode.']);
   if($inputMode==='daily' && $actionDate==='')throw ValidationException::withMessages(["actions.$ix.work_date"=>'Tanggal tindakan wajib dalam mode harian.']);
   $proc=DoctorProcedure::query()->whereKey($a['procedure_id'])->where('active',true)->firstOrFail();
   if($proc->branch && strcasecmp($proc->branch,$assignment->branch)!==0)throw ValidationException::withMessages(["actions.$ix.procedure_id"=>'Tindakan tidak tersedia untuk cabang ini.']);
   $qty=$this->nonnegative($a['qty']??1);
   $fixed=$a['fixed_fee']!==null && $a['fixed_fee']!==''?$this->nonnegative($a['fixed_fee']):$this->nonnegative($proc->fixed_fee);
   $pct=(float)($a['percentage']??($proc->mode==='fixed'?$proc->percentage:($rules['procedure_pct']??$proc->percentage)));
   $pct=max(0,min(100,$pct));
   $price=$this->nonnegative($a['price']??0);$material=$this->nonnegative($a['material']??0);
   $claim=$this->nonnegative($a['claim']??0);$patient=$this->nonnegative($a['patient']??0);
   $fee=$this->procedureFee($proc->mode,$qty,$fixed,$pct,$price,$material,$claim,$patient);
   $actionTotal+=$fee;
   $actionDetails[]=['date'=>$actionDate!==''?$actionDate:null,'label'=>$proc->label,'mode'=>$proc->mode,'qty'=>$qty,'unit_fee'=>$fixed,'percentage'=>$pct,'charge'=>$price,'material'=>$material,'claim'=>$claim,'patient_receipt'=>$patient,'fee'=>$fee];
  }
  if($actionTotal>0)$income['Fee Tindakan']=$actionTotal;
  $extraDetails=[];
  foreach(($data['extras']??[]) as $x){$label=trim($x['label']);$amount=$this->nonnegative($x['amount']);$type=$x['type'];
   $extraDetails[]=['label'=>$label,'amount'=>$amount,'type'=>$type];
   if($amount<=0)continue;
   if($type==='income')$income[$label]=($income[$label]??0)+$amount;
   else $deductions[$label]=($deductions[$label]??0)+$amount;
  }
  $gross=array_sum($income);$cuts=array_sum($deductions);$net=$gross-$cuts;$cash=0;
  foreach(($data['cash']??[]) as $ix=>$row){
   if(substr($row['date'],0,7)!==$period)throw ValidationException::withMessages(["cash.$ix.date"=>'Tanggal pengambilan cash harus dalam periode ini.']);
   $amount=$this->nonnegative($row['amount']);$cash+=$amount;
   $cashDetails[]=['date'=>$row['date'],'amount'=>$amount,'note'=>trim((string)($row['note']??''))];
  }
  $warning=[];
  if($cash>$net)$warning[]='Uang cash lebih besar daripada total hak dokter. Periksa sebelum menyetujui.';
  if($net<0)$warning[]='Potongan lebih besar daripada penerimaan dokter.';
  return ['doctor'=>['name'=>$assignment->employee?->name,'nip'=>$assignment->employee?->employee_code,'email'=>$assignment->employee?->email,'branch'=>$assignment->branch,'profile'=>$assignment->profile_label],
   'period'=>$period,'rules'=>$rules,'input_mode'=>$inputMode,'recap'=>$recapDetails,'income'=>$income,'deductions'=>$deductions,'shifts'=>$shiftDetails,'actions'=>$actionDetails,'extras'=>$extraDetails,'cash'=>$cashDetails,
   'gross_total'=>$gross,'deduction_total'=>$cuts,'net_total'=>$net,'cash_total'=>$cash,'transfer_due'=>$net-$cash,'warnings'=>$warning,'input'=>$data];
 }
}
