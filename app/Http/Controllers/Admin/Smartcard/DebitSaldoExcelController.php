<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Debit Saldo Excel — port builder DEBET SALDO USAKU by EXCEL.
 * UPDATE: sccttran (CASHOUT/ExeByEXL/CASH) + scctcashout (FIDBANK=CASH).
 */
class DebitSaldoExcelController extends Controller
{
    private const SESSION_ROWS = 'debit_saldo_excel_rows';

    public function index(): View
    {
        $rows = collect(session(self::SESSION_ROWS, []));

        return view('admin.smartcard.debit_saldo_excel.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Debit Saldo Excel',
            'dataTitle' => 'DEBET SALDO USAKU by EXCEL',
            'rows' => $rows,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ], [
            'file.required' => 'Pilih file terlebih dahulu.',
            'file.mimes' => 'File harus Excel/CSV (.xlsx, .xls, .csv).',
        ]);

        try {
            $sheets = Excel::toArray(new class {}, $request->file('file'));
            $raw = $sheets[0] ?? [];
            if ($raw === []) {
                return redirect()
                    ->route('admin.smartcard.debit-saldo-excel.index')
                    ->with('smartcard_error', 'File kosong / tidak bisa dibaca.');
            }

            $rows = $this->parseSheet($raw);
            if ($rows->isEmpty()) {
                return redirect()
                    ->route('admin.smartcard.debit-saldo-excel.index')
                    ->with('smartcard_error', 'Tidak ada baris valid. Pastikan ada kolom CUSTID/INT CODE dan Nominal.');
            }

            session([self::SESSION_ROWS => $rows->values()->all()]);

            return redirect()
                ->route('admin.smartcard.debit-saldo-excel.index')
                ->with('smartcard_success', 'File berhasil dibuka: ' . $rows->count() . ' baris.');
        } catch (\Throwable $e) {
            Log::error('DebitSaldoExcel open failed', ['message' => $e->getMessage()]);
            report($e);

            return redirect()
                ->route('admin.smartcard.debit-saldo-excel.index')
                ->with('smartcard_error', 'Gagal membuka file: ' . $e->getMessage());
        }
    }

    public function update(Request $request): RedirectResponse
    {
        $rows = collect(session(self::SESSION_ROWS, []));
        if ($rows->isEmpty()) {
            return redirect()
                ->route('admin.smartcard.debit-saldo-excel.index')
                ->with('smartcard_error', 'Belum ada data. OPEN file dulu.');
        }

        $ok = 0;
        $skip = 0;
        $teller = $this->currentUserLabel();

        try {
            DB::connection('DATA_MYSQL')->transaction(function () use ($rows, $teller, &$ok, &$skip) {
                foreach ($rows as $row) {
                    $row = (object) $row;
                    $custid = (int) ($row->custid ?? 0);
                    $nominal = (int) ($row->nominal ?? 0);

                    if ($custid <= 0 || $nominal == 0) {
                        $skip++;
                        continue;
                    }

                    if (!$this->siswaInScope($custid)) {
                        $skip++;
                        continue;
                    }

                    // Builder: KDCHANNEL = ''
                    DB::connection('DATA_MYSQL')->table('sccttran')->insert([
                        'CUSTID' => $custid,
                        'NOREFF' => 'ExeByEXL',
                        'FIDBANK' => 'CASH',
                        'TRXDATE' => now()->format('Y-m-d H:i:s'),
                        'KDCHANNEL' => '',
                        'DEBET' => $nominal,
                        'KREDIT' => 0,
                        'METODE' => 'CASHOUT',
                        'REFFBANK' => '38',
                    ]);

                    DB::connection('DATA_MYSQL')->table('scctcashout')->insert([
                        'CUSTID' => $custid,
                        'BILLAM' => $nominal,
                        'TanggalKeluar' => now()->format('Y-m-d H:i:s'),
                        'Teller' => $teller,
                        'FIDBANK' => 'CASH',
                    ]);

                    $ok++;
                }
            });
        } catch (\Throwable $e) {
            Log::error('DebitSaldoExcel update failed', ['message' => $e->getMessage()]);
            report($e);

            return redirect()
                ->route('admin.smartcard.debit-saldo-excel.index')
                ->with('smartcard_error', 'Gagal proses debit: ' . $e->getMessage());
        }

        session()->forget(self::SESSION_ROWS);

        return redirect()
            ->route('admin.smartcard.debit-saldo-excel.index')
            ->with('smartcard_success', "Sukses. Berhasil: {$ok}, dilewati: {$skip}.");
    }

    public function clear(): RedirectResponse
    {
        session()->forget(self::SESSION_ROWS);

        return redirect()
            ->route('admin.smartcard.debit-saldo-excel.index')
            ->with('smartcard_success', 'List dikosongkan.');
    }

    private function parseSheet(array $raw): Collection
    {
        if ($raw === []) {
            return collect();
        }

        $headerRow = array_map(static function ($v) {
            return strtolower(trim((string) $v));
        }, $raw[0] ?? []);

        $hasHeader = $this->looksLikeHeader($headerRow);
        $map = $hasHeader ? $this->mapHeaders($headerRow) : [
            'custid' => 0,
            'nis' => 1,
            'nama' => 2,
            'nominal' => 3,
            'unit' => 4,
            'kelas' => 5,
            'kelompok' => 6,
        ];

        $start = $hasHeader ? 1 : 0;
        $out = collect();

        for ($i = $start; $i < count($raw); $i++) {
            $line = $raw[$i];
            if (!is_array($line) || $this->rowEmpty($line)) {
                continue;
            }

            $nis = $this->cell($line, $map['nis'] ?? null);
            $custid = (int) preg_replace('/\D+/', '', $this->cell($line, $map['custid'] ?? null));
            $nominal = $this->parseAmount($this->cell($line, $map['nominal'] ?? null));
            $nama = $this->cell($line, $map['nama'] ?? null);
            $unit = $this->cell($line, $map['unit'] ?? null);
            $kelas = $this->cell($line, $map['kelas'] ?? null);
            $kelompok = $this->cell($line, $map['kelompok'] ?? null);

            if ($custid <= 0 && $nis !== '') {
                $custid = $this->resolveCustidByNis($nis);
            }

            if (($nama === '' || $nis === '') && $custid > 0) {
                $siswa = DB::connection('DATA_MYSQL')
                    ->table('scctcust')
                    ->where('CUSTID', $custid)
                    ->first(['NMCUST', 'NOCUST', 'DESC02', 'DESC03', 'CODE02']);
                if ($siswa) {
                    if ($nama === '') {
                        $nama = trim((string) ($siswa->NMCUST ?? ''));
                    }
                    if ($nis === '') {
                        $nis = trim((string) ($siswa->NOCUST ?? ''));
                    }
                    if ($kelas === '') {
                        $kelas = trim((string) ($siswa->DESC02 ?? ''));
                    }
                    if ($kelompok === '') {
                        $kelompok = trim((string) ($siswa->DESC03 ?? ''));
                    }
                    if ($unit === '') {
                        $unit = trim((string) ($siswa->CODE02 ?? ''));
                    }
                }
            }

            if ($custid <= 0 && $nominal == 0 && $nis === '') {
                continue;
            }

            $out->push([
                'custid' => $custid,
                'nis' => $nis,
                'nama' => $nama,
                'nominal' => $nominal,
                'unit' => $unit,
                'kelas' => $kelas,
                'kelompok' => $kelompok,
                'valid' => $custid > 0 && $nominal != 0,
            ]);
        }

        return $out;
    }

    private function looksLikeHeader(array $headerRow): bool
    {
        $joined = implode('|', $headerRow);

        return str_contains($joined, 'nis')
            || str_contains($joined, 'custid')
            || str_contains($joined, 'intcode')
            || str_contains($joined, 'nominal')
            || str_contains($joined, 'nama');
    }

    /** @return array<string, int|null> */
    private function mapHeaders(array $headerRow): array
    {
        $map = [
            'custid' => null,
            'nis' => null,
            'nama' => null,
            'nominal' => null,
            'unit' => null,
            'kelas' => null,
            'kelompok' => null,
        ];

        foreach ($headerRow as $idx => $h) {
            $h = preg_replace('/\s+/', '', $h) ?? $h;
            if (in_array($h, ['custid', 'id', 'intcode', 'int_code', 'kode'], true)) {
                $map['custid'] = $idx;
            } elseif (in_array($h, ['nis', 'nocust', 'noinduk'], true)) {
                $map['nis'] = $idx;
            } elseif (in_array($h, ['nama', 'nmcust', 'namasiswa'], true)) {
                $map['nama'] = $idx;
            } elseif (in_array($h, ['nominal', 'nominalx', 'saldo', 'amount', 'debet'], true)) {
                $map['nominal'] = $idx;
            } elseif (in_array($h, ['unit', 'code02'], true)) {
                $map['unit'] = $idx;
            } elseif (in_array($h, ['kelas', 'desc02'], true)) {
                $map['kelas'] = $idx;
            } elseif (in_array($h, ['kelompok', 'desc03'], true)) {
                $map['kelompok'] = $idx;
            }
        }

        return $map;
    }

    private function cell(array $line, ?int $idx): string
    {
        if ($idx === null || !array_key_exists($idx, $line)) {
            return '';
        }

        return trim((string) $line[$idx]);
    }

    private function rowEmpty(array $line): bool
    {
        foreach ($line as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseAmount(mixed $value): int
    {
        $raw = preg_replace('/[^\d\-]/', '', (string) $value) ?? '';

        return (int) $raw;
    }

    private function resolveCustidByNis(string $nis): int
    {
        $query = DB::connection('DATA_MYSQL')
            ->table('scctcust')
            ->where(function ($q) use ($nis) {
                $q->whereRaw('TRIM(NOCUST) = ?', [$nis])
                    ->orWhereRaw('TRIM(NUM2ND) = ?', [$nis]);
            });

        $this->applySchoolScope($query);

        return (int) ($query->value('CUSTID') ?? 0);
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

    private function currentUserLabel(): string
    {
        $user = Auth::user();
        if (!$user) {
            return 'ADMIN';
        }
        foreach (['username', 'name', 'email'] as $f) {
            $v = trim((string) ($user->{$f} ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return 'ADMIN';
    }
}
