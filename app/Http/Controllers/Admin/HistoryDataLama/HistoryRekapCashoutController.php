<?php

namespace App\Http\Controllers\Admin\HistoryDataLama;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HistoryRekapCashoutController extends Controller
{
    private const PER_PAGE = 25;

    private string $title = 'History Data Lama';
    private string $mainTitle = 'History Rekap Cashout';

    public function index(Request $request): View
    {
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

        $rows = $isSearch
            ? $this->fetchRows($filters)
            : new LengthAwarePaginator([], 0, self::PER_PAGE, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

        return view('admin.history_data_lama.history_rekap_cashout.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => 'History Rekap Keluar Uang Saku',
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

    private function baseQuery(array $filters)
    {
        $query = $this->db()
            ->table('scctran')
            ->leftJoin('scctcust', 'scctcust.CUSTID', '=', 'scctran.CUSTID')
            ->where('scctran.DEBET', '>', 0)
            ->where(function ($q) {
                // FIDBANK = CASH + DEBET > 0
                $q->whereRaw('UPPER(TRIM(scctran.FIDBANK)) = ?', ['CASH'])
                    // atau METODE CASHOUT tanpa FIDBANK
                    ->orWhere(function ($q2) {
                        $q2->whereRaw('UPPER(TRIM(scctran.METODE)) LIKE ?', ['%CASHOUT%'])
                            ->where(function ($q3) {
                                $q3->whereNull('scctran.FIDBANK')
                                    ->orWhereRaw("TRIM(COALESCE(scctran.FIDBANK, '')) = ''");
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
            $query->whereRaw('TRIM(scctran.NOREFF) = ?', [$filters['periode_cutoff']]);
        }

        if ($filters['nis'] !== '') {
            $query->where(function ($q) use ($filters) {
                $q->where('scctcust.NOCUST', 'like', '%' . $filters['nis'] . '%')
                    ->orWhere('scctcust.NUM2ND', 'like', '%' . $filters['nis'] . '%');
            });
        }

        if ($filters['nama'] !== '') {
            $query->where('scctcust.NMCUST', 'like', '%' . $filters['nama'] . '%');
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
                $query->where('scctran.TRXDATE', '>=', $from->startOfDay());
            }
        }

        if ($filters['sampai_tanggal'] !== '') {
            $to = $this->parseDate($filters['sampai_tanggal']);
            if ($to) {
                $query->where('scctran.TRXDATE', '<=', $to->endOfDay());
            }
        }
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
        $saldoSub = '(SELECT COALESCE(SUM(s2.KREDIT), 0) - COALESCE(SUM(s2.DEBET), 0)
            FROM scctran s2
            WHERE s2.CUSTID = scctran.CUSTID
              AND (
                    s2.TRXDATE < scctran.TRXDATE
                    OR (s2.TRXDATE = scctran.TRXDATE AND s2.urut <= scctran.urut)
              )
        )';

        return $this->baseQuery($filters)
            ->select([
                'scctran.urut',
                'scctran.CUSTID',
                'scctran.TRXDATE',
                'scctran.DEBET',
                'scctran.TRANSNO',
                'scctran.NOREFF',
                'scctran.METODE',
                'scctran.FIDBANK',
                'scctcust.NMCUST as nama',
                'scctcust.NOCUST as nis',
                DB::raw("COALESCE(NULLIF(TRIM(scctcust.DESC03), ''), NULLIF(TRIM(scctcust.DESC02), ''), NULLIF(TRIM(scctcust.DESC04), ''), '-') as kelas"),
                DB::raw("COALESCE(NULLIF(TRIM(scctcust.CODE04), ''), '-') as gender"),
                DB::raw("COALESCE(NULLIF(TRIM(scctcust.CODE01), ''), '-') as lokasi"),
                DB::raw("COALESCE(NULLIF(TRIM(scctran.TRANSNO), ''), NULLIF(TRIM(scctran.NOREFF), ''), '-') as no_transaksi"),
                DB::raw("{$saldoSub} as saldo"),
            ])
            ->orderByRaw("COALESCE(NULLIF(scctcust.NOCUST, '-'), scctcust.NUM2ND, '') ASC")
            ->orderBy('scctran.TRXDATE')
            ->orderBy('scctran.urut')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function fetchCutoffOptions(): array
    {
        try {
            return $this->db()
                ->table('scctran')
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
