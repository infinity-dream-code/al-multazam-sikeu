@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? ($cfg['subtitle'] ?? 'TAP') }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">{{ $cfg['title'] ?? 'TAP' }}</li>
    </ul>

    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white">{{ $cfg['subtitle'] ?? $cfg['title'] }}</h5>
                    <div class="d-flex gap-2 small">
                        <span class="badge bg-light text-dark">NIS: <span id="dispNis">—</span></span>
                        <span class="badge bg-info text-dark">CUSTID: <span id="dispCustid">—</span></span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="tapAlert" class="alert d-none" role="alert"></div>

                    <div class="table-responsive">
                        <table class="table table-bordered mb-0 align-middle">
                            <tbody>
                            <tr>
                                <th class="bg-primary text-white" style="width:32%">TAP ID</th>
                                <td>
                                    <input type="text" class="form-control form-control-lg" id="tapId"
                                           autocomplete="off" placeholder="Scan / ketik TAP ID lalu Enter" autofocus>
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-primary text-white">Nama</th>
                                <td>
                                    <input type="text" class="form-control form-control-lg text-danger fw-semibold"
                                           id="nama" readonly tabindex="-1">
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-primary text-white">SALDO</th>
                                <td>
                                    <input type="text" class="form-control form-control-lg text-danger fw-bold"
                                           id="saldo" readonly tabindex="-1" value="0">
                                </td>
                            </tr>
                            <tr>
                                <th class="bg-warning text-dark">{{ $cfg['ambil_label'] ?? 'AMBIL' }}</th>
                                <td>
                                    <input type="text" class="form-control form-control-lg text-primary fw-bold"
                                           id="ambil" inputmode="numeric" autocomplete="off" placeholder="Nominal">
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <input type="hidden" id="custid" value="">

                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="btn btn-primary btn-lg px-5" id="btnOk">OK</button>
                        <button type="button" class="btn btn-outline-secondary btn-lg" id="btnClear">Clear</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
(function () {
    const lookupUrl = @json($lookupUrl);
    const processUrl = @json($processUrl);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    const el = {
        tapId: document.getElementById('tapId'),
        nama: document.getElementById('nama'),
        saldo: document.getElementById('saldo'),
        ambil: document.getElementById('ambil'),
        custid: document.getElementById('custid'),
        dispNis: document.getElementById('dispNis'),
        dispCustid: document.getElementById('dispCustid'),
        alert: document.getElementById('tapAlert'),
        btnOk: document.getElementById('btnOk'),
        btnClear: document.getElementById('btnClear'),
    };

    let saldoNum = 0;
    let busy = false;

    function fmt(n) {
        return new Intl.NumberFormat('id-ID').format(Number(n || 0));
    }

    function parseAmt(v) {
        return parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;
    }

    function showAlert(msg, type) {
        el.alert.className = 'alert alert-' + (type || 'danger');
        el.alert.textContent = msg;
        el.alert.classList.remove('d-none');
    }

    function hideAlert() {
        el.alert.classList.add('d-none');
        el.alert.textContent = '';
    }

    function clearForm(keepFocus) {
        el.tapId.value = '';
        el.nama.value = '';
        el.saldo.value = '0';
        el.ambil.value = '';
        el.custid.value = '';
        el.dispNis.textContent = '—';
        el.dispCustid.textContent = '—';
        saldoNum = 0;
        if (keepFocus !== false) el.tapId.focus();
    }

    async function postJson(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        return { res, data };
    }

    async function doLookup() {
        hideAlert();
        const tapId = el.tapId.value.trim();
        if (!tapId) {
            showAlert('TAP ID wajib diisi.', 'warning');
            return;
        }
        if (busy) return;
        busy = true;
        try {
            const { res, data } = await postJson(lookupUrl, { tap_id: tapId });
            if (!res.ok || !data.ok) {
                showAlert(data.message || 'Kartu tidak ditemukan.');
                clearForm();
                return;
            }
            const d = data.data || {};
            el.custid.value = d.custid || '';
            el.nama.value = d.nama || '';
            saldoNum = Number(d.saldo || 0);
            el.saldo.value = fmt(saldoNum);
            el.dispNis.textContent = d.nis || '—';
            el.dispCustid.textContent = d.custid || '—';
            el.ambil.focus();
            el.ambil.select();
        } catch (e) {
            showAlert(e.message || 'Gagal lookup kartu.');
        } finally {
            busy = false;
        }
    }

    async function doProcess() {
        hideAlert();
        const tapId = el.tapId.value.trim();
        const custid = el.custid.value;
        const ambil = parseAmt(el.ambil.value);

        if (!tapId || !custid || ambil <= 0) {
            showAlert('ERROR Proses TAP');
            clearForm();
            return;
        }
        if (ambil > saldoNum) {
            showAlert('Saldo tidak cukup');
            el.ambil.focus();
            return;
        }
        if (busy) return;
        busy = true;
        el.btnOk.disabled = true;
        try {
            const { res, data } = await postJson(processUrl, {
                tap_id: tapId,
                custid: custid,
                ambil: ambil,
            });
            if (!res.ok || !data.ok) {
                showAlert(data.message || 'Proses gagal.');
                if ((data.message || '').indexOf('Saldo') !== -1) {
                    el.ambil.focus();
                } else if ((data.message || '').indexOf('Batas') !== -1) {
                    el.ambil.focus();
                } else {
                    clearForm();
                }
                return;
            }
            showAlert(data.message || 'Sukses Belanja', 'success');
            clearForm();
        } catch (e) {
            showAlert(e.message || 'Gagal proses TAP.');
        } finally {
            busy = false;
            el.btnOk.disabled = false;
        }
    }

    el.tapId.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            doLookup();
        }
    });

    el.ambil.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            doProcess();
        }
    });

    el.ambil.addEventListener('input', function () {
        const n = parseAmt(el.ambil.value);
        if (el.ambil.value !== '' && String(n) !== el.ambil.value.replace(/\D/g, '')) {
            el.ambil.value = n ? String(n) : '';
        }
    });

    el.btnOk.addEventListener('click', doProcess);
    el.btnClear.addEventListener('click', function () {
        hideAlert();
        clearForm();
    });
})();
</script>
@endsection
