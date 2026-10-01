@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'TOP UP SALDO AWAL' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">Migrasi Saldo Awal</li>
    </ul>

    @if (session('smartcard_success'))
        <div class="alert alert-success">{{ session('smartcard_success') }}</div>
    @endif
    @if (session('smartcard_error'))
        <div class="alert alert-danger">{{ session('smartcard_error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">TOP UP SALDO AWAL</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.smartcard.migrasi-saldo-awal.open') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">File open</label>
                    <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required>
                    <small class="text-muted">Kolom: ID/CUSTID, NIS, Nama, SALDO, Unit, Kelas, Kelompok (header opsional). Kalau tanpa CUSTID, dicari dari NIS.</small>
                </div>
                <div class="col-md-6 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-outline-primary">OPEN</button>
                    <button type="submit" class="btn btn-primary"
                            form="formUpdate" @disabled(($rows ?? collect())->isEmpty())>UPDATE</button>
                    <button type="submit" class="btn btn-outline-secondary"
                            form="formClear" @disabled(($rows ?? collect())->isEmpty())>Clear</button>
                </div>
            </form>
            <form id="formUpdate" method="POST" action="{{ route('admin.smartcard.migrasi-saldo-awal.update') }}"
                  onsubmit="return confirm('Proses injek saldo untuk {{ ($rows ?? collect())->count() }} baris?');">
                @csrf
            </form>
            <form id="formClear" method="POST" action="{{ route('admin.smartcard.migrasi-saldo-awal.clear') }}">
                @csrf
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="mb-0">List Data</h5>
            <small class="text-muted">
                {{ ($rows ?? collect())->count() }} baris
                · valid {{ ($rows ?? collect())->where('valid', true)->count() }}
            </small>
        </div>
        <div class="table-responsive" style="max-height:520px;">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light sticky-top">
                <tr>
                    <th>ID</th>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th class="text-end">SALDO</th>
                    <th>Unit</th>
                    <th>Kelas</th>
                    <th>Kelompok</th>
                    <th>Tahun Siswa</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse (($rows ?? collect()) as $row)
                    @php $r = (object) $row; @endphp
                    <tr class="{{ empty($r->valid) ? 'table-warning' : '' }}">
                        <td>{{ $r->custid ?: '—' }}</td>
                        <td>{{ $r->nis ?: '—' }}</td>
                        <td>{{ $r->nama ?: '—' }}</td>
                        <td class="text-end">{{ number_format((int) ($r->saldo ?? 0), 0, ',', '.') }}</td>
                        <td>{{ $r->unit ?: '—' }}</td>
                        <td>{{ $r->kelas ?: '—' }}</td>
                        <td>{{ $r->kelompok ?: '—' }}</td>
                        <td>{{ $r->tahun ?: '—' }}</td>
                        <td>
                            @if (!empty($r->valid))
                                <span class="badge bg-success">Siap</span>
                            @else
                                <span class="badge bg-warning text-dark">Skip</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Belum ada data. Klik OPEN untuk memuat file.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
