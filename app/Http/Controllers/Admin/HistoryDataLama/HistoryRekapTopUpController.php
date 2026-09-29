<?php

namespace App\Http\Controllers\Admin\HistoryDataLama;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HistoryRekapTopUpController extends Controller
{
    private const PER_PAGE = 10;

    private const CASH_FEE = 2000;

    private const TRAN_TABLE = 'sccttran_cashless';

    private const METODE_TOPUP = 'TOP UP CASH';

    /** Max 15 char (kolom METODE sccttran_cashless) */
    private const METODE_FEE = 'BIAYA TOPUP FEE';

    private const FIDBANK = '1140002';

    public function index(Request $request): View
    {
        $isSearch = $request->boolean('search');
        $filters = $this->filtersFromRequest($request);

        $thnAka = $this->fetchThnAka();
        $kelasOptions = $this->fetchKelasOptions();

        $rows = new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
        $totals = ['topup' => 0, 'fee' => 0, 'grand' => 0];

        if ($isSearch) {
            $rows = $this->fetchRows($filters, $request);
            $totals = $this->sumPageTotals($rows);
        }

        return view('admin.history_data_lama.history_rekap_top_up.index', [
            'title' => 'History Data Lama',
            'mainTitle' => 'History Rekap Top Up',
            'dataTitle' => 'History Rekap Top Up',
            'filters' => $filters,
            'isSearch' => $isSearch,
            'rows' => $rows,
            'totals' => $totals,
            'thnAka' => $thnAka,
            'kelasOptions' => $kelasOptions,
        ]);
    }

