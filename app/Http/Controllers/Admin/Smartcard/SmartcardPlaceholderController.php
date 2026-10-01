<?php

namespace App\Http\Controllers\Admin\Smartcard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SmartcardPlaceholderController extends Controller
{
    public function index(string $page): View
    {
        $titles = [];

        $mainTitle = $titles[$page] ?? str_replace('-', ' ', ucwords($page, '-'));

        return view('admin.smartcard.placeholder', [
            'title' => 'smartCARD',
            'mainTitle' => $mainTitle,
            'dataTitle' => $mainTitle,
            'pageKey' => $page,
        ]);
    }
}
