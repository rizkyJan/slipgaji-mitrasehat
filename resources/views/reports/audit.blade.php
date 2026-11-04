@extends('layouts.app')
@section('title','Log Aktivitas')
@section('content')
<div class="page-head"><div><p class="eyebrow">JEJAK AUDIT</p><h1 class="page-title">Log Aktivitas Admin</h1><p class="page-sub">Pencatatan perubahan data, slip terbit dan pengiriman email.</p></div></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>ID Pengguna</th><th>Aksi</th><th>Entitas</th><th>Ringkasan</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at?->format('d/m/Y H:i') }}</td><td>{{ $log->user_id ?? '—' }}</td><td><strong>{{ $log->action }}</strong></td><td>{{ $log->entity_type ?: '—' }} #{{ $log->entity_id }}</td><td class="subtle">{{ \Illuminate\Support\Str::limit(json_encode($log->details, JSON_UNESCAPED_UNICODE), 110) }}</td></tr>@empty<tr><td colspan="5" class="empty">Belum ada riwayat aktivitas.</td></tr>@endforelse</tbody></table></div><div class="pagination">@include('partials.pagination', ['paginator' => $logs])</div></div>
@endsection
