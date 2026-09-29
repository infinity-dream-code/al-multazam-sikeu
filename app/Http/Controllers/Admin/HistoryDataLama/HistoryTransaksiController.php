<?php

namespace App\Http\Controllers\Admin\HistoryDataLama;

use App\Http\Controllers\Controller;
use App\Models\scctcust;
use App\Models\sccttran;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HistoryTransaksiController extends Controller
{
    private string $title = 'History Data Lama';
    private string $mainTitle = 'History Transaksi';
    private string $vaPrefix = '752038';

    public function index(Request $request)
    {
        $dariTanggal = $request->input('dari_tanggal');
        $sampaiTanggal = $request->input('sampai_tanggal');
        $cari = trim((string) $request->input('cari', ''));

        $siswaQuery = sccttran::query()
            ->leftJoin('scctcust', 'scctcust.CUSTID', '=', 'sccttran.CUSTID')
            ->select([
                'sccttran.CUSTID',
                DB::raw("MAX(COALESCE(NULLIF(scctcust.NOCUST, '-'), scctcust.NUM2ND, '')) as nis"),
                DB::raw('MAX(scctcust.NMCUST) as nama'),
            ])
            ->groupBy('sccttran.CUSTID');

        $this->applyFilters($siswaQuery, $dariTanggal, $sampaiTanggal, $cari);

        $siswaPaginated = $siswaQuery
            ->orderByRaw("MAX(COALESCE(NULLIF(scctcust.NOCUST, '-'), scctcust.NUM2ND, '')) ASC")
            ->paginate(20)
            ->withQueryString();

        $custIds = $siswaPaginated->getCollection()->pluck('CUSTID')->filter()->values();

        $transaksiByCust = collect();
        if ($custIds->isNotEmpty()) {
            $trxQuery = sccttran::query()
                ->leftJoin('scctcust', 'scctcust.CUSTID', '=', 'sccttran.CUSTID')
                ->whereIn('sccttran.CUSTID', $custIds)
                ->select([
                    'sccttran.urut',
                    'sccttran.CUSTID',
                    'sccttran.TRXDATE',
                    'sccttran.DEBET',
                    'sccttran.KREDIT',
                    'sccttran.FIDBANK',
                    'sccttran.METODE',
                    'sccttran.NOREFF',
                    'scctcust.NOCUST',
                    'scctcust.NUM2ND',
                    'scctcust.NMCUST',
                ]);

            $this->applyFilters($trxQuery, $dariTanggal, $sampaiTanggal, $cari);

            $transaksiByCust = $trxQuery
                ->orderByRaw("COALESCE(NULLIF(scctcust.NOCUST, '-'), scctcust.NUM2ND, '') ASC")
                ->orderBy('sccttran.TRXDATE')
                ->orderBy('sccttran.urut')
                ->get()
                ->groupBy('CUSTID');
        }

        $groups = $siswaPaginated->getCollection()->map(function ($siswa) use ($transaksiByCust) {
            $nis = $siswa->nis ?: '-';
            $rows = ($transaksiByCust->get($siswa->CUSTID) ?? collect())->map(function ($item) {
                return (object) [
                    'tanggal' => $item->TRXDATE
                        ? Carbon::parse($item->TRXDATE)->format('Y-m-d H:i:s')
                        : '-',
                    'debet' => (int) ($item->DEBET ?? 0),
                    'kredit' => (int) ($item->KREDIT ?? 0),
                    'remark' => $item->METODE ?: '-',
                    'fidbank' => $item->FIDBANK ?: '-',
                    'keterangan' => $item->NOREFF ?: '-',
                ];
            })->values();

            return (object) [
                'nis' => $nis,
                'vano' => ($nis && $nis !== '-') ? scctcust::formatVA($this->vaPrefix, $nis) : '-',
                'nama' => $siswa->nama ?: '-',
                'rows' => $rows,
                'rowspan' => max($rows->count(), 1),
            ];
        });

        $siswaPaginated->setCollection($groups);

        return view('admin.history_data_lama.history_transaksi.index', [
            'title' => $this->title,
            'mainTitle' => $this->mainTitle,
            'dataTitle' => $this->mainTitle,
            'groups' => $siswaPaginated,
            'dari_tanggal' => $dariTanggal,
            'sampai_tanggal' => $sampaiTanggal,
            'cari' => $cari,
        ]);
    }

    private function applyFilters($query, ?string $dariTanggal, ?string $sampaiTanggal, string $cari): void
    {
        if ($dariTanggal && preg_match('/^\d{2}-\d{2}-\d{4}$/', $dariTanggal)) {
            $query->where(
                'sccttran.TRXDATE',
                '>=',
                Carbon::createFromFormat('d-m-Y', $dariTanggal)->startOfDay()
            );
        }

        if ($sampaiTanggal && preg_match('/^\d{2}-\d{2}-\d{4}$/', $sampaiTanggal)) {
            $query->where(
                'sccttran.TRXDATE',
                '<=',
                Carbon::createFromFormat('d-m-Y', $sampaiTanggal)->endOfDay()
            );
        }

        if ($cari !== '') {
            $query->where(function ($q) use ($cari) {
                $q->where('scctcust.NOCUST', 'like', '%' . $cari . '%')
                    ->orWhere('scctcust.NMCUST', 'like', '%' . $cari . '%')
                    ->orWhere('scctcust.NUM2ND', 'like', '%' . $cari . '%');
            });
        }
    }
}
