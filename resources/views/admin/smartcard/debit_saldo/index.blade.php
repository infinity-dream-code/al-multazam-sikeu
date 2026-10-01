@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'DEBIT Biaya Admin' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">Debit Saldo</li>
    </ul>

    @if (session('smartcard_success'))
        <div class="alert alert-success">{{ session('smartcard_success') }}</div>
    @endif
    @if (session('smartcard_error'))
        <div class="alert alert-danger">{{ session('smartcard_error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">DEBIT {{ number_format($debitAmount, 0, ',', '.') }} Biaya Admin</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.smartcard.debit-saldo.cari') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">NIS</label>
                    <input type="text" class="form-control" name="nis" value="{{ $filters['nis'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nama</label>
                    <input type="text" class="form-control" name="nama" value="{{ $filters['nama'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kelas</label>
                    <input type="text" class="form-control" name="kelas" value="{{ $filters['kelas'] ?? '' }}" placeholder="Opsional">
                </div>
                <div class="col-md-3 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-outline-primary">Cari</button>
                    <button type="submit" class="btn btn-danger"
                            form="formDebit"
                            @disabled(($rows ?? collect())->isEmpty())
                            onclick="return confirm('Debit Rp {{ number_format($debitAmount, 0, ',', '.') }} untuk {{ ($rows ?? collect())->count() }} siswa?');">
                        Debit
                    </button>
                    <button type="submit" class="btn btn-outline-secondary" form="formClear"
                            @disabled(($rows ?? collect())->isEmpty())>Clear</button>
                </div>
            </form>
            <form id="formDebit" method="POST" action="{{ route('admin.smartcard.debit-saldo.debit') }}">@csrf</form>
            <form id="formClear" method="POST" action="{{ route('admin.smartcard.debit-saldo.clear') }}">@csrf</form>
            <p class="text-muted small mb-0 mt-3">
                Setiap siswa di list akan di-debit <strong>Rp {{ number_format($debitAmount, 0, ',', '.') }}</strong>
                (<code>METODE=REDUCE</code>, <code>FIDBANK=AdminFee</code>).
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="mb-0">List Siswa</h5>
            <small class="text-muted">{{ ($rows ?? collect())->count() }} data</small>
        </div>
        <div class="table-responsive" style="max-height:520px;">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light sticky-top">
                <tr>
                    <th>NIS</th>
                    <th>NO VA</th>
                    <th>Nama</th>
                    <th>No Pendaftaran</th>
                    <th>Status</th>
                    <th>Jenjang</th>
                    <th>Kelas</th>
                    <th>Kelompok</th>
                    <th>Thn Masuk</th>
                </tr>
                </thead>
                <tbody>
                @forelse (($rows ?? collect()) as $row)
                    @php $r = (object) $row; @endphp
                    <tr>
                        <td>{{ $r->nis }}</td>
                        <td>{{ $r->no_va }}</td>
                        <td>{{ $r->nama }}</td>
                        <td>{{ $r->no_pend }}</td>
                        <td>{{ $r->status }}</td>
                        <td>{{ $r->jenjang }}</td>
                        <td>{{ $r->kelas }}</td>
                        <td>{{ $r->kelompok }}</td>
                        <td>{{ $r->thn_masuk }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Klik Cari untuk memuat siswa, lalu Debit.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
