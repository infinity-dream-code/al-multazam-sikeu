<?php

namespace App\Http\Controllers\Admin\HistoryDataLama;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HistoryRekapCashoutController extends Controller
{
    private const PER_PAGE = 25;

    private string $title = 'History Data Lama';
    private string $mainTitle = 'History Rekap Cashout';
    private string $tranTable = 'sccttran';

    public function index(Request $request): View
    {
        $cutoffOptions = $this->fetchCutoffOptions();

        $isSearch = $request->boolean('search');
        $filters = [
            'periode_cutoff' => trim((string) $request->query('periode_cutoff', '')),
            'thn_angkatan' => trim((string) $request->query('thn_angkatan', '')),
            'kelas_id' => trim((string) $request->query('kelas_id', '')),
            'nis' => trim((string) $request->query('nis', '')),
            'nama' => trim((string) $request->query('nama', '')),
            'dari_tanggal' => trim((string) $request->query('dari_tanggal', '')),
            'sampai_tanggal' => trim((string) $request->query('sampai_tanggal', '')),
        ];

        // Default ke arsip cutoff terbaru (bukan sccttran aktif)
        if ($filters['periode_cutoff'] === '' && !empty($cutoffOptions)) {
            $filters['periode_cutoff'] = (string) ($cutoffOptions[0]->value ?? 'sccttran');
        }

        $this->tranTable = $this->resolveTranTable($filters['periode_cutoff']);

        $rows = new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $viewData = [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => 'History Rekap Keluar Uang Saku Data Cutoff',
            'filters' => $filters,
            'isSearch' => $isSearch,
            'rows' => $rows,
            'cutoffOptions' => $cutoffOptions,
            'thnAka' => $this->fetchThnAka(),
            'kelasOptions' => $this->fetchKelasOptions(),
            'activeTable' => $this->tranTable,
        ];

        if ($isSearch) {
            try {
                $viewData['rows'] = $this->fetchRows($filters);
            } catch (\Throwable $e) {
                Log::error('HistoryRekapCashout fetchRows failed', [
                    'message' => $e->getMessage(),
                    'table' => $this->tranTable,
                ]);
                report($e);
                $viewData['errorMessage'] = 'Gagal memuat data [' . $this->tranTable . ']: ' . $e->getMessage();
            }
        }

        return view('admin.history_data_lama.history_rekap_cashout.index', $viewData);
    }

    private function db()
    {
        return DB::connection('DATA_MYSQL');
    }

    /**
     * Periode cutoff = nama tabel arsip, contoh sccttran_29122025.
     */
    private function resolveTranTable(string $periodeCutoff): string
    {
        $periodeCutoff = trim($periodeCutoff);
        if ($periodeCutoff === '' || $periodeCutoff === 'sccttran') {
            return 'sccttran';
        }

        if (!preg_match('/^sccttran_\d{8}$/', $periodeCutoff)) {
            return 'sccttran';
        }

        try {
            if (Schema::connection('DATA_MYSQL')->hasTable($periodeCutoff)) {
                return $periodeCutoff;
            }
        } catch (\Throwable) {
            // ignore
        }

        return 'sccttran';
    }

    /** sccttran_29122025 → scctcashout_29122025 (sumber kolom User/Teller) */
    private function resolveCashoutTable(): ?string
    {
        $cashout = $this->tranTable === 'sccttran'
            ? 'scctcashout'
            : (string) preg_replace('/^sccttran_/', 'scctcashout_', $this->tranTable);

        if ($cashout === '' || $cashout === $this->tranTable) {
            return null;
        }

        try {
            if (Schema::connection('DATA_MYSQL')->hasTable($cashout)) {
                return $cashout;
            }
        } catch (\Throwable) {
            // ignore
        }

        return null;
    }

