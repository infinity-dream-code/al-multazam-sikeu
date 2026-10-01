@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'DATA BATASAN ( SETTING ORTU )' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">CEK Batas Jajan</li>
    </ul>

    @if (session('smartcard_success'))
        <div class="alert alert-success">{{ session('smartcard_success') }}</div>
    @endif
    @if (session('smartcard_error'))
        <div class="alert alert-danger">{{ session('smartcard_error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-primary">
            <h5 class="mb-0 text-white">Filter</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.smartcard.cek-batas-jajan.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="search" value="1">
                <div class="col-md-2">
                    <label class="form-label" for="nis">NIS</label>
                    <input type="text" class="form-control" id="nis" name="nis"
                           value="{{ $filters['nis'] ?? '' }}" placeholder="NIS / No Pend">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="nama">Nama</label>
                    <input type="text" class="form-control" id="nama" name="nama"
                           value="{{ $filters['nama'] ?? '' }}" placeholder="Nama siswa">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="kelas">Kelas</label>
                    <input type="text" class="form-control" id="kelas" name="kelas"
                           value="{{ $filters['kelas'] ?? '' }}" placeholder="CODE03 / Kelas">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="tahunMasuk">Tahun Angkatan</label>
                    <input type="text" class="form-control" id="tahunMasuk" name="tahun_masuk"
                           value="{{ $filters['tahun_masuk'] ?? '' }}" placeholder="DESC04">
                </div>
                <div class="col-md-3 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">Cari</button>
                    <a href="{{ route('admin.smartcard.cek-batas-jajan.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Hasil Batasan</h5>
            @if ($isSearch)
                <small class="text-muted">{{ $rows->total() }} data</small>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>NIS</th>
                    <th>Nama</th>
                    <th class="text-end">Batas Belanja/Kantin</th>
                    <th class="text-end">Batas Cash</th>
                    <th>Kelas</th>
                    <th>Thn Angkatan</th>
                </tr>
                </thead>
                <tbody>
                @if (!$isSearch)
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Isi filter lalu klik Cari.</td>
                    </tr>
                @else
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ trim((string) ($row->nis ?? '')) ?: trim((string) ($row->no_pend ?? '')) }}</td>
                            <td>{{ $row->nama }}</td>
                            <td class="text-end">{{ number_format((int) ($row->batas_belanja_hari ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((int) ($row->batas_cash ?? 0), 0, ',', '.') }}</td>
                            <td>{{ trim((string) ($row->kelas ?? '')) ?: trim((string) ($row->desc_kelas ?? '')) }}</td>
                            <td>{{ $row->tahun_masuk }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Tidak ada data batasan sesuai filter.</td>
                        </tr>
                    @endforelse
                @endif
                </tbody>
            </table>
        </div>
        @if ($isSearch && method_exists($rows, 'links'))
            <div class="card-footer">
                {{ $rows->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
