<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::create('doctor_assignments',function(Blueprint $t){
   $t->id(); $t->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
   $t->string('branch',100); $t->string('profile_label',100); $t->string('kind',20)->default('general');
   $t->json('rules'); $t->unsignedBigInteger('base_salary')->default(0); $t->unsignedBigInteger('sip_fee')->default(0);
   $t->unsignedBigInteger('pj_fee')->default(0); $t->unsignedBigInteger('absence_rate')->default(0); $t->string('pj_mode',20)->default('income'); $t->boolean('active')->default(true); $t->timestamps();
   $t->unique(['employee_id','branch']);
  });
  Schema::create('doctor_procedures',function(Blueprint $t){
   $t->id(); $t->string('label',120); $t->string('branch',100)->nullable();
   $t->string('mode',20)->default('fixed'); $t->unsignedBigInteger('fixed_fee')->default(0);
   $t->decimal('percentage',6,2)->default(35); $t->boolean('active')->default(true); $t->timestamps();
  });
  Schema::create('doctor_payrolls',function(Blueprint $t){
   $t->id(); $t->foreignId('doctor_assignment_id')->constrained('doctor_assignments')->restrictOnDelete();
   $t->char('period',7); $t->string('status',20)->default('draft');
   $t->json('input_snapshot'); $t->json('calculation_snapshot');
   $t->unsignedBigInteger('gross_total')->default(0); $t->unsignedBigInteger('deduction_total')->default(0);
   $t->bigInteger('net_total')->default(0); $t->unsignedBigInteger('cash_total')->default(0);
   $t->bigInteger('transfer_due')->default(0);
   $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
   $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
   $t->timestamp('approved_at')->nullable();
   $t->string('slip_number',60)->nullable()->unique();
   $t->text('verification_token')->nullable(); $t->string('verification_signature',64)->nullable();
   $t->timestamp('issued_at')->nullable(); $t->text('notes')->nullable(); $t->timestamps();
   $t->unique(['doctor_assignment_id','period']); $t->index(['period','status']);
  });
  Schema::create('doctor_deliveries',function(Blueprint $t){
   $t->id(); $t->foreignId('doctor_payroll_id')->constrained('doctor_payrolls')->cascadeOnDelete();
   $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
   $t->string('recipient_email'); $t->string('status',20); $t->string('gmail_message_id')->nullable();
   $t->text('error')->nullable(); $t->timestamp('sent_at')->nullable(); $t->timestamps();
  });
  $defaults=[
   ['Medikasi Kecil','fixed',7500],['Medikasi Sedang','fixed',8500],['Medikasi Besar','fixed',10000],
   ['Injeksi','fixed',6500],['Nebulizer','fixed',8000],['Aff Hecting','fixed',8500],
   ['Hecting 1-5','fixed',16250],['Hecting 6-10','fixed',19500],['Hecting di atas 10','fixed',22750],
   ['Pemasangan Infus','fixed',12000],['Homecare tindakan','fixed',60000],
   ['Tindakan gigi umum (35% setelah BHMP)','percentage',0],
   ['Protesa gigi (BPJS + pasien - lab)','prosthesis',0],
  ];
  foreach($defaults as [$name,$mode,$amount]) DB::table('doctor_procedures')->insert(['label'=>$name,'mode'=>$mode,'fixed_fee'=>$amount,'percentage'=>35,'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down():void {
  Schema::dropIfExists('doctor_deliveries'); Schema::dropIfExists('doctor_payrolls');
  Schema::dropIfExists('doctor_procedures'); Schema::dropIfExists('doctor_assignments');
 }
};
