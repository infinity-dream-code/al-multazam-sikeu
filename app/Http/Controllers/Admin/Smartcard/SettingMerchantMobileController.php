<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Setting Merchant Mobile — port builder SETTING MOBILE MERCHANT.
 * Tambah: SELECT AndroidAddMerchant(nama, username, role)
 * Reset: CALL AndroidResetPassMerchant(username, users, host)
 * List: sm_kantin (Nama Kantin, Username, Jenis).
 */
class SettingMerchantMobileController extends Controller
{
    /** @var list<string> */
    private const ROLE_OPTIONS = ['MERCHANT', 'LAUNDRY', 'PERPUS'];

    public function index(): View
    {
        return view('admin.smartcard.setting_merchant_mobile.index', [
            'title' => 'smartCARD',
            'mainTitle' => 'Setting Merchant Mobile',
            'dataTitle' => 'SETTING MOBILE MERCHANT',
            'rows' => $this->fetchMerchants(),
            'roleOptions' => self::ROLE_OPTIONS,
        ]);
    }

    public function tambah(Request $request): RedirectResponse
    {
        $nama = trim((string) $request->input('nama_merchant', ''));
        $username = trim((string) $request->input('username_mobile', ''));
        $role = strtoupper(trim((string) $request->input('jenis_merchant', '')));

        if ($nama === '' || $username === '' || $role === '') {
            return redirect()
                ->route('admin.smartcard.setting-merchant-mobile.index')
                ->withInput()
                ->with('smartcard_error', 'Nama dan Username Kantin Mohon Terisi');
        }

        if (!in_array($role, self::ROLE_OPTIONS, true)) {
            return redirect()
                ->route('admin.smartcard.setting-merchant-mobile.index')
                ->withInput()
                ->with('smartcard_error', 'Jenis Merchant harus MERCHANT / LAUNDRY / PERPUS.');
        }

        try {
            $result = $this->callAddMerchant($nama, $username, $role);
        } catch (\Throwable $e) {
            Log::error('SettingMerchantMobile tambah failed', ['message' => $e->getMessage()]);
            report($e);

            return redirect()
                ->route('admin.smartcard.setting-merchant-mobile.index')
                ->withInput()
                ->with('smartcard_error', 'Gagal tambah: ' . $e->getMessage());
        }

        if (strtoupper(trim($result)) === 'OK') {
            return redirect()
                ->route('admin.smartcard.setting-merchant-mobile.index')
                ->with('smartcard_success', 'Data Tersimpan');
        }

        return redirect()
            ->route('admin.smartcard.setting-merchant-mobile.index')
            ->withInput()
            ->with('smartcard_error', 'Username Telah Terpakai');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $username = trim((string) $request->input('username', ''));
        if ($username === '') {
            return redirect()
                ->route('admin.smartcard.setting-merchant-mobile.index')
                ->with('smartcard_error', 'Pilih kantin di tabel terlebih dahulu.');
        }

        $users = trim((string) (Auth::user()->username ?? Auth::user()->name ?? 'admin'));
        $host = (string) ($request->server('SERVER_ADDR')
            ?: $request->getHost()
            ?: gethostname()
            ?: 'localhost');

        try {
            DB::connection('DATA_MYSQL')->statement(
                'CALL AndroidResetPassMerchant(?, ?, ?)',
                [$username, $users, $host]
            );
        } catch (\Throwable $e) {
            Log::error('SettingMerchantMobile reset failed', [
                'username' => $username,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return redirect()
                ->route('admin.smartcard.setting-merchant-mobile.index')
                ->with('smartcard_error', 'Gagal reset password: ' . $e->getMessage());
        }

        return redirect()
            ->route('admin.smartcard.setting-merchant-mobile.index')
            ->with('smartcard_success', 'OK — password merchant "' . $username . '" di-reset.');
    }

    /**
     * Builder: Result = SELECT AndroidAddMerchant(...)
     */
    private function callAddMerchant(string $nama, string $username, string $role): string
    {
        $row = DB::connection('DATA_MYSQL')->selectOne(
            'SELECT AndroidAddMerchant(?, ?, ?) AS result',
            [$nama, $username, $role]
        );

        if ($row === null) {
            return '';
        }

        $arr = (array) $row;

        return trim((string) ($arr['result'] ?? reset($arr) ?? ''));
    }

    private function fetchMerchants(): Collection
    {
        try {
            if (!Schema::connection('DATA_MYSQL')->hasTable('sm_kantin')) {
                return collect();
            }
        } catch (\Throwable) {
            return collect();
        }

        $namaCol = $this->detectColumn('sm_kantin', ['NamaKantin', 'nama_kantin', 'NamaMercan', 'nama']);
        $userCol = $this->detectColumn('sm_kantin', ['username', 'Username', 'USER', 'user']);
        $roleCol = $this->detectColumn('sm_kantin', [
            'Role', 'role', 'Jenis', 'JenisKantin', 'RoleKantin', 'tipe', 'Type', 'TYPE',
        ]);

        if ($namaCol === null || $userCol === null) {
            return collect();
        }

        $select = [
            DB::raw("TRIM(`{$namaCol}`) as nama_kantin"),
            DB::raw("TRIM(`{$userCol}`) as username"),
        ];
        if ($roleCol !== null) {
            $select[] = DB::raw("TRIM(`{$roleCol}`) as jenis_kantin");
        }

        try {
            $rows = DB::connection('DATA_MYSQL')
                ->table('sm_kantin')
                ->orderBy($namaCol)
                ->get($select);
        } catch (\Throwable $e) {
            Log::warning('SettingMerchantMobile list failed', ['message' => $e->getMessage()]);

            return collect();
        }

        return $rows->map(function ($row) {
            return (object) [
                'nama_kantin' => trim((string) ($row->nama_kantin ?? '')),
                'username' => trim((string) ($row->username ?? '')),
                'jenis_kantin' => trim((string) ($row->jenis_kantin ?? '-')),
            ];
        })->filter(fn ($r) => $r->username !== '' || $r->nama_kantin !== '')->values();
    }

    /**
     * @param  list<string>  $candidates
     */
    private function detectColumn(string $table, array $candidates): ?string
    {
        try {
            $cols = Schema::connection('DATA_MYSQL')->getColumnListing($table);
        } catch (\Throwable) {
            return null;
        }

        $map = [];
        foreach ($cols as $col) {
            $map[strtolower((string) $col)] = (string) $col;
        }

        foreach ($candidates as $cand) {
            $key = strtolower($cand);
            if (isset($map[$key])) {
                return $map[$key];
            }
        }

        return null;
    }
}
