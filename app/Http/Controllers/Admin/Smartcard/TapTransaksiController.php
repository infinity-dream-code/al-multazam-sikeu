<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * TAP Ritel / Laundry / Perpus — port dari builder MyBuilder.
 *
 * Ritel/Belanja (BATAS ON): sccttran + sccttran_merchant + scctcashout,
 *   FIDBANK=BUY, FROM SALDO_CLN, batas dari sm_batasan_child lalu sm_batasan (batas_belanja_hari),
 *   lolos jika batas >= ambil + totalharian
 * Perpus: FIDBANK=PERPUS, batas_perpus, lolos jika batas > ambil + totalharian
 * Laundry: pola perpus, FIDBANK=LAUNDRY
 */
class TapTransaksiController extends Controller
{
    /** @var array<string, array<string, mixed>> */
    private const TYPES = [
        'ritel' => [
            'title' => 'TAP RITEL',
            'subtitle' => 'Pengeluaran Transaksi Belanja / Ritel',
            'ambil_label' => 'BELANJA',
            'fidbank' => 'BUY',
            'metode_tran' => 'FROM SALDO_CLN',
            'metode_merchant' => 'TOP UP',
            'reffbank' => '38',
            'use_merchant' => true,
            'use_batas' => true,
            'batas_column' => 'batas_belanja_hari',
            'batas_from_child' => true, // sm_batasan_child dulu, fallback sm_batasan
            'batas_inclusive' => true,  // builder: batas >= (ambil + totalharian)
            'error_empty' => 'Kartu Tidak Terdaftar Atau Kartu Terblokir / ERROR NOMINAL ',
        ],
        'laundry' => [
            'title' => 'TAP LAUNDRY',
            'subtitle' => 'Pengeluaran Transaksi Laundry',
            'ambil_label' => 'LAUNDRY',
            'fidbank' => 'LAUNDRY',
            'metode_tran' => 'FROM SALDO_CLN',
            'metode_merchant' => 'TOP UP',
            'reffbank' => '38',
            'use_merchant' => true,
            'use_batas' => true,
            'batas_column' => 'batas_laundry',
            'batas_from_child' => false,
            'batas_inclusive' => false, // builder perpus: batas > (ambil + totalharian)
            'error_empty' => 'ERORR Proses TAP',
        ],
        'perpus' => [
            'title' => 'TAP PERPUS',
            'subtitle' => 'Pengeluaran Transaksi Perpus',
            'ambil_label' => 'PERPUS',
            'fidbank' => 'PERPUS',
            'metode_tran' => 'FROM SALDO_CLN',
            'metode_merchant' => 'TOP UP',
            'reffbank' => '38',
            'use_merchant' => true,
            'use_batas' => true,
            'batas_column' => 'batas_perpus',
            'batas_from_child' => false,
            'batas_inclusive' => false,
            'error_empty' => 'ERORR Proses TAP',
        ],
    ];

    public function index(string $type): View
    {
        $cfg = $this->config($type);

        return view('admin.smartcard.tap_transaksi.index', [
            'title' => 'smartCARD',
            'mainTitle' => $cfg['title'],
            'dataTitle' => $cfg['subtitle'],
            'type' => $type,
            'cfg' => $cfg,
            'lookupUrl' => route('admin.smartcard.tap-' . $type . '.lookup'),
            'processUrl' => route('admin.smartcard.tap-' . $type . '.process'),
        ]);
    }

