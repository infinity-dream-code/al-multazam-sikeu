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
    private string $tranTable = 'scctran';

    public function index(Request $request): View
    {
        $this->tranTable = $this->resolveTranTable();

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

        $rows = new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        if ($isSearch) {
            try {
                $rows = $this->fetchRows($filters);
            } catch (\Throwable $e) {
                Log::error('HistoryRekapCashout fetchRows failed', [
                    'message' => $e->getMessage(),
                    'table' => $this->tranTable,
                ]);
                report($e);

                return view('admin.history_data_lama.history_rekap_cashout.index', [
                    'title' => $this->title,
                    'mainTitle' => $this->mainTitle,
                    'dataTitle' => 'History Rekap Keluar Uang Saku Data Cutoff',
                    'filters' => $filters,
                    'isSearch' => true,
                    'rows' => $rows,
                    'cutoffOptions' => $this->fetchCutoffOptions(),
                    'thnAka' => $this->fetchThnAka(),
                    'kelasOptions' => $this->fetchKelasOptions(),
                    'errorMessage' => 'Gagal memuat data: ' . $e->getMessage(),
                ]);
            }
        }

        return view('admin.history_data_lama.history_rekap_cashout.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => 'History Rekap Keluar Uang Saku Data Cutoff',
            'filters' => $filters,
            'isSearch' => $isSearch,
            'rows' => $rows,
            'cutoffOptions' => $this->fetchCutoffOptions(),
            'thnAka' => $this->fetchThnAka(),
            'kelasOptions' => $this->fetchKelasOptions(),
        ]);
    }

    private function db()
    {
        return DB::connection('DATA_MYSQL');
    }

    private function resolveTranTable(): string
    {
        // History cashout Al-Multazam: sumber utamanya scctran (data lama)
        try {
            if (Schema::connection('DATA_MYSQL')->hasTable('scctran')) {
                return 'scctran';
            }
        } catch (\Throwable) {
            // ignore
        }

        try {
            if (Schema::connection('DATA_MYSQL')->hasTable('sccttran')) {
                return 'sccttran';
            }
        } catch (\Throwable) {
            // ignore
        }

        return 'scctran';
    }

    private function userColumnExpr(string $alias): string
    {
        $cols = [];
        try {
            $schema = Schema::connection('DATA_MYSQL');
            if ($schema->hasColumn($this->tranTable, 'MERCH')) {
                $cols[] = "NULLIF(TRIM({$alias}.MERCH), '')";
            }
            if ($schema->hasColumn($this->tranTable, 'MERCHANT')) {
                $cols[] = "NULLIF(TRIM({$alias}.MERCHANT), '')";
            }
            if ($schema->hasColumn($this->tranTable, 'HELPDESK')) {
                $cols[] = "NULLIF(TRIM({$alias}.HELPDESK), '')";
            }
        } catch (\Throwable) {
            $cols = [];
        }

        if ($cols === []) {
            return "'-'";
        }

        return 'COALESCE(' . implode(', ', $cols) . ", '-')";
    }

    private function baseQuery(array $filters)
    {
        $t = $this->tranTable;

        $query = $this->db()
            ->table("{$t} as t")
            ->leftJoin('scctcust', 'scctcust.CUSTID', '=', 't.CUSTID')
            ->leftJoin('mst_kelas', DB::raw('CAST(mst_kelas.id AS CHAR)'), '=', DB::raw('TRIM(scctcust.CODE03)'))
            ->leftJoin('mst_sekolah', DB::raw('TRIM(mst_sekolah.CODE01)'), '=', DB::raw('TRIM(scctcust.CODE01)'))
            ->where('t.DEBET', '>', 0)
            ->where(function ($q) {
                $q->whereRaw('UPPER(TRIM(t.FIDBANK)) = ?', ['CASH'])
                    ->orWhere(function ($q2) {
                        $q2->whereRaw('UPPER(TRIM(t.METODE)) LIKE ?', ['%CASHOUT%'])
                            ->where(function ($q3) {
                                $q3->whereNull('t.FIDBANK')
                                    ->orWhereRaw("TRIM(COALESCE(t.FIDBANK, '')) = ''");
                            });
                    });
            });

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
        if ($filters['periode_cutoff'] !== '') {
            $query->whereRaw('TRIM(t.NOREFF) = ?', [$filters['periode_cutoff']]);
        }

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

            // Cocokkan per kata (AND). "farel" juga dicoba sebagai "farrel".
            $q->orWhere(function ($q2) use ($words) {
                foreach ($words as $word) {
                    if (mb_strlen($word) < 2) {
                        continue;
                    }

                    $variants = array_values(array_unique(array_filter([
                        $word,
                        // farel -> farrel (r tunggal di depan vokal jadi rr)
                        preg_replace('/r([aeiou])/u', 'rr$1', $word),
                        // farrel -> farel
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
        $userExpr = $this->userColumnExpr('t');

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
                DB::raw("COALESCE(NULLIF(TRIM(mst_sekolah.DESC01), ''), NULLIF(TRIM(scctcust.CODE01), ''), '-') as lokasi"),
                DB::raw("COALESCE(NULLIF(TRIM(t.TRANSNO), ''), '') as no_transaksi"),
                DB::raw("{$userExpr} as user_name"),
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
            if ($user !== '' && $user !== '-' && preg_match('/User:\s*([^\s|]+)/i', $user, $m)) {
                $user = trim($m[1]);
            }
            $row->user_name = $user !== '' ? $user : '-';
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

    private function fetchCutoffOptions(): array
    {
        try {
            $t = $this->tranTable;

            return $this->db()
                ->table($t)
                ->where('DEBET', '>', 0)
                ->where(function ($q) {
                    $q->whereRaw('UPPER(TRIM(FIDBANK)) = ?', ['CASH'])
                        ->orWhere(function ($q2) {
                            $q2->whereRaw('UPPER(TRIM(METODE)) LIKE ?', ['%CASHOUT%'])
                                ->where(function ($q3) {
                                    $q3->whereNull('FIDBANK')
                                        ->orWhereRaw("TRIM(COALESCE(FIDBANK, '')) = ''");
                                });
                        });
                })
                ->whereNotNull('NOREFF')
                ->whereRaw("TRIM(NOREFF) != ''")
                ->distinct()
                ->orderByDesc('NOREFF')
                ->limit(200)
                ->pluck('NOREFF')
                ->map(static fn ($v) => trim((string) $v))
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
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
