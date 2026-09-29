<?php

namespace App\Http\Controllers\Admin\HistoryDataLama;

use App\Http\Controllers\Controller;

class HistoryDataLamaController extends Controller
{
    private string $title = 'History Data Lama';

    private array $pages = [
        'history-transaksi' => 'History Transaksi',
        'history-transaksi-belanja' => 'History Transaksi Belanja',
        'history-rekap-cashout' => 'History Rekap Cashout',
        'history-rekap-top-up' => 'History Rekap Top Up',
        'history-pencairan-kantin' => 'History Pencairan Kantin',
    ];

    public function index(string $page)
    {
        abort_unless(isset($this->pages[$page]), 404);

        $data['title'] = $this->title;
        $data['mainTitle'] = $this->pages[$page];
        $data['dataTitle'] = $this->pages[$page];
        $data['pageSlug'] = $page;

        return view('admin.history_data_lama.index', $data);
    }
}