    public function lookup(Request $request, string $type): JsonResponse
    {
        $this->config($type);

        $tapId = trim((string) $request->input('tap_id', ''));
        if ($tapId === '') {
            return $this->fail('TAP ID wajib diisi.');
        }

        $card = $this->fetchCard($tapId);
        if (!$card) {
            return $this->fail('Kartu Tidak Terdaftar Atau Kartu Terblokir', 404);
        }
        if ((int) ($card->blokir ?? 0) === 1) {
            return $this->fail('Kartu Tidak Terdaftar Atau Kartu Terblokir', 403);
        }

        $custid = (int) ($card->custid ?? 0);
        if (!$this->siswaInScope($custid)) {
            return $this->fail('Kartu tidak termasuk unit sekolah ini.', 403);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'tap_id' => $tapId,
                'custid' => $custid,
                'nama' => trim((string) ($card->nama ?? '')),
                'nis' => trim((string) ($card->nis ?? '')),
                'saldo' => $this->fetchSaldo($custid),
            ],
        ]);
    }

    public function process(Request $request, string $type): JsonResponse
    {
        $cfg = $this->config($type);

        $tapId = trim((string) $request->input('tap_id', ''));
        $custidInput = (int) $request->input('custid', 0);
        $ambil = $this->parseAmount($request->input('ambil', ''));

        if ($tapId === '' || $custidInput <= 0 || $ambil <= 0) {
            return $this->fail((string) ($cfg['error_empty'] ?? 'ERROR Proses TAP'));
        }

        $card = $this->fetchCard($tapId);
        if (!$card) {
            return $this->fail((string) ($cfg['error_empty'] ?? 'Kartu Tidak Terdaftar Atau Kartu Terblokir'), 404);
        }
        if ((int) ($card->blokir ?? 0) === 1) {
            return $this->fail((string) ($cfg['error_empty'] ?? 'Kartu Tidak Terdaftar Atau Kartu Terblokir'), 403);
        }

        $custid = (int) ($card->custid ?? 0);
        if ($custid !== $custidInput || $custid <= 0) {
            return $this->fail((string) ($cfg['error_empty'] ?? 'ERROR Proses TAP'));
        }
        if (!$this->siswaInScope($custid)) {
            return $this->fail('Kartu tidak termasuk unit sekolah ini.', 403);
        }

        $saldo = $this->fetchSaldo($custid);
        if ($ambil > $saldo || $ambil < 0) {
            return $this->fail('Saldo tidak cukup');
        }

        if ($cfg['use_batas']) {
            $batas = $this->fetchBatasForCust(
                $custid,
                (string) ($cfg['batas_column'] ?? ''),
                (bool) ($cfg['batas_from_child'] ?? false)
            );
            $totalHarian = $this->sumDebetHariIni($custid, (string) $cfg['fidbank']);
            $pakai = $ambil + $totalHarian;
            // Ritel builder: batas >= pakai  |  Perpus builder: batas > pakai
            $okBatas = !empty($cfg['batas_inclusive'])
                ? ($batas >= $pakai)
                : ($batas > $pakai);
            if (!$okBatas) {
                return $this->fail('Melebihi Batas Harian');
            }
        }

        $trxDate = now();
        $transNo = $this->generateTransNo($trxDate);
        $teller = $this->currentUserLabel();
        $fidbank = (string) $cfg['fidbank'];

        try {
            DB::connection('DATA_MYSQL')->transaction(function () use (
                $cfg,
                $custid,
                $ambil,
                $transNo,
                $trxDate,
                $teller,
                $fidbank
            ) {
                $tranRow = [
                    'CUSTID' => $custid,
                    'NOREFF' => $transNo,
                    'TRXDATE' => $trxDate->format('Y-m-d H:i:s'),
                    'KDCHANNEL' => 11,
                    'DEBET' => $ambil,
                    'KREDIT' => 0,
                    'METODE' => $cfg['metode_tran'],
                    'FIDBANK' => $fidbank,
                    'TRANSNO' => $transNo,
                ];

                if ($cfg['reffbank'] !== null) {
                    $tranRow['REFFBANK'] = $cfg['reffbank'];
                }

                if ($this->hasColumn('sccttran', 'MERCHANT')) {
                    $tranRow['MERCHANT'] = $teller;
                }

                DB::connection('DATA_MYSQL')->table('sccttran')->insert($tranRow);

                if ($cfg['use_merchant'] && Schema::connection('DATA_MYSQL')->hasTable('sccttran_merchant')) {
                    $mercRow = [
                        'CUSTID' => $custid,
                        'NOREFF' => $transNo,
                        'TRXDATE' => $trxDate->format('Y-m-d H:i:s'),
                        'KDCHANNEL' => 11,
                        'KREDIT' => $ambil,
                        'DEBET' => 0,
                        'METODE' => $cfg['metode_merchant'] ?? 'TOP UP',
                        'FIDBANK' => $fidbank,
                        'TELLER' => $teller,
                    ];
                    if ($cfg['reffbank'] !== null) {
                        $mercRow['REFFBANK'] = $cfg['reffbank'];
                    }
                    DB::connection('DATA_MYSQL')->table('sccttran_merchant')->insert($mercRow);
                }

                DB::connection('DATA_MYSQL')->table('scctcashout')->insert([
                    'CUSTID' => $custid,
                    'BILLAM' => $ambil,
                    'TanggalKeluar' => $trxDate->format('Y-m-d H:i:s'),
                    'Teller' => $teller,
                    'TRANSNO' => $transNo,
                    'FIDBANK' => $fidbank,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('TAP process failed', [
                'type' => $type,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return $this->fail('Gagal menyimpan transaksi: ' . $e->getMessage(), 500);
        }

        $saldoBaru = $this->fetchSaldo($custid);

        return response()->json([
            'ok' => true,
            'message' => 'Sukses Belanja',
            'data' => [
                'trans_no' => $transNo,
                'ambil' => $ambil,
                'saldo_baru' => $saldoBaru,
                'nama' => trim((string) ($card->nama ?? '')),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function config(string $type): array
    {
        if (!isset(self::TYPES[$type])) {
            abort(404);
        }

        return self::TYPES[$type];
    }

    private function fetchCard(string $tapId): ?object
    {
        return DB::connection('DATA_MYSQL')
            ->table('sm_pin')
            ->join('scctcust', 'sm_pin.CUSTID', '=', 'scctcust.CUSTID')
            ->where('sm_pin.PID', $tapId)
            ->first([
                'sm_pin.CUSTID as custid',
                'sm_pin.PIN as pin',
                'sm_pin.BLOKIR as blokir',
                'scctcust.NMCUST as nama',
                'scctcust.NOCUST as nis',
            ]);
    }

    private function fetchSaldo(int $custid): int
    {
        try {
            $fromView = DB::connection('DATA_MYSQL')
                ->table('v_saldo_va')
                ->where('CUSTID', $custid)
                ->value('SALDO');
            if ($fromView !== null) {
                return (int) $fromView;
            }
        } catch (\Throwable) {
            // fallback sum sccttran
        }

        $row = DB::connection('DATA_MYSQL')
            ->table('sccttran')
            ->where('CUSTID', $custid)
            ->selectRaw('CAST(COALESCE(SUM(KREDIT), 0) AS SIGNED) - CAST(COALESCE(SUM(DEBET), 0) AS SIGNED) AS saldo')
            ->first();

        return (int) ($row->saldo ?? 0);
    }

    /**
     * Batas harian.
     * Ritel: sm_batasan_child (per CUSTID) dulu; jika 0/kosong → sm_batasan.
     * Lainnya: langsung sm_batasan.[column]
     */
    private function fetchBatasForCust(int $custid, string $column, bool $fromChild): int
    {
        if ($column === '') {
            return PHP_INT_MAX;
        }

        if ($fromChild && Schema::connection('DATA_MYSQL')->hasTable('sm_batasan_child')) {
            try {
                $childCols = ['batas_belanja_hari'];
                if ($this->hasColumn('sm_batasan_child', $column)) {
                    $childCols = [$column];
                }
                $child = DB::connection('DATA_MYSQL')
                    ->table('sm_batasan_child')
                    ->where('aktif', 1)
                    ->where('CUSTID', $custid)
                    ->first($childCols);

                if ($child) {
                    $val = (int) ($child->{$childCols[0]} ?? 0);
                    if ($val > 0) {
                        return $val;
                    }
                }
            } catch (\Throwable) {
                // fallback sm_batasan
            }
        }

        return $this->fetchBatasGlobal($column);
    }

    private function fetchBatasGlobal(string $column): int
    {
        if ($column === '' || !$this->hasColumn('sm_batasan', $column)) {
            return PHP_INT_MAX;
        }

        try {
            $row = DB::connection('DATA_MYSQL')
                ->table('sm_batasan')
                ->where('aktif', 1)
                ->orderByDesc('periode')
                ->first([$column]);

            return (int) ($row->{$column} ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function sumDebetHariIni(int $custid, string $fidbank): int
    {
        $row = DB::connection('DATA_MYSQL')
            ->table('sccttran')
            ->where('CUSTID', $custid)
            ->whereRaw('UPPER(TRIM(FIDBANK)) = ?', [strtoupper($fidbank)])
            ->whereDate('TRXDATE', now()->toDateString())
            ->selectRaw('CAST(COALESCE(SUM(DEBET), 0) AS SIGNED) as tot')
            ->first();

        return (int) ($row->tot ?? 0);
    }

    /** Builder: YYYYMMDD + pad(count+1, 5) dari scctcashout hari ini */
    private function generateTransNo(Carbon $trxDate): string
    {
        $prefix = $trxDate->format('Ymd');

        $count = (int) DB::connection('DATA_MYSQL')
            ->table('scctcashout')
            ->whereRaw('SUBSTRING(TRIM(TRANSNO), 1, 8) = ?', [$prefix])
            ->count();

        return $prefix . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }

    private function currentUserLabel(): string
    {
        $user = Auth::user();
        if (!$user) {
            return 'ADMIN';
        }

        foreach (['username', 'name', 'email'] as $field) {
            $val = trim((string) ($user->{$field} ?? ''));
            if ($val !== '') {
                return $val;
            }
        }

        return 'ADMIN';
    }

    private function siswaInScope(int $custid): bool
    {
        $unit = trim((string) (Auth::user()->unit ?? ''));
        if ($unit === '') {
            return true;
        }

        return DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where('CUSTID', $custid)
            ->where(function ($q) use ($unit) {
                $q->whereRaw('TRIM(CAST(CODE01 AS CHAR)) = ?', [$unit])
                    ->orWhereRaw('TRIM(CAST(CODE02 AS CHAR)) = ?', [$unit]);
            })
            ->exists();
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::connection('DATA_MYSQL')->hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    private function parseAmount(mixed $value): int
    {
        $raw = preg_replace('/[^\d]/', '', (string) $value) ?? '';

        return (int) $raw;
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
        ], $status);
    }
}
