<?php
namespace App\Services;

use App\Models\GoogleConnection;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GmailService
{
    private function credentials(): array
    {
        $id = (string) config('services.google_payroll.client_id');
        $secret = (string) config('services.google_payroll.client_secret');
        $redirect = (string) config('services.google_payroll.redirect');
        if (!$id || !$secret || !$redirect) {
            throw new RuntimeException('Google OAuth belum dikonfigurasi. Isi GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, dan GOOGLE_REDIRECT_URI pada .env.');
        }
        return compact('id', 'secret', 'redirect');
    }

    public function authorizationUrl(string $state): string
    {
        $c = $this->credentials();
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $c['id'], 'redirect_uri' => $c['redirect'],
            'response_type' => 'code',
            'scope' => 'openid email https://www.googleapis.com/auth/gmail.send',
            'access_type' => 'offline', 'prompt' => 'consent',
            'include_granted_scopes' => 'true', 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function connect(User $user, string $code): GoogleConnection
    {
        $c = $this->credentials();
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code' => $code, 'client_id' => $c['id'], 'client_secret' => $c['secret'],
            'redirect_uri' => $c['redirect'], 'grant_type' => 'authorization_code',
        ]);
        if (!$response->successful() || !$response->json('access_token')) {
            throw new RuntimeException('Google menolak otorisasi. Periksa Redirect URI dan status OAuth di Google Cloud Console.');
        }
        $access = (string) $response->json('access_token');
        $identity = Http::withToken($access)->timeout(20)->get('https://openidconnect.googleapis.com/v1/userinfo');
        if (!$identity->successful() || !$identity->json('email') || !$identity->json('sub')) {
            throw new RuntimeException('Email akun Google tidak dapat diverifikasi.');
        }
        $existing = GoogleConnection::query()->where('user_id', $user->id)->first();
        $refresh = $response->json('refresh_token') ?: $existing?->refresh_token;
        if (!$refresh) throw new RuntimeException('Google tidak memberikan refresh token. Putuskan akses aplikasi dari akun Google, lalu hubungkan ulang.');
        return GoogleConnection::updateOrCreate(['user_id' => $user->id], [
            'google_subject' => (string) $identity->json('sub'),
            'email' => (string) $identity->json('email'),
            'access_token' => $access, 'refresh_token' => $refresh,
            'expires_at' => now()->addSeconds(max(1, ((int)$response->json('expires_in', 3600)) - 60)),
        ]);
    }

    private function accessToken(GoogleConnection $connection): string
    {
        if ($connection->expires_at && $connection->expires_at->isFuture() && $connection->access_token) {
            return $connection->access_token;
        }
        $c = $this->credentials();
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id' => $c['id'], 'client_secret' => $c['secret'],
            'refresh_token' => $connection->refresh_token, 'grant_type' => 'refresh_token',
        ]);
        if (!$response->successful() || !$response->json('access_token')) {
            throw new RuntimeException('Akses Google kedaluwarsa/dicabut. Hubungkan ulang akun pada Pengaturan Email.');
        }
        $connection->update([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds(max(1, ((int) $response->json('expires_in', 3600)) - 60)),
        ]);
        return $connection->access_token;
    }

    public function sendPayslip(GoogleConnection $connection, Payroll $payroll, string $pdf): string
    {
        $recipient = filter_var($payroll->employee->email, FILTER_VALIDATE_EMAIL);
        if (!$recipient) throw new RuntimeException('Email penerima tidak valid. Perbaiki email karyawan terlebih dahulu.');
        $name = $payroll->calculation_snapshot['employee']['name'];
        $subject = 'Slip Gaji '. $payroll->period.' - '.$name;
        $fileName = 'slip-gaji-'.preg_replace('/[^A-Za-z0-9_-]/', '_', $payroll->employee->employee_code).'-'.$payroll->period.'.pdf';
        $boundary = 'payroll_'.Str::random(32);
        $body = 'Yth. '.htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').',<br><br>'.
            'Terlampir slip gaji periode '.htmlspecialchars($payroll->period, ENT_QUOTES, 'UTF-8').
            ' dari Klinik Pratama Mitra Sehat.<br>Dokumen ini bersifat rahasia dan hanya untuk penerima.'.
            '<br><br>Salam,<br>Administrasi Klinik';
        $mime = implode("\r\n", [
            'From: '.$connection->email,
            'To: '.$recipient,
            'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="'.$boundary.'"',
            '',
            '--'.$boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($body), 76, "\r\n")),
            '--'.$boundary,
            'Content-Type: application/pdf; name="'.$fileName.'"',
            'Content-Disposition: attachment; filename="'.$fileName.'"',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($pdf), 76, "\r\n")),
            '--'.$boundary.'--',
            '',
        ]);
        $raw = rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
        $response = Http::withToken($this->accessToken($connection))
            ->timeout(40)->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $raw]);
        if (!$response->successful() || !$response->json('id')) {
            $reason = (string) $response->json('error.message', 'Google Gmail API gagal mengirim email.');
            throw new RuntimeException('Gmail: '.Str::limit($reason, 180));
        }
        return (string) $response->json('id');
    }

    public function disconnect(GoogleConnection $connection): void
    {
        try {
            Http::asForm()->timeout(5)->post('https://oauth2.googleapis.com/revoke', ['token' => $connection->refresh_token]);
        } catch (\Throwable) {
            // Koneksi lokal tetap diputus walaupun revoke Google sedang tidak dapat dijangkau.
        }
        $connection->delete();
    }
}
