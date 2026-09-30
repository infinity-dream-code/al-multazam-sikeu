@extends('layouts.admin_new')
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

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">History Data Pencairan Belanja ke Kantin</h5></div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.history-data-lama.history-pencairan-kantin.index') }}" id="rpFormGet">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="kdMercan">Merchant</label>
                        <select class="form-select" id="kdMercan" name="kd_mercan">
                            <option value="">Pilih Merchant</option>
                            @foreach ($mercanOptions as $m)
                                <option value="{{ $m->kode }}" @selected(($kdMercan ?? '') === $m->kode)>{{ $m->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="dariTanggal">Dari Tanggal</label>
                        <input type="date" class="form-control" id="dariTanggal" name="dari_tanggal" value="{{ $dariTanggal ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="sampaiTanggal">Sampai Tanggal</label>
                        <input type="date" class="form-control" id="sampaiTanggal" name="sampai_tanggal" value="{{ $sampaiTanggal ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="namaPenerima">Nama Penerima</label>
                        <input type="text" class="form-control" id="namaPenerima" name="nama_penerima"
                               value="{{ $namaPenerima ?? '' }}" placeholder="Nama penerima" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="nominal">Nominal</label>
                        <input type="text" class="form-control" id="nominal" name="nominal"
                               value="{{ $nominal !== '' ? $nominal : '0' }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">No Terima</label>
                        <input type="text" class="form-control" value="{{ $previewNoTerima ?? '' }}" readonly>
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" name="cari_transaksi" value="1" class="btn btn-primary">Cari Transaksi</button>
                        <button type="submit" name="liat_pencairan" value="1" class="btn btn-outline-secondary">Liat Pencairan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Transaksi Belanja</h5></div>
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Tgl Transaksi</th>
                            <th class="text-end">Saldo</th>
                            <th>Kantin</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if ($cariTransaksi ?? false)
                            @forelse (($transaksiRows ?? collect()) as $row)
                                <tr>
                                    <td>
                                        @if (!empty($row->tgl_transaksi))
                                            {{ \Illuminate\Support\Carbon::parse($row->tgl_transaksi)->format('d-m-Y H:i') }}
                                        @else — @endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) ($row->saldo ?? 0), 0, ',', '.') }}</td>
                                    <td>{{ $row->kantin ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">Tidak ada transaksi pada periode ini.</td></tr>
                            @endforelse
                            @if (($transaksiRows ?? collect())->isNotEmpty())
                                <tr class="table-light">
                                    <td><strong>Total</strong></td>
                                    <td class="text-end"><strong>{{ number_format((float) ($transaksiTotal ?? 0), 0, ',', '.') }}</strong></td>
                                    <td></td>
                                </tr>
                            @endif
                        @else
                            <tr><td colspan="3" class="text-center text-muted">Pilih merchant & tanggal, lalu klik Cari Transaksi.</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Data Pencairan</h5></div>
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Tgl Terima</th>
                            <th>Nama Penerima</th>
                            <th class="text-end">Nominal</th>
                            <th>No Terima</th>
                        </tr>
                        </thead>
                        <tbody>
                        @if ($liatPencairan ?? false)
                            @forelse (($pencairanRows ?? collect()) as $row)
                                <tr>
                                    <td>
                                        @if (!empty($row->tgl_terima))
                                            {{ \Illuminate\Support\Carbon::parse($row->tgl_terima)->format('Y-m-d H:i:s') }}
                                        @else — @endif
                                    </td>
                                    <td>{{ $row->nama_penerima ?? '—' }}</td>
                                    <td class="text-end">{{ number_format((float) ($row->nominal ?? 0), 0, ',', '.') }}</td>
                                    <td>{{ $row->no_terima ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Belum ada data pencairan.</td></tr>
                            @endforelse
                            @if (($pencairanRows ?? collect())->isNotEmpty())
                                <tr class="table-light">
                                    <td colspan="2"><strong>Total</strong></td>
                                    <td class="text-end"><strong>{{ number_format((float) ($pencairanTotal ?? 0), 0, ',', '.') }}</strong></td>
                                    <td></td>
                                </tr>
                            @endif
                        @else
                            <tr><td colspan="4" class="text-center text-muted">Klik Liat Pencairan untuk menampilkan data.</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
