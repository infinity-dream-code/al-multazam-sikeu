<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use App\Models\scctcust;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Debit Saldo — DEBIT 3000 Biaya Admin (builder).
 * Cari: isi list siswa. Debit: tiap CUSTID insert sccttran DEBET 3000, METODE=REDUCE, FIDBANK=AdminFee.
 */
class DebitSaldoController extends Controller
{
    private const DEBIT_AMOUNT = 3000;

    private const SESSION_ROWS = 'debit_saldo_admin_rows';

    private string $vaPrefix;

    public function __construct()
    {
        $this->vaPrefix = (string) config('app.nova', env('APP_NOVA', '752038'));
    }

    public function index(Request $request): View
    {
        $rows = collect(session(self::SESSION_ROWS, []));

        return view('admin.smartcard.debit_saldo.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Debit Saldo',
            'dataTitle' => 'DEBIT ' . number_format(self::DEBIT_AMOUNT, 0, ',', '.') . ' Biaya Admin',
            'rows' => $rows,
            'debitAmount' => self::DEBIT_AMOUNT,
            'filters' => [
                'nis' => trim((string) $request->query('nis', '')),
                'nama' => trim((string) $request->query('nama', '')),
                'kelas' => trim((string) $request->query('kelas', '')),
            ],
        ]);
    }

    public function cari(Request $request): RedirectResponse
    {
        $nis = trim((string) $request->input('nis', ''));
        $nama = trim((string) $request->input('nama', ''));
        $kelas = trim((string) $request->input('kelas', ''));

        try {
            $query = DB::connection('DATA_MYSQL')
                ->table('scctcust')
                ->where('STCUST', 1);

            $this->applySchoolScope($query);

            if ($nis !== '') {
                $query->where(function ($q) use ($nis) {
                    $q->where('NOCUST', 'like', '%' . $nis . '%')
                        ->orWhere('NUM2ND', 'like', '%' . $nis . '%');
                });
            }
            if ($nama !== '') {
                $query->whereRaw('LOWER(NMCUST) LIKE ?', ['%' . mb_strtolower($nama) . '%']);
            }
            if ($kelas !== '') {
                $query->where(function ($q) use ($kelas) {
                    $q->whereRaw('TRIM(DESC02) = ?', [$kelas])
                        ->orWhereRaw('TRIM(DESC03) = ?', [$kelas])
                        ->orWhereRaw('TRIM(CODE02) = ?', [$kelas]);
                });
            }

            $rows = $query
                ->orderBy('CUSTID')
                ->limit(2000)
                ->get([
                    'CUSTID',
                    'NOCUST',
                    'NUM2ND',
                    'NMCUST',
                    'STCUST',
                    'CODE02',
                    'DESC02',
                    'DESC03',
                    'DESC04',
                ])
                ->map(function ($row) {
                    $nis = trim((string) ($row->NOCUST ?? ''));
                    if ($nis === '') {
                        $nis = trim((string) ($row->NUM2ND ?? ''));
                    }

                    return [
                        'custid' => (int) $row->CUSTID,
                        'nis' => $nis,
                        'no_va' => ($nis !== '')
                            ? scctcust::formatVA($this->vaPrefix, $nis)
                            : '-',
                        'nama' => trim((string) ($row->NMCUST ?? '')),
                        'no_pend' => trim((string) ($row->NUM2ND ?? '')) ?: '-',
                        'status' => (string) ($row->STCUST ?? ''),
                        'jenjang' => trim((string) ($row->CODE02 ?? '')),
                        'kelas' => trim((string) ($row->DESC02 ?? '')),
                        'kelompok' => trim((string) ($row->DESC03 ?? '')),
                        'thn_masuk' => trim((string) ($row->DESC04 ?? '')),
                    ];
                })
                ->values()
                ->all();

            session([self::SESSION_ROWS => $rows]);

            return redirect()
                ->route('admin.smartcard.debit-saldo.index', array_filter([
                    'nis' => $nis !== '' ? $nis : null,
                    'nama' => $nama !== '' ? $nama : null,
                    'kelas' => $kelas !== '' ? $kelas : null,
                ]))
                ->with('smartcard_success', count($rows) . ' siswa dimuat. Klik Debit untuk proses.');
        } catch (\Throwable $e) {
            Log::error('DebitSaldo cari failed', ['message' => $e->getMessage()]);
            report($e);

            return redirect()
                ->route('admin.smartcard.debit-saldo.index')
                ->with('smartcard_error', 'Gagal cari: ' . $e->getMessage());
        }
    }

    public function debit(Request $request): RedirectResponse
    {
        $rows = collect(session(self::SESSION_ROWS, []));
        if ($rows->isEmpty()) {
            return redirect()
                ->route('admin.smartcard.debit-saldo.index')
                ->with('smartcard_error', 'List kosong. Klik Cari dulu.');
        }

        $ok = 0;
        $skip = 0;
        $amount = self::DEBIT_AMOUNT;

        try {
            DB::connection('DATA_MYSQL')->transaction(function () use ($rows, $amount, &$ok, &$skip) {
                foreach ($rows as $row) {
                    $custid = (int) (is_array($row) ? ($row['custid'] ?? 0) : ($row->custid ?? 0));
                    if ($custid <= 0) {
                        $skip++;
                        continue;
                    }

                    DB::connection('DATA_MYSQL')->table('sccttran')->insert([
                        'CUSTID' => $custid,
                        'NOREFF' => '',
                        'FIDBANK' => 'AdminFee',
                        'TRXDATE' => now()->format('Y-m-d H:i:s'),
                        'KDCHANNEL' => '',
                        'DEBET' => $amount,
                        'KREDIT' => 0,
                        'METODE' => 'REDUCE',
                        'REFFBANK' => '38',
                    ]);
                    $ok++;
                }
            });
        } catch (\Throwable $e) {
            Log::error('DebitSaldo debit failed', ['message' => $e->getMessage()]);
            report($e);

            return redirect()
                ->route('admin.smartcard.debit-saldo.index')
                ->with('smartcard_error', 'Gagal debit: ' . $e->getMessage());
        }

        session()->forget(self::SESSION_ROWS);

        return redirect()
            ->route('admin.smartcard.debit-saldo.index')
            ->with('smartcard_success', "Sukses. Debit Rp " . number_format($amount, 0, ',', '.') . " × {$ok} siswa" . ($skip ? ", skip {$skip}" : '') . '.');
    }

    public function clear(): RedirectResponse
    {
        session()->forget(self::SESSION_ROWS);

        return redirect()
            ->route('admin.smartcard.debit-saldo.index')
            ->with('smartcard_success', 'List dikosongkan.');
    }

    private function applySchoolScope($query): void
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit === '') {
            return;
        }

        $query->where(function ($q) use ($unit) {
            $q->whereRaw('TRIM(CAST(CODE01 AS CHAR)) = ?', [$unit])
                ->orWhereRaw('TRIM(CAST(CODE02 AS CHAR)) = ?', [$unit]);
        });
    }
}