    private function baseQuery(array $filters)
    {
        $t = $this->tranTable;
        $co = $this->resolveCashoutTable();

        // Pre-agregasi 1x — buang duplikat ExeByEXL
        $exlKeep = $this->db()
            ->table("{$t} as d")
            ->selectRaw('d.CUSTID, d.DEBET, DATE(d.TRXDATE) as trx_day, MAX(d.urut) as max_urut')
            ->where('d.DEBET', '>', 0)
            ->whereRaw("UPPER(TRIM(d.FIDBANK)) = 'CASH'")
            ->whereRaw("UPPER(TRIM(COALESCE(d.NOREFF, ''))) = 'EXEBYEXL'")
            ->groupByRaw('d.CUSTID, d.DEBET, DATE(d.TRXDATE)');

        $query = $this->db()
            ->table("{$t} as t")
            ->join('scctcust', 'scctcust.CUSTID', '=', 't.CUSTID')
            ->leftJoin('mst_kelas', DB::raw('CAST(mst_kelas.id AS CHAR)'), '=', DB::raw('TRIM(scctcust.CODE03)'))
            ->leftJoinSub($exlKeep, 'exl_keep', function ($join) {
                $join->on('exl_keep.CUSTID', '=', 't.CUSTID')
                    ->on('exl_keep.DEBET', '=', 't.DEBET')
                    ->whereRaw('exl_keep.trx_day = DATE(t.TRXDATE)');
            })
            ->where('t.DEBET', '>', 0)
            ->whereRaw("UPPER(TRIM(t.FIDBANK)) = 'CASH'")
            ->where(function ($q) {
                $q->whereRaw("UPPER(TRIM(COALESCE(t.NOREFF, ''))) <> 'EXEBYEXL'")
                    ->orWhereColumn('t.urut', 'exl_keep.max_urut');
            });

        // Nama sekolah (bukan kode CODE01)
        $sekolah = $this->db()
            ->table('mst_sekolah')
            ->selectRaw('TRIM(CODE01) as code01, MAX(NULLIF(TRIM(DESC01), \'\')) as nama_sekolah')
            ->groupByRaw('TRIM(CODE01)');
        $query->leftJoinSub($sekolah, 'sk', function ($join) {
            $join->on(DB::raw('sk.code01'), '=', DB::raw('TRIM(scctcust.CODE01)'));
        });

        // User builder = Teller dari scctcashout(_cutoff), match siswa+tanggal+nominal
        if ($co !== null) {
            $tellerAgg = $this->db()
                ->table("{$co} as co")
                ->selectRaw('co.CUSTID, DATE(co.TanggalKeluar) as trx_day, CAST(TRIM(co.BILLAM) AS SIGNED) as billam, MAX(co.urut) as max_urut')
                ->whereRaw("UPPER(TRIM(co.FIDBANK)) = 'CASH'")
                ->groupByRaw('co.CUSTID, DATE(co.TanggalKeluar), CAST(TRIM(co.BILLAM) AS SIGNED)');

            $query->leftJoinSub($tellerAgg, 'co_agg', function ($join) {
                $join->on('co_agg.CUSTID', '=', 't.CUSTID')
                    ->whereRaw('co_agg.trx_day = DATE(t.TRXDATE)')
                    ->whereRaw('co_agg.billam = t.DEBET');
            })->leftJoin("{$co} as co", 'co.urut', '=', 'co_agg.max_urut');
        }

        $this->applySchoolScope($query);
        $this->applyFilters($query, $filters);

        return $query;
    }

