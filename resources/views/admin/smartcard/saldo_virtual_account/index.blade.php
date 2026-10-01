@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? 'Data Saldo Virtual Account Siswa' }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">Saldo Virtual Account</li>
    </ul>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Filter</h5></div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.smartcard.saldo-virtual-account.index') }}" id="svaForm">
                <input type="hidden" name="search" value="1">
                <input type="hidden" name="mode" id="svaMode" value="{{ $filters['mode'] ?? 'cari' }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Tahun Akademik</label>
                        <select class="form-select" name="thn_aka">
                            <option value="">Semua</option>
                            @foreach ($thnAkaOptions as $aka)
                                <option value="{{ $aka }}" @selected(($filters['thn_aka'] ?? '') === $aka)>{{ $aka }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">NIS</label>
                        <input type="text" class="form-control" name="nis" value="{{ $filters['nis'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" name="nama" value="{{ $filters['nama'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Kelas</label>
                        <select class="form-select" name="kelas">
                            <option value="">Semua</option>
                            @foreach ($kelasOptions as $k)
                                <option value="{{ $k }}" @selected(($filters['kelas'] ?? '') === $k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tahun Angkatan</label>
                        <select class="form-select" name="thn_angkatan">
                            <option value="">Semua</option>
                            @foreach ($angkatanOptions as $a)
                                <option value="{{ $a }}" @selected(($filters['thn_angkatan'] ?? '') === $a)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Saldo</label>
                        @php
                            $initialSaldo = 0;
                            if (($isSearch ?? false) && ($filters['mode'] ?? '') === 'saldo') {
                                $initialSaldo = (int) collect($rows)->sum('saldo');
                            }
                        @endphp
                        <input type="text" class="form-control fw-bold text-primary" id="svaSaldoBox"
                               value="{{ number_format($initialSaldo, 0, ',', '.') }}" readonly>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary" onclick="document.getElementById('svaMode').value='cari'">Cari</button>
                        <button type="submit" class="btn btn-info" onclick="document.getElementById('svaMode').value='saldo'">Cari Saldo</button>
                        <button type="button" class="btn btn-secondary" id="btnLihatTransaksi">Lihat Transaksi</button>
                        <a href="{{ route('admin.smartcard.saldo-virtual-account.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if (!empty($errorMessage))
        <div class="alert alert-danger">{{ $errorMessage }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="mb-0">Data Siswa</h5>
                    <small class="text-muted">
                        @if ($isSearch ?? false)
                            {{ count($rows) }} data
                            @if (count($rows) >= ($maxRows ?? 500))
                                (maks {{ $maxRows ?? 500 }})
                            @endif
                        @endif
                    </small>
                </div>
                <div class="table-responsive" style="max-height: 480px;">
                    <table class="table table-sm table-bordered table-hover mb-0" id="svaSiswaTable">
                        <thead class="table-light">
                        @if (($filters['mode'] ?? 'cari') === 'saldo')
                            <tr>
                                <th style="width:28px;"></th>
                                <th>Nama</th>
                                <th class="text-end">Saldo</th>
                                <th>Kelas</th>
                                <th>Gender</th>
                                <th>Kelompok</th>
                            </tr>
                        @else
                            <tr>
                                <th style="width:28px;"></th>
                                <th>NIS</th>
                                <th>No Pend</th>
                                <th>NO VA</th>
                                <th>Nama</th>
                            </tr>
                        @endif
                        </thead>
                        <tbody>
                        @if ($isSearch ?? false)
                            @forelse ($rows as $row)
                                <tr class="sva-row" data-custid="{{ (int) $row->CUSTID }}" data-saldo="{{ (int) $row->saldo }}">
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input sva-check" value="{{ (int) $row->CUSTID }}">
                                    </td>
                                    @if (($filters['mode'] ?? 'cari') === 'saldo')
                                        <td>{{ $row->nama }}</td>
                                        <td class="text-end">{{ number_format((int) $row->saldo, 0, ',', '.') }}</td>
                                        <td>{{ $row->kelas }}</td>
                                        <td>{{ $row->gender }}</td>
                                        <td>{{ $row->kelompok }}</td>
                                    @else
                                        <td>{{ $row->nis }}</td>
                                        <td>{{ $row->no_pend }}</td>
                                        <td>{{ $row->no_va }}</td>
                                        <td>{{ $row->nama }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Tidak ada data.</td>
                                </tr>
                            @endforelse
                        @else
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Gunakan filter lalu klik Cari / Cari Saldo.</td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Transaksi</h5></div>
                <div class="table-responsive" style="max-height: 480px;">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                        <tr>
                            <th class="text-end">Debet</th>
                            <th class="text-end">Kredit</th>
                            <th>Tgl Transaksi</th>
                            <th>Remark</th>
                        </tr>
                        </thead>
                        <tbody id="svaTrxBody">
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Pilih siswa lalu klik Lihat Transaksi.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
(function () {
    const saldoBox = document.getElementById('svaSaldoBox');
    const trxBody = document.getElementById('svaTrxBody');
    const btnTrx = document.getElementById('btnLihatTransaksi');
    const trxUrl = @json(route('admin.smartcard.saldo-virtual-account.transaksi'));

    function fmt(n) {
        return new Intl.NumberFormat('id-ID').format(Number(n || 0));
    }

    function selectedIds() {
        return Array.from(document.querySelectorAll('.sva-check:checked')).map(el => el.value);
    }

    function updateSaldoFromSelection() {
        const checked = Array.from(document.querySelectorAll('.sva-check:checked'));
        if (!checked.length) {
            saldoBox.value = '0';
            return;
        }
        let sum = 0;
        checked.forEach(ch => {
            const row = ch.closest('tr');
            sum += Number(row?.dataset?.saldo || 0);
        });
        saldoBox.value = fmt(sum);
    }

    async function loadTransaksi(ids) {
        if (!ids.length) {
            alert('Pilih minimal 1 siswa.');
            return;
        }

        trxBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Memuat...</td></tr>';

        try {
            const res = await fetch(trxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ cust_ids: ids }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Gagal memuat transaksi');

            const rows = data.rows || [];
            if (!rows.length) {
                trxBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada transaksi.</td></tr>';
                return;
            }

            let html = rows.map(r => {
                const tgl = r.tgl_transaksi ? String(r.tgl_transaksi).replace('T', ' ').substring(0, 19) : '—';
                return `<tr>
                    <td class="text-end">${fmt(r.debet)}</td>
                    <td class="text-end">${fmt(r.kredit)}</td>
                    <td>${tgl}</td>
                    <td>${r.remark || '—'}</td>
                </tr>`;
            }).join('');

            html += `<tr class="table-light">
                <td class="text-end"><strong>${fmt(data.total_debet)}</strong></td>
                <td class="text-end"><strong>${fmt(data.total_kredit)}</strong></td>
                <td colspan="2"><strong>TOTAL</strong></td>
            </tr>`;

            trxBody.innerHTML = html;
            if (ids.length === 1) {
                const row = document.querySelector(`.sva-row[data-custid="${ids[0]}"]`);
                if (row) {
                    saldoBox.value = fmt(row.dataset.saldo);
                } else {
                    saldoBox.value = fmt(data.saldo);
                }
            }
        } catch (err) {
            trxBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-3">${err.message || err}</td></tr>`;
        }
    }

    document.querySelectorAll('.sva-row').forEach(row => {
        row.addEventListener('click', (e) => {
            if (e.target.classList.contains('sva-check')) {
                updateSaldoFromSelection();
                return;
            }
            const cb = row.querySelector('.sva-check');
            if (!cb) return;
            document.querySelectorAll('.sva-check').forEach(c => { c.checked = false; });
            document.querySelectorAll('.sva-row').forEach(r => r.classList.remove('table-primary'));
            cb.checked = true;
            row.classList.add('table-primary');
            updateSaldoFromSelection();
        });
    });

    document.querySelectorAll('.sva-check').forEach(ch => {
        ch.addEventListener('change', updateSaldoFromSelection);
    });

    btnTrx?.addEventListener('click', () => loadTransaksi(selectedIds()));
})();
</script>
@endsection
