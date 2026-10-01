@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'SETTING MOBILE MERCHANT' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">Setting Merchant Mobile</li>
    </ul>

    @if (session('smartcard_success'))
        <div class="alert alert-success">{{ session('smartcard_success') }}</div>
    @endif
    @if (session('smartcard_error'))
        <div class="alert alert-danger">{{ session('smartcard_error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Tambah Merchant Mobile</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.smartcard.setting-merchant-mobile.tambah') }}"
                  class="row g-3 align-items-end" id="formTambah">
                @csrf
                <div class="col-md-3">
                    <label class="form-label" for="namaMerchant">Nama Merchant</label>
                    <input type="text" class="form-control" id="namaMerchant" name="nama_merchant"
                           value="{{ old('nama_merchant') }}" required autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="usernameMobile">Username Mobile</label>
                    <input type="text" class="form-control" id="usernameMobile" name="username_mobile"
                           value="{{ old('username_mobile') }}" required autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="jenisMerchant">Jenis Merchant</label>
                    <select class="form-select" id="jenisMerchant" name="jenis_merchant" required>
                        <option value="">— pilih —</option>
                        @foreach (($roleOptions ?? []) as $opt)
                            <option value="{{ $opt }}" @selected(old('jenis_merchant') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2 flex-wrap">
                    <a href="{{ route('admin.smartcard.setting-merchant-mobile.index') }}" class="btn btn-outline-secondary">Lihat</a>
                    <button type="submit" class="btn btn-primary" id="btnTambah">Tambah Mobile Kantin</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Merchant</h5>
            <small class="text-muted">{{ ($rows ?? collect())->count() }} data — klik baris untuk pilih</small>
        </div>
        <div class="table-responsive" style="max-height:420px;">
            <table class="table table-sm table-bordered table-hover mb-0" id="tblMerchant">
                <thead class="table-light sticky-top">
                <tr>
                    <th style="width:40px;"></th>
                    <th>Nama Kantin</th>
                    <th>Username Mobile</th>
                    <th>Jenis Kantin</th>
                </tr>
                </thead>
                <tbody>
                @forelse (($rows ?? collect()) as $row)
                    <tr class="merchant-row" data-username="{{ $row->username }}" style="cursor:pointer;">
                        <td class="text-center">
                            <input type="radio" name="pick" class="form-check-input pick-radio"
                                   value="{{ $row->username }}"
                                   aria-label="Pilih {{ $row->username }}">
                        </td>
                        <td>{{ $row->nama_kantin }}</td>
                        <td>{{ $row->username }}</td>
                        <td>{{ $row->jenis_kantin }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Belum ada data / tabel sm_kantin kosong.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="text-primary mb-3 mb-md-2">
                PILIH KANTIN YANG INGIN DI RESET PADA TABEL DI ATAS DAN KLIK TOMBOL DI BAWAH INI
            </p>
            <form method="POST" action="{{ route('admin.smartcard.setting-merchant-mobile.reset') }}"
                  id="formReset"
                  onsubmit="return confirm('Reset password merchant yang dipilih?');">
                @csrf
                <input type="hidden" name="username" id="resetUsername" value="">
                <button type="submit" class="btn btn-warning" id="btnReset" disabled>
                    Reset Password Merchant Mobile
                </button>
            </form>
        </div>
    </div>
@endsection

@section('script')
<script>
(function () {
    const resetInput = document.getElementById('resetUsername');
    const btnReset = document.getElementById('btnReset');

    function selectUsername(u) {
        resetInput.value = u || '';
        btnReset.disabled = !u;
        document.querySelectorAll('.merchant-row').forEach(function (tr) {
            tr.classList.toggle('table-active', tr.getAttribute('data-username') === u);
        });
        document.querySelectorAll('.pick-radio').forEach(function (r) {
            r.checked = r.value === u;
        });
    }

    document.querySelectorAll('.merchant-row').forEach(function (tr) {
        tr.addEventListener('click', function () {
            selectUsername(tr.getAttribute('data-username') || '');
        });
    });
})();
</script>
@endsection
