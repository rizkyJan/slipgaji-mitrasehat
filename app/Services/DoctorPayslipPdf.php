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
  // Lampiran ringkas R4.13: hanya tata letak, semua nominal dari snapshot.
  // Halaman utama di atas tidak diubah.
  $moneyShort=static fn($value):string=>number_format((int)round((float)($value??0)),0,',','.');
  $numShort=static fn($value):string=>rtrim(rtrim(number_format((float)($value??0),2,',','.'),'0'),',');
  $labels=config('doctor_payroll.shift_labels',[]);
  $groups=[];
  $add=function(string $section,string $label,int $count,string $rate,int $amount)use(&$groups){
   if(!isset($groups[$section]))$groups[$section]=[];
   $groups[$section][]=['label'=>$label,'count'=>$count,'rate'=>$rate,'amount'=>$amount];
  };
  // Transport: kelompokkan jenis shift/tarif agar dokter tidak perlu membaca per tanggal.
  $transportGrouped=[];
  if($recapMode){
   foreach($recapTransport as $tr){
    $qty=(int)($tr['count']??0);if($qty<=0)continue;
    $type=(string)($tr['type']??'-');
    $label=(string)($labels[$type]??$type);
    $per=(int)($tr['per_visit']??0);
    if(($tr['mode']??'full')!=='full')$label.=' ('.$numShort($tr['hours_per_visit']??0).' jam)';
    $key=$label.'|'.$per;
    if(!isset($transportGrouped[$key]))$transportGrouped[$key]=['label'=>$label,'count'=>0,'per'=>$per,'amount'=>0];
    $transportGrouped[$key]['count']+=$qty;
    $transportGrouped[$key]['amount']+=(int)($tr['subtotal']??0);
   }
  }else{
   foreach($shiftRows as $shift){
    $type=(string)($shift['type']??'-');
    $label=(string)($labels[$type]??$type);
    $std=(float)($shift['standard_hours']??0);$worked=(float)($shift['hours']??0);
    if($std>0 && $worked+0.00001<$std && ($shift['transport_mode']??'')!=='full'){
     $label.=' ('.$numShort($worked).' jam)';
    }
    if(($shift['transport_mode']??'')==='manual')$label.=' (koreksi)';
    $per=(int)($shift['transport']??0);
    $key=$label.'|'.$per;
    if(!isset($transportGrouped[$key]))$transportGrouped[$key]=['label'=>$label,'count'=>0,'per'=>$per,'amount'=>0];
    $transportGrouped[$key]['count']++;
    $transportGrouped[$key]['amount']+=$per;
   }
  }
  foreach($transportGrouped as $tr)$add('TRANSPORT / KEHADIRAN',$tr['label'],(int)$tr['count'],$moneyShort($tr['per']),(int)$tr['amount']);

  // BPJS dan umum tidak dicampur, Poli 2 dipisahkan berdasarkan pasien BERFEE.
  $counts=['bpjs_regular'=>0,'bpjs_poli2'=>0,'umum_regular'=>0,'umum_poli2'=>0,'usg'=>0,'homecare_clinic'=>0,'homecare_own'=>0];
  if($recapMode){
   $counts['bpjs_regular']=(int)($recapCounts['bpjs_regular']??0);
   $counts['bpjs_poli2']=(int)($recapCounts['poli2_bpjs_paid']??0);
   $counts['umum_regular']=(int)($recapCounts['umum_regular']??0);
   $counts['umum_poli2']=(int)($recapCounts['poli2_umum_paid']??0);
   foreach(['usg','homecare_clinic','homecare_own'] as $key)$counts[$key]=(int)($recapCounts[$key]??0);
  }else{
   foreach($shiftRows as $shift){
    $poli=($shift['type']??'')==='poli2';
    $counts[$poli?'bpjs_poli2':'bpjs_regular']+=(int)($shift['bpjs_paid']??0);
    $counts[$poli?'umum_poli2':'umum_regular']+=(int)($shift['general_paid']??0);
    foreach(['usg','homecare_clinic','homecare_own'] as $key)$counts[$key]+=(int)($shift[$key]??0);
   }
  }
  $noPatientFee=($rules['patient_mode']??'all')==='none';
  foreach([
   ['bpjs_regular','BPJS - reguler','bpjs_fee'],
   ['bpjs_poli2','BPJS - Poli 2','bpjs_fee'],
   ['umum_regular','Umum - reguler','general_fee'],
   ['umum_poli2','Umum - Poli 2','general_fee'],
   ['usg','USG','usg_fee'],
   ['homecare_clinic','Homecare - kendaraan klinik','homecare_clinic'],
   ['homecare_own','Homecare - kendaraan sendiri','homecare_own'],
  ] as [$key,$label,$rateKey]){
   $qty=(int)$counts[$key];if($qty<=0)continue;
   $rate=(int)($rules[$rateKey]??0);
   if($noPatientFee && in_array($key,['bpjs_regular','bpjs_poli2','umum_regular','umum_poli2'],true))$rate=0;
   $add('PASIEN / LAYANAN',$label,$qty,$moneyShort($rate),$qty*$rate);
  }

  // Tindakan: 1 baris untuk jenis/tarif yang sama, tanpa uraian rumus panjang.
  $actionGroups=[];
  foreach($actionRows as $action){
   $qty=(int)($action['qty']??0);if($qty<=0)continue;
   $label=(string)($action['label']??'Tindakan');
   $mode=(string)($action['mode']??'fixed');
   $rate=$mode==='fixed'?$moneyShort($action['unit_fee']??0):$numShort($action['percentage']??0).'%';
   $key=$label.'|'.$mode.'|'.$rate;
   if(!isset($actionGroups[$key]))$actionGroups[$key]=['label'=>$label,'count'=>0,'rate'=>$rate,'amount'=>0];
   $actionGroups[$key]['count']+=$qty;
   $actionGroups[$key]['amount']+=(int)($action['fee']??0);
  }
  foreach($actionGroups as $action)$add('TINDAKAN',$action['label'],(int)$action['count'],$action['rate'],(int)$action['amount']);

  foreach(['Gaji Pokok','Fee SIP','Fee PJ'] as $name){
   $amount=(int)($s['income'][$name]??0);
   if($amount>0 || ($optionalZero && array_key_exists($name,(array)($s['income']??[]))))$add('LAINNYA',$name,0,'',$amount);
  }
  foreach(($s['extras']??[]) as $extra){
   if(($extra['type']??'')!=='income')continue;
   $amount=(int)($extra['amount']??0);if(!$optionalZero && $amount===0)continue;
   $add('LAINNYA',(string)($extra['label']??'Tambahan'),0,'',$amount);
  }
  foreach(($s['deductions']??[]) as $label=>$amount)$add('POTONGAN',(string)$label,0,'',(int)$amount);

  $pdf->newPage();$y=792;
  $header=function()use($pdf,$d,$period,$p,&$y){
   $pdf->rgb(.07,.24,.55);$pdf->text(48,795,'LAMPIRAN REKAP HONOR',12.5,true);
   $pdf->rgb(.37,.42,.50);$pdf->text(48,776,($d['name']??'-').' | '.$period,9);
   $pdf->text(548,776,$p->slip_number??'DRAFT - BELUM SAH',8,false,'right');
   $pdf->rgb(.73,.78,.87);$pdf->line(48,763,548,763,.7);
   $pdf->rgb(.09,.26,.57);
   $pdf->text(55,744,'KOMPONEN',8.3,true);
   $pdf->text(343,744,'JUMLAH',8.3,true,'right');
   $pdf->text(436,744,'TARIF',8.3,true,'right');
   $pdf->text(541,744,'NOMINAL (Rp)',8.3,true,'right');
   $pdf->rgb(.75,.80,.89);$pdf->line(48,735,548,735,.5);
   $y=719;
  };
  $header();
  $ensure=function(int $needed)use($pdf,&$y,$header){
   if($y-$needed<65){$pdf->newPage();$header();}
  };
  $section=function(string $title)use($pdf,&$y,$ensure){
   $ensure(44);$y-=5;$pdf->rgb(.91,.95,1);$pdf->rectangle(48,$y-4,500,20,true);
   $pdf->rgb(.08,.27,.62);$pdf->text(55,$y+2,$title,8.5,true);$y-=29;
  };
  $lineRow=function(string $label,string $qty,string $rate,string $amount,bool $bold=false)use($pdf,&$y,$ensure){
   $ensure(19);
   $label=trim($label);
   $limit=44;
   if(function_exists('mb_strlen') && function_exists('mb_substr')){
    if(mb_strlen($label,'UTF-8')>$limit)$label=mb_substr($label,0,$limit-3,'UTF-8').'...';
   }elseif(strlen($label)>$limit){$label=substr($label,0,$limit-3).'...';}
   $pdf->rgb($bold?.08:.20,$bold?.27:.24,$bold?.61:.31);
   $pdf->text(55,$y,$label,$bold?8.6:8.4,$bold);
   if($qty!=='')$pdf->text(343,$y,$qty,8.4,$bold,'right');
   if($rate!=='')$pdf->text(436,$y,$rate,8.4,$bold,'right');
   $pdf->text(541,$y,$amount,$bold?8.8:8.4,$bold,'right');
   $y-=19;
  };
  foreach($groups as $name=>$entries){
   if(!$entries)continue;
   $section($name);
   foreach($entries as $entry){
    $lineRow($entry['label'],$entry['count']>0?$entry['count'].'x':'',$entry['rate'],$moneyShort($entry['amount']));
   }
  }
  $section('TOTAL PEMBAYARAN');
  $lineRow('Total penerimaan','','',$moneyShort($s['gross_total']??0));
  if((int)($s['deduction_total']??0)!==0)$lineRow('Total potongan','','',$moneyShort($s['deduction_total']??0));
  $lineRow('Hak dokter','','',$moneyShort($s['net_total']??0),true);
  $cashCount=count($s['cash']??[]);
  $lineRow('Cash diterima'.($cashCount>1?' ('.$cashCount.' transaksi)':''),'','',$moneyShort($s['cash_total']??0));
  $pdf->rgb(.6,.7,.85);$pdf->line(315,$y+7,541,$y+7,.7);
  $lineRow('SISA TRANSFER','','',$moneyShort($s['transfer_due']??0),true);
  $pdf->rgb(.47,.50,.54);$pdf->text(48,38,'Lampiran dari snapshot honor. Dokumen internal.',7.3);
  return $pdf->output();
 }
}
