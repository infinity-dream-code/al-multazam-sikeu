<?php

namespace App\Http\Controllers\Admin\HistoryDataLama;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HistoryPencairanKantinController extends Controller
{
    private const MAX_ROWS = 500;

    public function index(Request $request): View
    {
        $kdMercan = trim((string) $request->query('kd_mercan', ''));
        $dariTanggal = trim((string) $request->query('dari_tanggal', ''));
        $sampaiTanggal = trim((string) $request->query('sampai_tanggal', ''));
        $namaPenerima = trim((string) $request->query('nama_penerima', ''));
        $nominal = trim((string) $request->query('nominal', ''));

        $cariTransaksi = $request->boolean('cari_transaksi');
        $liatPencairan = $request->boolean('liat_pencairan');

        $mercanOptions = $this->fetchMercanOptions();

        $transaksiRows = collect();
        $transaksiTotal = 0;
        if ($cariTransaksi) {
            [$transaksiRows, $transaksiTotal] = $this->fetchTransaksiRows($kdMercan, $dariTanggal, $sampaiTanggal);
        }

        $pencairanRows = collect();
        $pencairanTotal = 0;
        if ($liatPencairan) {
            [$pencairanRows, $pencairanTotal] = $this->fetchPencairanRows(
                $kdMercan,
                $dariTanggal,
                $sampaiTanggal
            );
        }

        return view('admin.history_data_lama.history_pencairan_kantin.index', [
            'title' => 'History Data Lama',
            'mainTitle' => 'History Pencairan Kantin',
            'dataTitle' => 'History Pencairan Kantin',
            'mercanOptions' => $mercanOptions,
            'kdMercan' => $kdMercan,
            'dariTanggal' => $dariTanggal,
            'sampaiTanggal' => $sampaiTanggal,
            'namaPenerima' => $namaPenerima,
            'nominal' => $nominal,
            'cariTransaksi' => $cariTransaksi,
            'liatPencairan' => $liatPencairan,
            'transaksiRows' => $transaksiRows,
            'transaksiTotal' => $transaksiTotal,
            'pencairanRows' => $pencairanRows,
            'pencairanTotal' => $pencairanTotal,
            'previewNoTerima' => $this->generateNoTerima(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kd_mercan' => ['required', 'string', 'max:50'],
            'dari_tanggal' => ['required', 'date'],
            'sampai_tanggal' => ['required', 'date', 'after_or_equal:dari_tanggal'],
            'nama_penerima' => ['required', 'string', 'max:100'],
            'nominal' => ['required', 'integer', 'min:1'],
        ], [
            'kd_mercan.required' => 'Pilih merchant terlebih dahulu.',
            'nama_penerima.required' => 'Nama penerima wajib diisi.',
            'nominal.required' => 'Nominal wajib diisi.',
            'nominal.min' => 'Nominal harus lebih dari 0.',
        ]);

        $kdMercan = trim($validated['kd_mercan']);
        $dari = Carbon::parse($validated['dari_tanggal'])->startOfDay();
        $sampai = Carbon::parse($validated['sampai_tanggal'])->endOfDay();

        $noTerima = $this->generateNoTerima();

        DB::connection('DATA_MYSQL')->table('sm_mercan_cair')->insert([
            'KDMERCAN' => $kdMercan,
            'NamaPenerima' => trim($validated['nama_penerima']),
            'TglTerima' => now()->format('Y-m-d H:i:s'),
            'Nominal' => (int) $validated['nominal'],
            'NoTerima' => $noTerima,
            'dari_tgl_tran' => $dari->format('Y-m-d'),
            'akhir_tgl_tran' => $sampai->format('Y-m-d'),
        ]);

        return redirect()
            ->route('admin.history-data-lama.history-pencairan-kantin.index', [
                'kd_mercan' => $kdMercan,
                'dari_tanggal' => $dari->format('Y-m-d'),
                'sampai_tanggal' => $sampai->format('Y-m-d'),
                'nama_penerima' => trim($validated['nama_penerima']),
                'nominal' => (int) $validated['nominal'],
                'liat_pencairan' => 1,
            ])
            ->with('success', 'Data pencairan berhasil disimpan. No Terima: ' . $noTerima);
    }

    /** Format: YYYYMMDD + urut 3 digit, contoh 20260607001 */
    private function generateNoTerima(): string
    {
        $prefix = now()->format('Ymd');

        $last = DB::connection('DATA_MYSQL')
            ->table('sm_mercan_cair')
            ->whereRaw('TRIM(NoTerima) LIKE ?', [$prefix . '%'])
            ->orderByDesc('NoTerima')
            ->value('NoTerima');

        $seq = 1;
        if ($last !== null && $last !== '') {
            $last = trim((string) $last);
            if (str_starts_with($last, $prefix) && strlen($last) > strlen($prefix)) {
                $tail = substr($last, strlen($prefix));
                if (ctype_digit($tail)) {
                    $seq = (int) $tail + 1;
                }
            }
        }

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

  /**
   * @return list<object{kode: string, nama: string}>
   */
    private function fetchMercanOptions(): array
    {
        try {
            $fromMercan = DB::connection('DATA_MYSQL')
                ->table('sm_mercan')
                ->whereNotNull('KDMERCAN')
                ->where('KDMERCAN', '!=', '')
                ->orderBy('NamaMercan')
                ->get(['KDMERCAN', 'NamaMercan']);

            if ($fromMercan->isNotEmpty()) {
                return $fromMercan->map(static function ($row) {
                    return (object) [
                        'kode' => trim((string) ($row->KDMERCAN ?? '')),
                        'nama' => trim((string) ($row->NamaMercan ?? $row->KDMERCAN ?? '')),
                    ];
                })->filter(static fn ($r) => $r->kode !== '')->values()->all();
            }
        } catch (\Throwable) {
            // fallback ke sm_kantin
        }

        try {
            return DB::connection('DATA_MYSQL')
                ->table('sm_kantin')
                ->whereNotNull('KDMERCAN')
                ->where('KDMERCAN', '!=', '')
                ->orderBy('NamaKantin')
                ->get(['KDMERCAN', 'NamaKantin'])
                ->unique('KDMERCAN')
                ->map(static function ($row) {
                    return (object) [
                        'kode' => trim((string) ($row->KDMERCAN ?? '')),
                        'nama' => trim((string) ($row->NamaKantin ?? $row->KDMERCAN ?? '')),
                    ];
                })
                ->filter(static fn ($r) => $r->kode !== '')
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

  /**
   * @return array{0: Collection<int, object>, 1: float}
   */
    private function fetchTransaksiRows(string $kdMercan, string $dari, string $sampai): array
    {
        $query = DB::connection('DATA_MYSQL')
            ->table('scctcashout')
            ->join('scctcust', 'scctcashout.CUSTID', '=', 'scctcust.CUSTID')
            ->leftJoin('sm_kantin', function ($join) {
                $join->on(DB::raw('TRIM(sm_kantin.username)'), '=', DB::raw('TRIM(scctcashout.Teller)'));
            })
            ->leftJoin('sm_mercan', function ($join) {
                $join->on(DB::raw('TRIM(sm_mercan.KDMERCAN)'), '=', DB::raw('TRIM(sm_kantin.KDMERCAN)'));
            })
            ->whereRaw('UPPER(TRIM(scctcashout.FIDBANK)) = ?', ['BUY']);

        $this->applySchoolScope($query);

        if ($kdMercan !== '') {
            $query->whereRaw('TRIM(sm_kantin.KDMERCAN) = ?', [$kdMercan]);
        }

        $from = $this->parseDate($dari);
        $to = $this->parseDate($sampai);
        if ($from) {
            $query->where('scctcashout.TanggalKeluar', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $query->where('scctcashout.TanggalKeluar', '<=', $to->copy()->endOfDay());
        }

        $rows = $query
            ->select([
                'scctcashout.TanggalKeluar as tgl_transaksi',
                'scctcashout.BILLAM as saldo',
                DB::raw('COALESCE(NULLIF(TRIM(sm_mercan.NamaMercan), \'\'), TRIM(sm_kantin.KDMERCAN), \'-\') as mercan'),
                DB::raw('COALESCE(NULLIF(TRIM(sm_kantin.NamaKantin), \'\'), TRIM(scctcashout.Teller), \'-\') as kantin'),
            ])
            ->orderByDesc('scctcashout.TanggalKeluar')
            ->orderByDesc('scctcashout.urut')
            ->limit(self::MAX_ROWS)
            ->get();

        $total = (float) $rows->sum(static fn ($r) => (float) ($r->saldo ?? 0));

        return [$rows, $total];
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

  /**
   * @return array{0: Collection<int, object>, 1: float}
   */
    private function fetchPencairanRows(string $kdMercan, string $dari, string $sampai): array
    {
        $query = DB::connection('DATA_MYSQL')
            ->table('sm_mercan_cair')
            ->orderByDesc('TglTerima')
            ->orderByDesc('urut');

        if ($kdMercan !== '') {
            $query->whereRaw('TRIM(KDMERCAN) = ?', [$kdMercan]);
        }

        $from = $this->parseDate($dari);
        $to = $this->parseDate($sampai);
        if ($from && $to) {
            $query->where(function ($q) use ($from, $to) {
                $q->where(function ($q2) use ($from, $to) {
                    $q2->where('dari_tgl_tran', '<=', $to->format('Y-m-d'))
                        ->where('akhir_tgl_tran', '>=', $from->format('Y-m-d'));
                })->orWhereBetween('TglTerima', [
                    $from->copy()->startOfDay(),
                    $to->copy()->endOfDay(),
                ]);
            });
        }

        $rows = $query
            ->select([
                'TglTerima as tgl_terima',
                'NamaPenerima as nama_penerima',
                'Nominal as nominal',
                'NoTerima as no_terima',
                'dari_tgl_tran',
                'akhir_tgl_tran',
            ])
            ->limit(self::MAX_ROWS)
            ->get();

        $total = (float) $rows->sum(static fn ($r) => (float) ($r->nominal ?? 0));

        return [$rows, $total];
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
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
