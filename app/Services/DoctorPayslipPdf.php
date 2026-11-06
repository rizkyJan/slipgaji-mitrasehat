<?php
namespace App\Services;
use App\Models\AppSetting;
use App\Models\DoctorPayroll;
use Carbon\Carbon;
class DoctorPayslipPdf {
 public function render(DoctorPayroll $p, ?array $settings=null):string {
  $v=app(DoctorSlipVerification::class);
  if($p->status==='approved'){$p=$v->issue($p);$v->assertIntact($p);}
  $s=$p->calculation_snapshot;$d=$s['doctor'];$pdf=new PdfCanvas();
  // Kebijakan tampilan disimpan di rules dalam snapshot, bukan diambil dari profil terbaru.
  // Slip lama tanpa flags memakai default aman: komponen opsional nol disembunyikan.
  $rules=$s['rules']??[];
  $display=(array)($rules['slip_display']??[]);
  $optionalZero=(bool)($display['optional_zero']??false);
  $patientZero=(bool)($display['patient_zero']??true);
  $shiftZero=(bool)($display['shift_zero']??true);
  $shiftRows=$s['shifts']??[];$actionRows=$s['actions']??[];
  $recapMode=($s['input_mode']??'daily')==='recap' && is_array($s['recap']??null);
  $recap=$recapMode?$s['recap']:[];
  $recapCounts=$recap['counts']??[];
  $recapTransport=$recap['transport']??[];
  $bpjsVisits=0;$umumVisits=0;$usgVisits=0;$homeVisits=0;
  foreach($shiftRows as $shift){
   $bpjsVisits+=(int)($shift['bpjs']??0);
   $umumVisits+=(int)($shift['general']??0);
   $usgVisits+=(int)($shift['usg']??0);
   $homeVisits+=(int)($shift['homecare_clinic']??0)+(int)($shift['homecare_own']??0);
  }
  if($recapMode){
   $bpjsVisits=(int)($recapCounts['bpjs_regular']??0)+(int)($recapCounts['poli2_bpjs_paid']??0);
   $umumVisits=(int)($recapCounts['umum_regular']??0)+(int)($recapCounts['poli2_umum_paid']??0);
   $usgVisits=(int)($recapCounts['usg']??0);
   $homeVisits=(int)($recapCounts['homecare_clinic']??0)+(int)($recapCounts['homecare_own']??0);
  }
  $hasActivities=(bool)$shiftRows || $recapMode;
  $bpjsRelevant=$hasActivities && ((int)($rules['bpjs_fee']??0)>0 || $bpjsVisits>0);
  $umumRelevant=$hasActivities && ((int)($rules['general_fee']??0)>0 || $umumVisits>0);
  $usgRelevant=$hasActivities && ((int)($rules['usg_fee']??0)>0 || $usgVisits>0);
  $homeRelevant=$hasActivities && ((int)($rules['homecare_clinic']??0)>0 || (int)($rules['homecare_own']??0)>0 || $homeVisits>0);

  $clinic=$settings['clinic_name']??AppSetting::setting('clinic_name','PT. MITRA SEHAT MAJU SENTOSA');
  $address=$settings['clinic_address']??AppSetting::setting('clinic_address','Sukoharjo, Jawa Tengah');
  $pdf->jpeg(public_path('images/logo-pdf.jpg'),48,768,65,65);
  $pdf->rgb(.08,.23,.52);$pdf->text(124,803,strtoupper($clinic),12,true);$pdf->text(124,787,$address,8.5);
  $pdf->rgb(.11,.28,.66);$pdf->text(548,803,'SLIP HONOR DOKTER',14,true,'right');
  $pdf->line(48,763,548,763,1.2);$pdf->rgb(.20,.25,.35);
  $period=Carbon::createFromFormat('!Y-m',$p->period)->locale('id')->translatedFormat('F Y');
  $pdf->text(48,741,'Periode',9);$pdf->text(139,741,': '.$period,9,true);
  $pdf->text(315,741,'Nama Dokter',9);$pdf->text(407,741,': '.($d['name']??'-'),9,true);
  $pdf->text(48,724,'NIP',9);$pdf->text(139,724,': '.($d['nip']??'-'),9);
  $pdf->text(315,724,'Unit / Cabang',9);$pdf->text(407,724,': '.($d['branch']??'-'),9);
  $pdf->text(48,707,'Jenis Profil',9);$pdf->text(139,707,': '.($d['profile']??'-'),9);
  $pdf->text(48,690,'Nomor Slip',9);$pdf->text(139,690,': '.($p->slip_number??'DRAFT - BELUM SAH'),9,true);
  $pdf->rgb(.17,.20,.18);$y=654;
  $title=function(string $label)use($pdf,&$y){
   if($y<265){$pdf->newPage();$y=785;}
   $pdf->rgb(.89,.94,1);$pdf->rectangle(48,$y-6,500,23,true);$pdf->rgb(.08,.27,.65);
   $pdf->text(55,$y+2,$label,10,true);$pdf->rgb(.2,.22,.27);$y-=29;
  };
  $line=function(string $label,int $amount,bool $total=false)use($pdf,&$y){
   if($y<260){$pdf->newPage();$y=782;}
   if($total){$pdf->rgb(.45,.5,.6);$pdf->line(308,$y+8,548,$y+8,.5);$pdf->rgb(.08,.23,.52);}
   $pdf->text(55,$y,$label,$total?10:9,$total);
   $pdf->text(548,$y,number_format($amount,0,',','.'),$total?10:9,$total,'right');
   $y-=$total?26:18; $pdf->rgb(.17,.20,.18);
  };
  $summaryIncome=$s['income']??[];
  if($optionalZero){
   foreach(['Gaji Pokok','Fee SIP','Fee PJ'] as $key){
    if(!array_key_exists($key,$summaryIncome))$summaryIncome[$key]=0;
   }
   foreach(($s['extras']??[]) as $extra){
    if(($extra['type']??'')==='income' && (int)($extra['amount']??0)===0){
     $key=(string)($extra['label']??'Tambahan');
     if(!array_key_exists($key,$summaryIncome))$summaryIncome[$key]=0;
    }
   }
  }
  if($shiftZero && ($shiftRows || $recapTransport) && !array_key_exists('Uang Transport',$summaryIncome))$summaryIncome['Uang Transport']=0;
  if($patientZero && $bpjsRelevant && !array_key_exists('Uang Pasien BPJS',$summaryIncome))$summaryIncome['Uang Pasien BPJS']=0;
  if($patientZero && $umumRelevant && !array_key_exists('Uang Pasien Umum',$summaryIncome))$summaryIncome['Uang Pasien Umum']=0;
  // Aktivitas yang benar-benar tercatat tetap terlihat meski tidak menghasilkan fee.
  if($usgVisits>0 && !array_key_exists('Uang Pasien USG',$summaryIncome))$summaryIncome['Uang Pasien USG']=0;
  if($homeVisits>0 && !array_key_exists('Fee Homecare',$summaryIncome))$summaryIncome['Fee Homecare']=0;
  if($actionRows && !array_key_exists('Fee Tindakan',$summaryIncome))$summaryIncome['Fee Tindakan']=0;
  $order=['Gaji Pokok','Fee SIP','Fee PJ','Uang Transport','Uang Pasien BPJS','Uang Pasien Umum','Uang Pasien USG','Fee Homecare','Fee Tindakan'];
  $orderedIncome=[];
  foreach($order as $name){if(array_key_exists($name,$summaryIncome)){$orderedIncome[$name]=$summaryIncome[$name];unset($summaryIncome[$name]);}}
  $orderedIncome+=$summaryIncome;
  $title('PENERIMAAN');
  foreach($orderedIncome as $name=>$amt)$line((string)$name,(int)$amt);
  $line('TOTAL PENERIMAAN',(int)$s['gross_total'],true);
  $summaryDeductions=$s['deductions']??[];
  if($optionalZero){
   foreach(($s['extras']??[]) as $extra){
    if(($extra['type']??'')==='deduction' && (int)($extra['amount']??0)===0){
     $key=(string)($extra['label']??'Potongan');
     if(!array_key_exists($key,$summaryDeductions))$summaryDeductions[$key]=0;
    }
   }
  }
  if($summaryDeductions || $optionalZero){
   $title('PENGURANGAN');
   foreach($summaryDeductions as $name=>$amt)$line((string)$name,(int)$amt);
   $line('TOTAL PENGURANGAN',(int)$s['deduction_total'],true);
  }
  if($y<355){$pdf->newPage();$y=775;}
  $pdf->rgb(.08,.27,.65);$pdf->rectangle(48,$y-15,500,38,true);$pdf->rgb(1,1,1);
  $pdf->text(57,$y,'TOTAL HAK DOKTER',11,true);
  $pdf->text(535,$y,'Rp '.number_format($s['net_total'],0,',','.'),12,true,'right');$y-=52;
  $pdf->rgb(.17,.20,.18);
  $line('SUDAH DITERIMA CASH',(int)$s['cash_total']);
  $line('SISA BELUM DIBAYAR / RENCANA TRANSFER',(int)$s['transfer_due'],true);
  // Detail cash pada halaman pertama (maksimal 3 entri) agar terlihat saat PDF diterima via email.
  $cashRows=$s['cash']??[]; $shownCash=min(3,count($cashRows));
  if($shownCash>0){
   if($y<265){$pdf->newPage();$y=785;}
   $pdf->rgb(.08,.27,.65);$pdf->text(55,$y,'RINCIAN CASH SUDAH DITERIMA',9,true);$y-=20;
   for($i=0;$i<$shownCash;$i++){
    $c=$cashRows[$i];
    $label=($c['date']??'-').' - '.substr((string)($c['note']??'Cash'),0,30);
    $line($label,(int)$c['amount']);
   }
   if(count($cashRows)>$shownCash){
    $pdf->rgb(.4,.44,.5);$pdf->text(55,$y,'+'.(count($cashRows)-$shownCash).' catatan cash lainnya ada di lampiran',8);$y-=17;
   }
  } else {
   $pdf->rgb(.4,.44,.5);$pdf->text(55,$y,'Belum ada pembayaran cash yang dicatat.',8);$y-=18;
  }
  $pdf->rgb(.17,.20,.18);
  foreach(explode("\n",wordwrap(Terbilang::rupiah((int)$s['net_total']),92,"\n",false)) as $phrase){
   $pdf->text(48,$y,'Terbilang: '.$phrase,8);$y-=13;
  }
  $y-=13;$pdf->rgb(.45,.47,.49);$pdf->line(48,$y,548,$y,.7);
  $date=($p->approved_at??now())->locale('id')->translatedFormat('d F Y');
  $pdf->text(548,$y-18,'Sukoharjo, '.$date,9,false,'right');
  if($p->status==='approved'){
   $qrY=$y-125;
   if($qrY<48){$pdf->newPage();$qrY=636;$pdf->text(548,785,'VERIFIKASI SLIP DOKTER',12,true,'right');}
   $pdf->qr($v->url($p),48,$qrY,86);
   $pdf->rgb(.08,.27,.65);$pdf->text(145,$qrY+72,'VERIFIKASI KEASLIAN',9.5,true);
   $pdf->rgb(.35,.4,.48);$pdf->text(145,$qrY+54,'Pindai untuk cocokkan total resmi,',8);
   $pdf->text(145,$qrY+40,'pembayaran cash, dan transfer.',8);
  }else{$pdf->text(48,$y-52,'DRAFT - BELUM DISETUJUI',10,true);}
  $pdf->rgb(.48,.50,.54);$pdf->text(48,38,'Dokumen rahasia. Diterbitkan sistem Mitra Sehat Payroll.',7.5);
  // Lampiran rinci: menggunakan snapshot gaji, tanpa menghitung ulang aturan yang mungkin berubah.
  $money=static fn($v):string=>'Rp '.number_format((int)round((float)($v??0)),0,',','.');
  // Shift, tindakan, dan rules telah dibaca dari snapshot di atas.
  $cashRows=$s['cash']??[];
  $pdf->newPage();$y=792;
  $drawPageHeader=function()use($pdf,$p,$d,$period,&$y){
   $pdf->rgb(.07,.24,.55);$pdf->text(48,791,'LAMPIRAN RINCIAN HONOR DOKTER',12.5,true);
   $pdf->rgb(.35,.41,.5);$pdf->text(48,772,($d['name']??'-').' | '.$period,9);
   $pdf->text(548,772,$p->slip_number??'DRAFT - BELUM SAH',8,false,'right');
   $pdf->rgb(.7,.76,.85);$pdf->line(48,761,548,761,.65);$y=741;
  };
  $drawPageHeader();
  $checkSpace=function(int $need=20)use($pdf,&$y,$drawPageHeader){
   if($y-$need<58){$pdf->newPage();$drawPageHeader();}
  };
  $sectionCount=0;
  $heading=function(string $title)use($pdf,&$y,$checkSpace,&$sectionCount){
   $sectionCount++;
   $checkSpace(48);$pdf->rgb(.89,.94,1);$pdf->rectangle(48,$y-7,500,22,true);
   $pdf->rgb(.07,.25,.61);$pdf->text(55,$y+1,chr(64+$sectionCount).'. '.$title,9.4,true);$y-=31;
  };
  $row=function(string $left,string $right='',bool $strong=false)use($pdf,&$y,$checkSpace){
   $checkSpace(19);$pdf->rgb($strong?.08:.23,$strong?.27:.26,$strong?.64:.31);
   // Sisakan kolom kanan untuk jumlah uang, mencegah angka bertabrakan dengan uraian.
   $max=$right!==''?65:98;
   $left=trim($left);
   if(strlen($left)>$max)$left=substr($left,0,$max-3).'...';
   $pdf->text(55,$y,$left,$strong?8.7:8.1,$strong);
   if($right!=='')$pdf->text(541,$y,$right,$strong?8.7:8.1,$strong,'right');
   $y-=18;
  };
  $note=function(string $text)use($pdf,&$y,$checkSpace){
   foreach(explode("\n",wordwrap($text,97,"\n",true)) as $part){
    $checkSpace(16);$pdf->rgb(.37,.41,.49);$pdf->text(55,$y,$part,7.8);$y-=15;
   }
  };
  $date=static fn($raw):string=>(is_string($raw)&&strtotime($raw))?date('d/m/Y',strtotime($raw)):(string)($raw??'-');
  $num=static fn($value):string=>rtrim(rtrim(number_format((float)($value??0),2,',','.'),'0'),',');
  $subTotal=function(string $name,int $amount)use($pdf,&$y,$checkSpace,$row,$money){
   $checkSpace(30);$pdf->rgb(.72,.77,.86);$pdf->line(310,$y+7,541,$y+7,.45);
   $row($name,$money($amount),true);$y-=9;
  };

  // R4.5: rekap bulanan tidak punya tanggal per kedatangan.
  // Cetak jumlah x tarif dari snapshot, bukan mengarang tanggal praktik.
  if($recapMode){
   if($recapTransport){
    $heading('REKAP TRANSPORT / KEHADIRAN');
    foreach($recapTransport as $i=>$tr){
     $type=(string)($tr['type']??'-');
     $label=(string)(config('doctor_payroll.shift_labels')[$type]??$type);
     $count=(int)($tr['count']??0);$per=(int)($tr['per_visit']??0);
     $row(($i+1).'. '.$label.' | '.$count.' x '.$money($per),$money($tr['subtotal']??0),true);
     if(($tr['mode']??'full')!=='full'){
      $work=$num($tr['hours_per_visit']??0);$std=$num($tr['standard_hours']??0);
      $times=(!empty($tr['start_time']) && !empty($tr['end_time']))?(' | jam '.$tr['start_time'].'-'.$tr['end_time']):'';
      $note('    Tarif full '.$money($tr['full_rate']??0).' / '.$std.' jam, kerja '.$work.' jam'.$times.' = '.$money($per).' per kedatangan.');
     }
    }
    $subTotal('TOTAL TRANSPORT',(int)($s['income']['Uang Transport']??0));
   }
   $patientMode=$rules['patient_mode']??'all';
   $poliQuota=(int)($recap['poli2_quota']??20);
   foreach([
    ['RINCIAN FEE PASIEN BPJS','Uang Pasien BPJS','bpjs_regular','poli2_bpjs_paid','bpjs_fee'],
    ['RINCIAN FEE PASIEN UMUM','Uang Pasien Umum','umum_regular','poli2_umum_paid','general_fee'],
   ] as [$titleText,$incomeKey,$regularKey,$poliKey,$rateKey]){
    $rate=(int)($rules[$rateKey]??0);$regular=(int)($recapCounts[$regularKey]??0);$poli=(int)($recapCounts[$poliKey]??0);
    if(($patientZero && $rate>0)||$regular>0||$poli>0){
     $heading($titleText);
     if($patientMode==='none')$note('Profil ini tidak memberikan fee pasien. Jumlah tetap dicatat sebagai rekap.');
     $row('Selain Poli 2: '.$regular.' pasien berfee x '.$money($rate),$money($patientMode==='none'?0:$regular*$rate));
     $row('Poli 2: '.$poli.' pasien setelah kuota x '.$money($rate),$money($patientMode==='none'?0:$poli*$rate));
     $note('Kuota Poli 2 '.$poliQuota.' pasien PERTAMA PER KEDATANGAN (BPJS dan umum digabung). Angka setelah kuota berasal dari rekap admin.');
     $subTotal('TOTAL '.$incomeKey,(int)($s['income'][$incomeKey]??0));
    }
   }
   $usgCount=(int)($recapCounts['usg']??0);
   if($usgCount>0 || ($patientZero && $usgRelevant)){
    $heading('RINCIAN PASIEN USG');$rate=(int)($rules['usg_fee']??0);
    $row('USG '.$usgCount.' x '.$money($rate),$money($usgCount*$rate));
    $subTotal('TOTAL FEE USG',(int)($s['income']['Uang Pasien USG']??0));
   }
   $hcClinic=(int)($recapCounts['homecare_clinic']??0);$hcOwn=(int)($recapCounts['homecare_own']??0);
   if($hcClinic+$hcOwn>0 || ($patientZero && $homeRelevant)){
    $heading('RINCIAN HOMECARE');
    $cRate=(int)($rules['homecare_clinic']??0);$oRate=(int)($rules['homecare_own']??0);
    $row('Kendaraan klinik: '.$hcClinic.' x '.$money($cRate),$money($hcClinic*$cRate));
    $row('Kendaraan sendiri: '.$hcOwn.' x '.$money($oRate),$money($hcOwn*$oRate));
    $subTotal('TOTAL FEE HOMECARE',(int)($s['income']['Fee Homecare']??0));
   }
   $note('Rekap ini disusun dari jumlah per bulan tanpa rincian tanggal masing-masing kedatangan. Periksa dengan catatan pelayanan bila diperlukan.');
   $y-=12;
  }else{
  $transportRows=array_values(array_filter($shiftRows,static fn($r)=>$shiftZero || (int)($r['transport']??0)!==0));
  if($transportRows){
   $heading('RINCIAN UANG TRANSPORT / KEHADIRAN');
  }
  foreach($transportRows as $i=>$shift){
   $checkSpace(65);
   $worked=$shift['hours']??0;$full=$shift['standard_hours']??0;
   $rate=(int)($shift['full_rate']??0);$actual=(int)($shift['transport']??0);
   $time=(!empty($shift['start_time'])&&!empty($shift['end_time']))?
    ($shift['start_time'].'-'.$shift['end_time']):'Full shift';
   $row(($i+1).'. '.$date($shift['date']??null).' | '.($shift['type']??'-').' | '.$time,$money($actual),true);
   $mode=$shift['transport_mode']??'unknown';
   if($mode==='manual')$note('    Tarif penuh '.$money($rate).' | koreksi transport manual | nominal '.$money($actual));
   elseif($mode==='hourly')$note('    '.$num($worked).' jam x tarif khusus '.$money($shift['hourly_rate']??0).'/jam = '.$money($actual));
   elseif($mode==='full')$note('    '.$num($worked).' jam (standar '.$num($full).' jam) | tarif shift penuh '.$money($rate));
   elseif($mode==='prorata')$note('    '.$money($rate).' / '.$num($full).' jam x '.$num($worked).' jam = '.$money($actual));
   else $note('    Durasi '.$num($worked).' jam | standar '.$num($full).' jam | tarif penuh '.$money($rate));
  }
  if($transportRows)$subTotal('TOTAL UANG TRANSPORT',(int)($s['income']['Uang Transport']??0));

  $mode=$rules['patient_mode']??'excess';
  // Snapshot R4.4 mencatat mode per jadwal. Slip lama tetap menggunakan keterangan
  // aturan lama agar PDF tidak salah menjelaskan hitungan yang sudah disetujui.
  $newRule=false;
  foreach($shiftRows as $shift){if(array_key_exists('patient_mode_applied',$shift)){$newRule=true;break;}}
  if($newRule){
   $included=(int)($rules['poli2_included']??20);
   $modeText=$mode==='none'?'Profil ini tidak menerima fee pasien BPJS/umum':'Selain Poli 2: semua pasien mendapat fee sejak pasien pertama. Poli 2: '.$included.' pasien pertama termasuk transport; hanya pasien setelah kuota mendapat fee';
  }else{
   $legacyIncluded=(int)($rules['included']??20);
   $modeText=match($mode){'all'=>'Semua pasien memperoleh fee','none'=>'Tidak ada fee per pasien',default=>'Aturan slip lama: fee pasien setelah kuota '.$legacyIncluded.' pasien per shift'};
  }
  $bpjsRate=(int)($rules['bpjs_fee']??0);$bpjsSeen=0;$bpjsPaid=0;
  if($bpjsRelevant && ($patientZero || $bpjsVisits>0 || (int)($s['income']['Uang Pasien BPJS']??0)>0)){
   $heading('RINCIAN PASIEN BPJS');
   $note('Ketentuan: '.$modeText.'. Dilayani berbeda dengan pasien berfee.');
   foreach($shiftRows as $shift){
    $seen=(int)($shift['bpjs']??0);$paid=(int)($shift['bpjs_paid']??0);
    $bpjsSeen+=$seen;$bpjsPaid+=$paid;
    if(!$patientZero && !$seen && !$paid)continue;
    $row($date($shift['date']??null).' | '.($shift['type']==='poli2'?'Poli 2':'Shift '.$shift['type']).' | dilayani '.$seen.' | berfee '.$paid.' x '.$money($bpjsRate),$money($paid*$bpjsRate));
   }
   $note('Total BPJS dilayani: '.$bpjsSeen.' | memperoleh fee: '.$bpjsPaid.'.');
   $subTotal('TOTAL FEE PASIEN BPJS',(int)($s['income']['Uang Pasien BPJS']??0));
  }

  $umumRate=(int)($rules['general_fee']??0);$umumSeen=0;$umumPaid=0;
  if($umumRelevant && ($patientZero || $umumVisits>0 || (int)($s['income']['Uang Pasien Umum']??0)>0)){
   $heading('RINCIAN PASIEN UMUM');$note('Ketentuan: '.$modeText.'.');
   foreach($shiftRows as $shift){
    $seen=(int)($shift['general']??0);$paid=(int)($shift['general_paid']??0);
    $umumSeen+=$seen;$umumPaid+=$paid;
    if(!$patientZero && !$seen && !$paid)continue;
    $row($date($shift['date']??null).' | '.($shift['type']==='poli2'?'Poli 2':'Shift '.$shift['type']).' | dilayani '.$seen.' | berfee '.$paid.' x '.$money($umumRate),$money($paid*$umumRate));
   }
   $note('Total umum dilayani: '.$umumSeen.' | memperoleh fee: '.$umumPaid.'.');
   $subTotal('TOTAL FEE PASIEN UMUM',(int)($s['income']['Uang Pasien Umum']??0));
  }

  $usgRate=(int)($rules['usg_fee']??0);$usgN=0;
  if($usgVisits>0 || ($patientZero && $usgRelevant)){
   $heading('RINCIAN PASIEN USG');
   foreach($shiftRows as $shift){
    $n=(int)($shift['usg']??0);$usgN+=$n;
    if(!$n)continue;
    $row($date($shift['date']??null).' | '.$n.' USG x '.$money($usgRate),$money($shift['usg_fee']??($n*$usgRate)));
   }
   if(!$usgN)$note('Tidak ada pasien USG tercatat pada periode ini.');
   $subTotal('TOTAL FEE USG',(int)($s['income']['Uang Pasien USG']??0));
  }

  if($homeVisits>0 || ($patientZero && $homeRelevant)){
   $heading('RINCIAN HOMECARE');
  $hcRate=(int)($rules['homecare_clinic']??0);$ownRate=(int)($rules['homecare_own']??0);$hcAny=false;
  foreach($shiftRows as $shift){
   $n=(int)($shift['homecare_clinic']??0);$own=(int)($shift['homecare_own']??0);
   if($n>0){$hcAny=true;$row($date($shift['date']??null).' | Kendaraan klinik: '.$n.' x '.$money($hcRate),$money($n*$hcRate));}
   if($own>0){$hcAny=true;$row($date($shift['date']??null).' | Kendaraan sendiri: '.$own.' x '.$money($ownRate),$money($own*$ownRate));}
  }
  if(!$hcAny)$note('Tidak ada homecare berfee pada periode ini.');
  $subTotal('TOTAL FEE HOMECARE',(int)($s['income']['Fee Homecare']??0));
  }

  } // akhir bagian lampiran harian; tindakan dan cash berlaku di semua mode.
  if($actionRows){
   $heading('RINCIAN TINDAKAN MEDIS DAN GIGI');
  }
  foreach($actionRows as $i=>$action){
   $checkSpace(60);
   $qty=(int)($action['qty']??0);$fee=(int)($action['fee']??0);
   $row(($i+1).'. '.($action['date']??null?$date($action['date']):'Rekap bulan').' | '.($action['label']??'Tindakan').' | '.$qty.'x',$money($fee),true);
   $type=$action['mode']??'fixed';$pct=$num($action['percentage']??0);
   if($type==='percentage')$note('    (Biaya '.$money($action['charge']??0).' - BHMP '.$money($action['material']??0).') x '.$pct.'% x '.$qty.' tindakan');
   elseif($type==='prosthesis')$note('    (Klaim BPJS '.$money($action['claim']??0).' + (Pasien '.$money($action['patient_receipt']??0).' - lab/BHMP '.$money($action['material']??0).')) x '.$pct.'% x '.$qty);
   else $note('    '.$qty.' tindakan x tarif dokter '.$money($action['unit_fee']??0));
  }
  if($actionRows)$subTotal('TOTAL FEE TINDAKAN',(int)($s['income']['Fee Tindakan']??0));

  $extraIncomes=array_values(array_filter(($s['extras']??[]),static fn($e)=>($e['type']??'')==='income' && ($optionalZero || (int)($e['amount']??0)>0)));
  $fixedIncomes=[];
  foreach(['Gaji Pokok','Fee SIP','Fee PJ'] as $key){
   if($optionalZero || (int)($s['income'][$key]??0)>0)$fixedIncomes[$key]=(int)($s['income'][$key]??0);
  }
  if($fixedIncomes || $extraIncomes){
   $heading('GAJI POKOK, SIP, PJ DAN KOMPONEN LAIN');
   foreach($fixedIncomes as $key=>$amount)$row($key,$money($amount));
   foreach($extraIncomes as $extra)$row('Tambahan: '.($extra['label']??'-'),$money($extra['amount']??0));
  }
  $extraCuts=array_values(array_filter(($s['extras']??[]),static fn($e)=>($e['type']??'')==='deduction' && (int)($e['amount']??0)===0));
  if(($s['deductions']??[]) || ($optionalZero && $extraCuts)){
   $heading('RINCIAN PENGURANGAN');
   foreach(($s['deductions']??[]) as $label=>$amt){
   $row((string)$label,$money($amt));
   if(str_starts_with((string)$label,'Tidak Hadir (')){
    $days=(int)($s['input']['absence_days']??0);
    $perDay=$days>0?(int)round((int)$amt/$days):0;
    $note('    '.$days.' hari x tarif '.$money($perDay).'/hari = '.$money($amt));
   }
  }
   if($optionalZero){
    foreach($extraCuts as $extra)$row('Potongan: '.($extra['label']??'-'),$money(0));
   }
   $subTotal('TOTAL PENGURANGAN',(int)($s['deduction_total']??0));
  }

  $heading('RIWAYAT CASH YANG SUDAH DITERIMA');
  if(!$cashRows)$note('Belum ada pengambilan cash yang dicatat.');
  $running=0;
  foreach($cashRows as $i=>$cash){
   $amount=(int)($cash['amount']??0);$running+=$amount;
   $row(($i+1).'. '.$date($cash['date']??null).' | '.($cash['note']??'Cash'),$money($amount));
   $note('    Akumulasi cash '.$money($running).' | sisa hak '.$money((int)($s['net_total']??0)-$running));
  }
  $subTotal('TOTAL SUDAH DITERIMA CASH',(int)($s['cash_total']??0));

  $heading('REKONSILIASI PEMBAYARAN');
  $row('Total penghasilan honor',$money($s['gross_total']??0));
  $row('Total potongan',$money($s['deduction_total']??0));
  $row('Total hak dokter setelah potongan',$money($s['net_total']??0),true);
  $row('Dikurangi cash yang sudah diterima',$money($s['cash_total']??0));
  $subTotal('SISA BELUM DIBAYAR / RENCANA TRANSFER',(int)($s['transfer_due']??0));
  $note('Catatan: Sisa rencana transfer bukan bukti bahwa uang sudah ditransfer. Konfirmasi pembayaran harus berasal dari bukti transaksi.');
  $note('Angka di lampiran berasal dari snapshot penggajian saat slip disimpan/disetujui. Perubahan tarif masa depan tidak mengubah isi slip ini.');
  $note('Dokumen internal. Jangan lampirkan identitas atau rekam medis pasien pada slip honor.');
  return $pdf->output();
 }
}
