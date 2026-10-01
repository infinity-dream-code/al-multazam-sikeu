<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * CEK Batas Jajan — port builder DATA BATASAN (SETTING ORTU).
 * List dari sm_batasan_child JOIN scctcust (batas_cash, batas_belanja_hari).
 */
class CekBatasJajanController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $filters = [
            'nis' => trim((string) $request->query('nis', '')),
            'nama' => trim((string) $request->query('nama', '')),
            'kelas' => trim((string) $request->query('kelas', '')),
            'tahun_masuk' => trim((string) $request->query('tahun_masuk', '')),
        ];
        $isSearch = $request->boolean('search');

        $rows = $isSearch
            ? $this->fetchRows($filters)
            : new LengthAwarePaginator([], 0, self::PER_PAGE);

        return view('admin.smartcard.cek_batas_jajan.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'CEK Batas Jajan',
            'dataTitle' => 'DATA BATASAN ( SETTING ORTU )',
            'filters' => $filters,
            'isSearch' => $isSearch,
            'rows' => $rows,
        ]);
    }

    /**
     * @param  array{nis:string,nama:string,kelas:string,tahun_masuk:string}  $filters
     */
    private function fetchRows(array $filters): LengthAwarePaginator
    {
        try {
            if (!Schema::connection('DATA_MYSQL')->hasTable('sm_batasan_child')) {
                return new LengthAwarePaginator([], 0, self::PER_PAGE);
            }
        } catch (\Throwable $e) {
            Log::warning('CekBatasJajan schema check failed', ['message' => $e->getMessage()]);

            return new LengthAwarePaginator([], 0, self::PER_PAGE);
        }

        $query = DB::connection('DATA_MYSQL')
            ->table('sm_batasan_child as b')
            ->join('scctcust as c', 'b.CUSTID', '=', 'c.CUSTID')
            ->select([
                'c.CUSTID',
                'c.NOCUST as nis',
                'c.NUM2ND as no_pend',
                'c.NMCUST as nama',
                'c.CODE03 as kelas',
                'c.DESC02 as desc_kelas',
                'c.DESC04 as tahun_masuk',
                'b.batas_belanja_hari',
                'b.batas_cash',
            ]);

        $this->applySchoolScope($query);

        // Builder: (c.NOCUST = :nim OR c.NUM2ND = :nim) — skip jika kosong
        if ($filters['nis'] !== '') {
            $nis = $filters['nis'];
            $query->where(function ($q) use ($nis) {
                $q->whereRaw('TRIM(c.NOCUST) = ?', [$nis])
                    ->orWhereRaw('TRIM(c.NUM2ND) = ?', [$nis])
                    ->orWhere('c.NOCUST', 'like', '%' . $nis . '%')
                    ->orWhere('c.NUM2ND', 'like', '%' . $nis . '%');
            });
        }

        // Builder: c.NMCUST like ::nama  → %nama%
        if ($filters['nama'] !== '') {
            $query->whereRaw('LOWER(c.NMCUST) LIKE ?', ['%' . mb_strtolower($filters['nama']) . '%']);
        }

        // Builder: c.CODE03 = :kelas (DESC02 dikomentari)
        if ($filters['kelas'] !== '') {
            $kelas = $filters['kelas'];
            $query->where(function ($q) use ($kelas) {
                $q->whereRaw('TRIM(CAST(c.CODE03 AS CHAR)) = ?', [$kelas])
                    ->orWhereRaw('TRIM(c.DESC02) = ?', [$kelas]);
            });
        }

        // Builder: c.DESC04 = :tahun_masuk
        if ($filters['tahun_masuk'] !== '') {
            $query->whereRaw('TRIM(c.DESC04) = ?', [$filters['tahun_masuk']]);
        }

        return $query
            ->orderBy('c.NMCUST')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function applySchoolScope($query): void
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit === '') {
            return;
        }

        $query->where(function ($q) use ($unit) {
            $q->whereRaw('TRIM(CAST(c.CODE01 AS CHAR)) = ?', [$unit])
                ->orWhereRaw('TRIM(CAST(c.CODE02 AS CHAR)) = ?', [$unit]);
        });
    }
}
