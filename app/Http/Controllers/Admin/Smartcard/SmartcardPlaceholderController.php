<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SmartcardPlaceholderController extends Controller
{
    public function index(string $page): View
    {
        $titles = [
            'saldo-virtual-account' => 'Saldo Virtual Account',
            'data-kartu-siswa' => 'Data Kartu Siswa',
            'setting-blokir-kartu' => 'Setting Blokir Kartu',
            'setting-batasan-saku' => 'Setting Batasan Saku',
            'transaksi-belanja' => 'Transaksi Belanja',
            'pencairan-kantin' => 'Pencairan Kantin',
            'rekap-topup' => 'Rekap TOPUP',
            'tap-ritel' => 'TAP RITEL',
            'tap-laundry' => 'TAP LAUNDRY',
            'tap-perpus' => 'TAP PERPUS',
            'topup-saldo' => 'TOP UP Saldo',
            'migrasi-saldo-awal' => 'Migrasi Saldo Awal',
            'debit-saldo-excel' => 'Debit Saldo Excel',
            'debit-saldo' => 'Debit Saldo',
            'setting-merchant-mobile' => 'Setting Merchant Mobile',
            'keluar-uang-saku' => 'Keluar Uang Saku',
            'cek-batas-jajan' => 'CEK Batas Jajan',
        ];

        // saldo-virtual-account sudah punya controller sendiri
        unset($titles['saldo-virtual-account']);

        $mainTitle = $titles[$page] ?? str_replace('-', ' ', ucwords($page, '-'));

        return view('admin.smartcard.placeholder', [
            'title' => 'smartCARD',
            'mainTitle' => $mainTitle,
            'dataTitle' => $mainTitle,
            'pageKey' => $page,
        ]);
    }
}