    private function applySchoolScope($query): void
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit === '') {
            return;
        }

        $query->where(function ($q) use ($unit) {
            $q->whereRaw('TRIM(scctcust.CODE01) = ?', [$unit])
                ->orWhereRaw('TRIM(scctcust.CODE02) = ?', [$unit]);
        });
    }

    private function applyFilters($query, array $filters): void
    {
        // periode_cutoff dipakai untuk pilih TABEL arsip, bukan filter NOREFF

        if ($filters['nis'] !== '') {
            $query->where(function ($q) use ($filters) {
                $q->where('scctcust.NOCUST', 'like', '%' . $filters['nis'] . '%')
                    ->orWhere('scctcust.NUM2ND', 'like', '%' . $filters['nis'] . '%');
            });
        }

        if ($filters['nama'] !== '') {
            $this->applyNamaFilter($query, $filters['nama']);
        }

        if ($filters['thn_angkatan'] !== '') {
            $full = $filters['thn_angkatan'];
            $base = trim((string) preg_replace('#\s*-\s*.*$#', '', $full));
            $query->where(function ($q) use ($full, $base) {
                $q->whereRaw('TRIM(scctcust.DESC04) = ?', [$full]);
                if ($base !== '' && $base !== $full) {
                    $q->orWhereRaw('TRIM(scctcust.DESC04) = ?', [$base]);
                }
            });
        }

        if ($filters['kelas_id'] !== '') {
            $this->applyKelasFilter($query, $filters['kelas_id']);
        }

        if ($filters['dari_tanggal'] !== '') {
            $from = $this->parseDate($filters['dari_tanggal']);
            if ($from) {
                $query->where('t.TRXDATE', '>=', $from->startOfDay());
            }
        }

        if ($filters['sampai_tanggal'] !== '') {
            $to = $this->parseDate($filters['sampai_tanggal']);
            if ($to) {
                $query->where('t.TRXDATE', '<=', $to->endOfDay());
            }
        }
    }

    private function applyNamaFilter($query, string $nama): void
    {
        $nama = trim(preg_replace('/\s+/u', ' ', $nama) ?? $nama);
        if ($nama === '') {
            return;
        }

        $words = preg_split('/\s+/u', mb_strtolower($nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $query->where(function ($q) use ($nama, $words) {
            $q->whereRaw('LOWER(scctcust.NMCUST) LIKE ?', ['%' . mb_strtolower($nama) . '%']);

            if ($words === []) {
                return;
            }

            $q->orWhere(function ($q2) use ($words) {
                foreach ($words as $word) {
                    if (mb_strlen($word) < 2) {
                        continue;
                    }

                    $variants = array_values(array_unique(array_filter([
                        $word,
                        preg_replace('/r([aeiou])/u', 'rr$1', $word),
                        preg_replace('/rr([aeiou])/u', 'r$1', $word),
                    ])));

                    $q2->where(function ($q3) use ($variants) {
                        foreach ($variants as $variant) {
                            $q3->orWhereRaw('LOWER(scctcust.NMCUST) LIKE ?', ['%' . $variant . '%']);
                        }
                    });
                }
            });
        });
    }

    private function applyKelasFilter($query, string $kelasId): void
    {
        $kelas = $this->db()
            ->table('mst_kelas')
            ->where('id', (int) $kelasId)
            ->first(['id', 'unit', 'jenjang', 'kelas']);

        if (!$kelas) {
            $query->whereRaw('1 = 0');

            return;
        }

        $unit = trim((string) ($kelas->unit ?? ''));
        $jenjang = trim((string) ($kelas->jenjang ?? ''));
        $kelasNama = trim((string) ($kelas->kelas ?? ''));

        $query->where(function ($q) use ($kelasId, $unit, $jenjang, $kelasNama) {
            $q->whereRaw('TRIM(scctcust.CODE03) = ?', [(string) $kelasId]);
            if ($unit !== '' && $jenjang !== '' && $kelasNama !== '') {
                $q->orWhere(function ($q2) use ($unit, $jenjang, $kelasNama) {
                    $q2->whereRaw('TRIM(scctcust.CODE02) = ?', [$unit])
                        ->whereRaw('TRIM(scctcust.DESC02) = ?', [$jenjang])
                        ->whereRaw('TRIM(scctcust.DESC03) = ?', [$kelasNama]);
                });
            }
        });
    }

    private function fetchRows(array $filters): LengthAwarePaginator
    {
        $hasCashout = $this->resolveCashoutTable() !== null;

        $paginator = $this->baseQuery($filters)
            ->select([
                't.urut',
                't.CUSTID',
                't.TRXDATE',
                't.DEBET',
                't.TRANSNO',
                't.NOREFF',
                't.METODE',
                't.FIDBANK',
                'scctcust.NMCUST as nama',
                'scctcust.NOCUST as nis',
                DB::raw("COALESCE(NULLIF(TRIM(mst_kelas.kelas), ''), NULLIF(TRIM(scctcust.DESC03), ''), NULLIF(TRIM(scctcust.DESC02), ''), NULLIF(TRIM(scctcust.DESC04), ''), '-') as kelas"),
                DB::raw("COALESCE(NULLIF(TRIM(scctcust.CODE04), ''), '-') as gender"),
                DB::raw("COALESCE(NULLIF(TRIM(sk.nama_sekolah), ''), NULLIF(TRIM(scctcust.DESC01), ''), '-') as lokasi"),
                // No Transaksi: TRANSNO sccttran → NOREFF → TRANSNO scctcashout
                DB::raw($hasCashout
                    ? "COALESCE(
                        NULLIF(TRIM(t.TRANSNO), ''),
                        CASE
                            WHEN UPPER(TRIM(COALESCE(t.NOREFF, ''))) = 'EXEBYEXL' THEN NULL
                            ELSE NULLIF(TRIM(t.NOREFF), '')
                        END,
                        NULLIF(TRIM(co.TRANSNO), ''),
                        ''
                    ) as no_transaksi"
                    : "COALESCE(
                        NULLIF(TRIM(t.TRANSNO), ''),
                        CASE
                            WHEN UPPER(TRIM(COALESCE(t.NOREFF, ''))) = 'EXEBYEXL' THEN NULL
                            ELSE NULLIF(TRIM(t.NOREFF), '')
                        END,
                        ''
                    ) as no_transaksi"),
                // User builder = Teller (scctcashout)
                DB::raw($hasCashout
                    ? "COALESCE(NULLIF(TRIM(co.Teller), ''), '-') as user_name"
                    : "'-' as user_name"),
            ])
            ->orderByRaw("COALESCE(NULLIF(scctcust.NOCUST, '-'), scctcust.NUM2ND, '') ASC")
            ->orderBy('t.TRXDATE')
            ->orderBy('t.urut')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $saldoMap = $this->fetchSaldoMap(
            collect($paginator->items())
                ->pluck('CUSTID')
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all()
        );

        return $paginator->through(function ($row) use ($saldoMap) {
            $user = trim((string) ($row->user_name ?? ''));
            $row->user_name = ($user !== '' && $user !== '-') ? $user : '-';
            $row->no_transaksi = trim((string) ($row->no_transaksi ?? '')) ?: '-';
            $row->saldo = $saldoMap[(int) ($row->CUSTID ?? 0)] ?? 0;

            return $row;
        });
    }

    /** @param list<int> $custIds */
    private function fetchSaldoMap(array $custIds): array
    {
        if ($custIds === []) {
            return [];
        }

        $t = $this->tranTable;

        $rows = $this->db()
            ->table($t)
            ->selectRaw('CUSTID, CAST(COALESCE(SUM(KREDIT), 0) - COALESCE(SUM(DEBET), 0) AS SIGNED) as saldo')
            ->whereIn('CUSTID', $custIds)
            ->groupBy('CUSTID')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->CUSTID] = (int) ($row->saldo ?? 0);
        }

        return $map;
    }

    /**
     * @return list<object{value: string, label: string}>
     */
    private function fetchCutoffOptions(): array
    {
        $options = [];

        try {
            $rows = $this->db()->select("SHOW TABLES LIKE 'sccttran\\_%'");
            foreach ($rows as $row) {
                $table = (string) (array_values((array) $row)[0] ?? '');
                if (!preg_match('/^sccttran_(\d{2})(\d{2})(\d{4})$/', $table, $m)) {
                    continue;
                }

                $label = $table;
                try {
                    $label = Carbon::createFromFormat('d-m-Y', $m[1] . '-' . $m[2] . '-' . $m[3])
                        ->locale('en')
                        ->format('d-F-Y');
                } catch (\Throwable) {
                    // keep table name
                }

                $options[] = (object) [
                    'value' => $table,
                    'label' => $label,
                ];
            }
        } catch (\Throwable) {
            // ignore
        }

        // Terbaru dulu (nama tabel ddmmyyyy — sort by date desc)
        usort($options, static function ($a, $b) {
            $da = preg_match('/_(\d{8})$/', $a->value, $ma) ? $ma[1] : '';
            $db = preg_match('/_(\d{8})$/', $b->value, $mb) ? $mb[1] : '';
            // ddmmyyyy → yyyymmdd for compare
            $ra = $da !== '' ? (substr($da, 4, 4) . substr($da, 2, 2) . substr($da, 0, 2)) : '';
            $rb = $db !== '' ? (substr($db, 4, 4) . substr($db, 2, 2) . substr($db, 0, 2)) : '';

            return strcmp($rb, $ra);
        });

        // Hanya arsip cutoff — jangan tampilkan opsi Data Aktif (sccttran)
        return $options;
    }

    private function fetchThnAka(): array
    {
        try {
            return $this->db()
                ->table('mst_thn_aka')
                ->whereNotNull('thn_aka')
                ->where('thn_aka', '!=', '')
                ->orderByDesc('thn_aka')
                ->get(['thn_aka'])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchKelasOptions(): array
    {
        try {
            return $this->db()
                ->table('mst_kelas')
                ->orderBy('unit')
                ->orderBy('jenjang')
                ->orderBy('kelas')
                ->get(['id', 'unit', 'jenjang', 'kelas'])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
