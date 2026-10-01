<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use App\Models\scctcust;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SaldoVirtualAccountController extends Controller
{
    /** Maks siswa per load (sama seperti builder). */
    private const MAX_ROWS = 500;

    private string $vaPrefix;

    public function __construct()
    {
        $this->vaPrefix = (string) config('app.nova', env('APP_NOVA', '752038'));
    }

    public function index(Request $request): View
    {
        $filters = [
            'thn_aka' => trim((string) $request->query('thn_aka', '')),
            'nis' => trim((string) $request->query('nis', '')),
            'nama' => trim((string) $request->query('nama', '')),
            'kelas' => trim((string) $request->query('kelas', '')),
            'thn_angkatan' => trim((string) $request->query('thn_angkatan', '')),
            'mode' => trim((string) $request->query('mode', 'cari')), // cari | saldo
        ];

        if ($filters['thn_aka'] === '') {
            $filters['thn_aka'] = $this->defaultThnAka();
        }

        $isSearch = $request->boolean('search');
        $rows = collect();
        $errorMessage = null;

        if ($isSearch) {
            try {
                $rows = $this->fetchSaldoRows($filters);
            } catch (\Throwable $e) {
                Log::error('Smartcard SaldoVA fetch failed', ['message' => $e->getMessage()]);
                report($e);
                $errorMessage = 'Gagal memuat data: ' . $e->getMessage();
            }
        }

        return view('admin.smartcard.saldo_virtual_account.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Saldo Virtual Account',
            'dataTitle' => 'Data Saldo Virtual Account Siswa',
            'filters' => $filters,
            'isSearch' => $isSearch,
            'rows' => $rows,
            'errorMessage' => $errorMessage,
            'thnAkaOptions' => $this->fetchThnAkaOptions(),
            'kelasOptions' => $this->fetchKelasOptions(),
            'angkatanOptions' => $this->fetchAngkatanOptions(),
            'selectedSaldo' => 0,
            'maxRows' => self::MAX_ROWS,
        ]);
    }

    public function transaksi(Request $request): JsonResponse
    {
        $custIds = $request->input('cust_ids', []);
        if (!is_array($custIds)) {
            $custIds = [$custIds];
        }
        $custIds = collect($custIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($custIds === []) {
            return response()->json(['rows' => [], 'total_debet' => 0, 'total_kredit' => 0, 'saldo' => 0]);
        }

        try {
            $rows = DB::connection('DATA_MYSQL')
                ->table('sccttran')
                ->whereIn('CUSTID', $custIds)
                ->orderByDesc('TRXDATE')
                ->orderByDesc('urut')
                ->limit(500)
                ->get([
                    'urut',
                    'CUSTID',
                    'DEBET',
                    'KREDIT',
                    'TRXDATE',
                    'METODE',
                    'NOREFF',
                    'TRANSNO',
                ])
                ->map(static function ($row) {
                    // REMARK di builder = METODE (REDUCE, TOP UP X, dll)
                    $remark = trim((string) ($row->METODE ?? ''));
                    if ($remark === '') {
                        $remark = trim((string) ($row->NOREFF ?? ''));
                    }

                    return [
                        'debet' => (int) ($row->DEBET ?? 0),
                        'kredit' => (int) ($row->KREDIT ?? 0),
                        'tgl_transaksi' => $row->TRXDATE,
                        'remark' => $remark !== '' ? $remark : '-',
                    ];
                })
                ->values()
                ->all();

            $totalDebet = (int) collect($rows)->sum('debet');
            $totalKredit = (int) collect($rows)->sum('kredit');

            return response()->json([
                'rows' => $rows,
                'total_debet' => $totalDebet,
                'total_kredit' => $totalKredit,
                'saldo' => $totalKredit - $totalDebet,
            ]);
        } catch (\Throwable $e) {
            Log::error('Smartcard SaldoVA transaksi failed', ['message' => $e->getMessage()]);
            report($e);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function fetchSaldoRows(array $filters)
    {
        $query = DB::connection('DATA_MYSQL')->table('v_saldo_va as v');

        $this->applySchoolScope($query);

        if ($filters['nis'] !== '') {
            $query->where(function ($q) use ($filters) {
                $q->where('v.NOCUST', 'like', '%' . $filters['nis'] . '%')
                    ->orWhere('v.NUM2ND', 'like', '%' . $filters['nis'] . '%');
            });
        }

        if ($filters['nama'] !== '') {
            $query->whereRaw('LOWER(v.NMCUST) LIKE ?', ['%' . mb_strtolower($filters['nama']) . '%']);
        }

        if ($filters['kelas'] !== '') {
            $query->where(function ($q) use ($filters) {
                $q->whereRaw('TRIM(v.DESC02) = ?', [$filters['kelas']])
                    ->orWhereRaw('TRIM(v.DESC03) = ?', [$filters['kelas']])
                    ->orWhereRaw('TRIM(v.CODE02) = ?', [$filters['kelas']]);
            });
        }

        if ($filters['thn_angkatan'] !== '') {
            $full = $filters['thn_angkatan'];
            $base = trim((string) preg_replace('#\s*-\s*.*$#', '', $full));
            $query->where(function ($q) use ($full, $base) {
                $q->whereRaw('TRIM(v.DESC04) = ?', [$full]);
                if ($base !== '' && $base !== $full) {
                    $q->orWhereRaw('TRIM(v.DESC04) = ?', [$base]);
                }
            });
        }

        // Tahun akademik di UI (mst_thn_aka) — tidak memfilter v_saldo_va
        // agar list saldo aktif tetap muncul seperti builder.

        $rows = $query
            ->select([
                'v.CUSTID',
                'v.NOCUST',
                'v.NUM2ND',
                'v.NMCUST',
                'v.CODE04',
                'v.CODE02',
                'v.DESC02',
                'v.DESC03',
                'v.DESC04',
                'v.SALDO',
            ])
            ->orderBy('v.NMCUST')
            ->limit(self::MAX_ROWS)
            ->get();

        return $rows->map(function ($row) {
            $nis = trim((string) ($row->NOCUST ?? ''));
            if ($nis === '' || $nis === '-') {
                $nis = trim((string) ($row->NUM2ND ?? ''));
            }
            $row->nis = $nis !== '' ? $nis : '-';
            $row->no_pend = trim((string) ($row->NUM2ND ?? '')) ?: '-';
            $row->no_va = ($nis !== '' && $nis !== '-')
                ? scctcust::formatVA($this->vaPrefix, $nis)
                : '-';
            $row->nama = trim((string) ($row->NMCUST ?? '')) ?: '-';
            $row->gender = trim((string) ($row->CODE04 ?? '')) ?: '-';
            $row->kelas = trim((string) ($row->DESC02 ?? $row->CODE02 ?? '')) ?: '-';
            $row->kelompok = trim((string) ($row->DESC03 ?? '')) ?: '-';
            $row->saldo = (int) ($row->SALDO ?? 0);

            return $row;
        });
    }

    private function applySchoolScope($query): void
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit === '') {
            return;
        }

        $query->where(function ($q) use ($unit) {
            $q->whereRaw('TRIM(CAST(v.CODE01 AS CHAR)) = ?', [$unit])
                ->orWhereRaw('TRIM(CAST(v.CODE02 AS CHAR)) = ?', [$unit]);
        });
    }

    private function defaultThnAka(): string
    {
        try {
            $val = DB::connection('DATA_MYSQL')
                ->table('mst_thn_aka')
                ->whereNotNull('thn_aka')
                ->where('thn_aka', '!=', '')
                ->orderByDesc('thn_aka')
                ->value('thn_aka');

            return trim((string) ($val ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }

    private function fetchThnAkaOptions(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
                ->table('mst_thn_aka')
                ->whereNotNull('thn_aka')
                ->where('thn_aka', '!=', '')
                ->orderByDesc('thn_aka')
                ->pluck('thn_aka')
                ->map(static fn ($v) => trim((string) $v))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchKelasOptions(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
                ->table('v_saldo_va')
                ->selectRaw('DISTINCT TRIM(DESC02) as kelas')
                ->whereNotNull('DESC02')
                ->where('DESC02', '!=', '')
                ->orderBy('kelas')
                ->pluck('kelas')
                ->map(static fn ($v) => trim((string) $v))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchAngkatanOptions(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
                ->table('v_saldo_va')
                ->selectRaw('DISTINCT TRIM(DESC04) as angkatan')
                ->whereNotNull('DESC04')
                ->where('DESC04', '!=', '')
                ->orderByDesc('angkatan')
                ->pluck('angkatan')
                ->map(static fn ($v) => trim((string) $v))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