    public function printRekap(Request $request): Response|RedirectResponse
    {
        $filters = $this->filtersFromRequest($request);

        $totalCount = (int) $this->baseQuery($filters)->count('t.CUSTID');
        if ($totalCount <= 0) {
            return redirect()
                ->route('admin.history-data-lama.history-rekap-top-up.index', array_merge($filters, ['search' => 1]))
                ->with('error', 'Tidak ada data rekap untuk dicetak.');
        }

        $totals = $this->sumTotalsSql($filters);
        $sekolahNama = $this->fetchSekolahNama();

        $pdf = Pdf::loadView('admin.history_data_lama.history_rekap_top_up.rekap-pdf', [
            'sekolahNama' => $sekolahNama,
            'filters' => $filters,
            'rows' => $this->fetchAllRowsForPrint($filters),
            'totals' => $totals,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('rekap-topup-uang-saku-' . date('Ymd-His') . '.pdf');
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'thn_angkatan' => trim((string) $request->input('thn_angkatan', $request->query('thn_angkatan', ''))),
            'kelas_id' => trim((string) $request->input('kelas_id', $request->query('kelas_id', ''))),
            'nis' => trim((string) $request->input('nis', $request->query('nis', ''))),
            'nama' => trim((string) $request->input('nama', $request->query('nama', ''))),
            'dari_tanggal' => trim((string) $request->input('dari_tanggal', $request->query('dari_tanggal', ''))),
            'sampai_tanggal' => trim((string) $request->input('sampai_tanggal', $request->query('sampai_tanggal', ''))),
        ];
    }

    private function baseQuery(array $filters)
    {
        $query = DB::connection('DATA_MYSQL')
            ->table(self::TRAN_TABLE . ' as t')
            ->join('scctcust', 't.CUSTID', '=', 'scctcust.CUSTID')
            ->leftJoin('mst_kelas', DB::raw('CAST(mst_kelas.id AS CHAR)'), '=', DB::raw('TRIM(scctcust.CODE03)'))
            ->leftJoin(self::TRAN_TABLE . ' as fee', function ($join) {
                $join->on('fee.TRANSNO', '=', 't.TRANSNO')
                    ->on('fee.CUSTID', '=', 't.CUSTID')
                    ->where(function ($q) {
                        $q->whereRaw('UPPER(TRIM(fee.METODE)) = ?', [self::METODE_FEE])
                            ->orWhereRaw('UPPER(TRIM(fee.METODE)) = ?', ['ADMIN FEE'])
                            ->orWhereRaw('UPPER(TRIM(fee.METODE)) = ?', ['BIAYA ADMIN TOPUP']);
                    });
            })
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereRaw('UPPER(TRIM(t.METODE)) = ?', [self::METODE_TOPUP])
                        ->whereRaw('TRIM(t.FIDBANK) = ?', [self::FIDBANK]);
                })->orWhere(function ($q2) {
                    $q2->whereRaw('UPPER(TRIM(t.METODE)) = ?', ['TOP UP CASHLESS'])
                        ->whereRaw('TRIM(t.FIDBANK) = ?', [self::FIDBANK]);
                })->orWhere(function ($q2) {
                    $q2->whereRaw('UPPER(TRIM(t.FIDBANK)) = ?', ['TOPUP'])
                        ->where('t.KREDIT', '>', 0);
                });
            })
            ->where('t.KREDIT', '>', 0);

        $this->applySchoolScope($query);
        $this->applyFilters($query, $filters);

        return $query;
    }

    private function selectColumns(): array
    {
        return [
            'scctcust.NOCUST as nis',
            'scctcust.NMCUST as nama',
            't.KREDIT as topup',
            't.TRXDATE as tgl_transaksi',
            DB::raw('COALESCE(NULLIF(TRIM(t.TRANSNO), \'\'), NULLIF(TRIM(t.NOREFF), \'\'), \'-\') as no_transaksi'),
            't.HELPDESK as helpdesk',
            't.METODE as metode',
            DB::raw('CAST(COALESCE(fee.DEBET, 0) AS SIGNED) as fee_debet'),
            DB::raw('COALESCE(NULLIF(TRIM(mst_kelas.jenjang), \'\'), TRIM(scctcust.DESC02), \'-\') as kelas'),
            DB::raw('COALESCE(NULLIF(TRIM(mst_kelas.kelas), \'\'), TRIM(scctcust.DESC03), \'-\') as kelompok'),
            DB::raw('COALESCE(NULLIF(TRIM(scctcust.CODE04), \'\'), \'-\') as gender'),
        ];
    }

    private function fetchRows(array $filters, Request $request): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->select($this->selectColumns())
            ->orderByDesc('t.TRXDATE')
            ->orderByDesc('t.urut')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn ($row) => $this->mapRow($row));
    }

    private function mapRow(object $row): object
    {
        $topupGross = (int) ($row->topup ?? 0);
        $fee = (int) ($row->fee_debet ?? 0);
        if ($fee <= 0) {
            $fee = $this->parseFee((string) ($row->helpdesk ?? ''), (string) ($row->metode ?? ''));
        }
        $row->topup = $topupGross;
        $row->fee = $fee;
        $row->total = max(0, $topupGross - $fee);
        $row->user = $this->parseUser((string) ($row->helpdesk ?? ''));

        return $row;
    }

    /** @return array{topup: int, fee: int, grand: int} */
    private function sumPageTotals(LengthAwarePaginator $paginator): array
    {
        $topup = 0;
        $fee = 0;
        foreach ($paginator->items() as $row) {
            $topup += (int) ($row->topup ?? 0);
            $fee += (int) ($row->fee ?? 0);
        }

        return [
            'topup' => $topup,
            'fee' => $fee,
            'grand' => max(0, $topup - $fee),
        ];
    }

    /** Agregat SQL — dipakai cetak PDF, tanpa load semua baris ke PHP. */
    private function sumTotalsSql(array $filters): array
    {
        $cashFee = self::CASH_FEE;
        $metodeTopup = self::METODE_TOPUP;
        $row = $this->baseQuery($filters)
            ->selectRaw(
                'CAST(COALESCE(SUM(t.KREDIT), 0) AS SIGNED) as topup_sum,
                CAST(COALESCE(SUM(
                    CASE
                        WHEN COALESCE(fee.DEBET, 0) > 0 THEN CAST(fee.DEBET AS SIGNED)
                        WHEN t.HELPDESK LIKE ? THEN
                            CAST(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(t.HELPDESK, \'Biaya:\', -1), \'|\', 1)) AS SIGNED)
                        WHEN UPPER(TRIM(t.METODE)) IN (\'CASH\', ?, \'TOP UP CASHLESS\') THEN ?
                        ELSE 0
                    END
                ), 0) AS SIGNED) as fee_sum',
                ['%Biaya:%', $metodeTopup, $cashFee]
            )
            ->first();

        $topup = (int) ($row->topup_sum ?? 0);
        $fee = (int) ($row->fee_sum ?? 0);

        return [
            'topup' => $topup,
            'fee' => $fee,
            'grand' => max(0, $topup - $fee),
        ];
    }

    private function fetchAllRowsForPrint(array $filters): \Illuminate\Support\Collection
    {
        return $this->baseQuery($filters)
            ->select($this->selectColumns())
            ->orderByDesc('t.TRXDATE')
            ->orderByDesc('t.urut')
            ->get()
            ->map(fn ($row) => $this->mapRow($row));
    }

    private function parseFee(string $helpdesk, string $metode): int
    {
        if (preg_match('/Biaya:\s*(\d+)/i', $helpdesk, $m)) {
            return (int) $m[1];
        }

        return strcasecmp(trim($metode), 'Cash') === 0
            || strcasecmp(trim($metode), self::METODE_TOPUP) === 0
            || strcasecmp(trim($metode), 'TOP UP CASHLESS') === 0
            ? self::CASH_FEE
            : 0;
    }

    private function parseUser(string $helpdesk): string
    {
        if (preg_match('/User:\s*([^\s|]+)/i', $helpdesk, $m)) {
            $user = trim($m[1]);
            if ($user !== '') {
                return $user;
            }
        }

        return '-';
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
        if ($filters['nis'] !== '') {
            $query->where('scctcust.NOCUST', 'like', '%' . $filters['nis'] . '%');
        }

        if ($filters['nama'] !== '') {
            $query->where('scctcust.NMCUST', 'like', '%' . $filters['nama'] . '%');
        }

        if ($filters['thn_angkatan'] !== '') {
            $this->applyAngkatanLike($query, $filters['thn_angkatan']);
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

        if ($filters['kelas_id'] !== '') {
            $this->applyKelasFilter($query, $filters['kelas_id']);
        }
    }

    private function applyAngkatanLike($query, string $value): void
    {
        $full = trim($value);
        $base = trim((string) preg_replace('#\s*-\s*.*$#', '', $full));
        $query->where(function ($q) use ($full, $base) {
            $q->whereRaw('TRIM(scctcust.DESC04) = ?', [$full]);
            if ($base !== '' && $base !== $full) {
                $q->orWhereRaw('TRIM(scctcust.DESC04) = ?', [$base])
                    ->orWhereRaw('REPLACE(TRIM(scctcust.DESC04), \' \', \'\') LIKE ?', [str_replace(' ', '', $base) . '%']);
            }
        });
    }

    private function applyKelasFilter($query, string $kelasId): void
    {
        $kelas = DB::connection('DATA_MYSQL')
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

    /** @return list<object{thn_aka: string}> */
    private function fetchThnAka(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
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

    /** @return list<object> */
    private function fetchKelasOptions(): array
    {
        try {
            return DB::connection('DATA_MYSQL')
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

    private function fetchSekolahNama(): string
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit !== '') {
            try {
                $nama = DB::connection('DATA_MYSQL')
                    ->table('mst_sekolah')
                    ->where(function ($q) use ($unit) {
                        $q->whereRaw('TRIM(CODE01) = ?', [$unit])
                            ->orWhereRaw('TRIM(DESC01) = ?', [$unit]);
                    })
                    ->value('DESC01');

                if ($nama !== null && trim((string) $nama) !== '') {
                    return trim((string) $nama);
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return 'Al-Multazam';
    }
}
