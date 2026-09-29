@extends('layouts.admin_new')
@section('style')
    <link rel="stylesheet" href="{{ asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.css') }}">
@endsection
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? ($mainTitle ?? ($title ?? '')) }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        @isset($title)
            <li class="breadcrumb-item">{{ $title }}</li>
        @endisset
        @isset($mainTitle)
            <li class="breadcrumb-item active">{{ $mainTitle }}</li>
        @endisset
    </ul>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.history-data-lama.history-transaksi.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="dari_tanggal" class="form-label">Tanggal Mulai</label>
                        <input type="text" class="form-control datepicker" id="dari_tanggal"
                               name="dari_tanggal" placeholder="dd-mm-yyyy"
                               value="{{ $dari_tanggal ?? '' }}" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label for="sampai_tanggal" class="form-label">Tanggal Selesai</label>
                        <input type="text" class="form-control datepicker" id="sampai_tanggal"
                               name="sampai_tanggal" placeholder="dd-mm-yyyy"
                               value="{{ $sampai_tanggal ?? '' }}" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label for="cari" class="form-label">NIS / Nama</label>
                        <input type="text" class="form-control" id="cari" name="cari"
                               placeholder="Cari NIS atau Nama" value="{{ $cari ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-search-line me-1"></i> Cari
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $dataTitle ?? $mainTitle }}</h5>
            <small class="text-muted">Total: {{ number_format($groups->total()) }} siswa</small>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-sm table-bordered table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>NIS</th>
                    <th>VA No</th>
                    <th>Nama</th>
                    <th>Tanggal</th>
                    <th class="text-end">Debet</th>
                    <th class="text-end">Kredit</th>
                    <th>Remark</th>
                    <th>FID Bank</th>
                    <th>Keterangan</th>
                </tr>
                </thead>
                <tbody>
                @forelse($groups as $group)
                    @if($group->rows->isEmpty())
                        <tr>
                            <td>{{ $group->nis }}</td>
                            <td>{{ $group->vano }}</td>
                            <td>{{ $group->nama }}</td>
                            <td colspan="6" class="text-muted">Tidak ada transaksi</td>
                        </tr>
                    @else
                        @foreach($group->rows as $index => $row)
                            <tr>
                                @if($index === 0)
                                    <td rowspan="{{ $group->rowspan }}">{{ $group->nis }}</td>
                                    <td rowspan="{{ $group->rowspan }}">{{ $group->vano }}</td>
                                    <td rowspan="{{ $group->rowspan }}">{{ $group->nama }}</td>
                                @endif
                                <td>{{ $row->tanggal }}</td>
                                <td class="text-end">{{ number_format($row->debet, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row->kredit, 0, ',', '.') }}</td>
                                <td>{{ $row->remark }}</td>
                                <td>{{ $row->fidbank }}</td>
                                <td>{{ $row->keterangan }}</td>
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Data tidak ditemukan</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($groups->hasPages())
            <div class="card-footer">
                {{ $groups->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection

@section('script')
    <script src="{{ asset('main/libs/bootstrap-datepicker/bootstrap-datepicker.js') }}"></script>
    <script>
        $('.datepicker').datepicker({
            format: 'dd-mm-yyyy',
            autoclose: true,
            todayHighlight: true,
            orientation: 'bottom auto'
        });
    </script>
@endsection
