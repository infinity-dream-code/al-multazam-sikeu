<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{route('admin.index')}}" class="app-brand-link">
            <span class="app-brand-logo demo">
                <span style="color: var(--bs-primary)">
                    <img width="50" height="50" src="{{asset('iconku.png')}}" alt="Al-Multazam">
                </span>
            </span>
            <span class="app-brand-text demo menu-text fw-bold ms-2 lh-sm">
                Al-Multazam
            </span>
        </a>
        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M11.4854 4.88844C11.0081 4.41121 10.2344 4.41121 9.75715 4.88844L4.51028 10.1353C4.03297 10.6126 4.03297 11.3865 4.51028 11.8638L9.75715 17.1107C10.2344 17.5879 11.0081 17.5879 11.4854 17.1107C11.9626 16.6334 11.9626 15.8597 11.4854 15.3824L7.96672 11.8638C7.48942 11.3865 7.48942 10.6126 7.96672 10.1353L11.4854 6.61667C11.9626 6.13943 11.9626 5.36568 11.4854 4.88844Z" fill="currentColor" fill-opacity="0.6"/>
                <path d="M15.8683 4.88844L10.6214 10.1353C10.1441 10.6126 10.1441 11.3865 10.6214 11.8638L15.8683 17.1107C16.3455 17.5879 17.1192 17.5879 17.5965 17.1107C18.0737 16.6334 18.0737 15.8597 17.5965 15.3824L14.0778 11.8638C13.6005 11.3865 13.6005 10.6126 14.0778 10.1353L17.5965 6.61667C18.0737 6.13943 18.0737 5.36568 17.5965 4.88844C17.1192 4.41121 16.3455 4.41121 15.8683 4.88844Z" fill="currentColor" fill-opacity="0.38"/>
            </svg>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <li class="menu-item {{ Request::is(['admin/master-data*']) ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon ri ri-database-2-line"></i>
                <div data-i18n="Master Data">Master Data</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ Request::is(['admin/master-data/master-kelas*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.master-kelas.index') }}" class="menu-link">
                        <div data-i18n="Master Kelas">Master Kelas</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/tahun-pelajaran*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.tahun-pelajaran.index') }}" class="menu-link">
                        <div data-i18n="Tahun Akademik">Tahun Akademik</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/master-post*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.master-post.index') }}" class="menu-link">
                        <div data-i18n="Master Post">Master Post</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/beban-post*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.beban-post.index') }}" class="menu-link">
                        <div data-i18n="Beban Post">Beban Post</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/export-import-data*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.export-import-data.index') }}" class="menu-link">
                        <div data-i18n="Export Import Data">Export Import Data</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/data-siswa*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.data-siswa.index') }}" class="menu-link">
                        <div data-i18n="Data Siswa">Data Siswa</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/master-data/pindah-kelas*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.master-data.pindah-kelas.index') }}" class="menu-link">
                        <div data-i18n="Pindah Kelas">Pindah Kelas</div>
                    </a>
                </li>
            </ul>
        </li>

        <li class="menu-item {{ Request::is(['admin/history-data-lama*']) ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon ri ri-history-line"></i>
                <div data-i18n="History Data Lama">History Data Lama</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ Request::is(['admin/history-data-lama/history-transaksi']) ? 'active' : '' }}">
                    <a href="{{ route('admin.history-data-lama.history-transaksi.index') }}" class="menu-link">
                        <div data-i18n="History Transaksi">History Transaksi</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/history-data-lama/history-transaksi-belanja*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.history-data-lama.history-transaksi-belanja.index') }}" class="menu-link">
                        <div data-i18n="History Transaksi Belanja">History Transaksi Belanja</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/history-data-lama/history-rekap-cashout*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.history-data-lama.history-rekap-cashout.index') }}" class="menu-link">
                        <div data-i18n="History Rekap Cashout">History Rekap Cashout</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/history-data-lama/history-rekap-top-up*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.history-data-lama.history-rekap-top-up.index') }}" class="menu-link">
                        <div data-i18n="History Rekap Top Up">History Rekap Top Up</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/history-data-lama/history-pencairan-kantin*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.history-data-lama.history-pencairan-kantin.index') }}" class="menu-link">
                        <div data-i18n="History Pencairan Kantin">History Pencairan Kantin</div>
                    </a>
                </li>
            </ul>
        </li>

        <li class="menu-item {{ Request::is(['admin/smartcard*']) ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon ri ri-bank-card-line"></i>
                <div data-i18n="smartCARD">smartCARD</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ Request::is(['admin/smartcard/saldo-virtual-account*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.saldo-virtual-account.index') }}" class="menu-link">
                        <div>Saldo Virtual Account</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/data-kartu-siswa*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.data-kartu-siswa.index') }}" class="menu-link">
                        <div>Data Kartu Siswa</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/setting-blokir-kartu*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.setting-blokir-kartu.index') }}" class="menu-link">
                        <div>Setting Blokir Kartu</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/setting-batasan-saku*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.setting-batasan-saku.index') }}" class="menu-link">
                        <div>Setting Batasan Saku</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/transaksi-belanja*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.transaksi-belanja.index') }}" class="menu-link">
                        <div>Transaksi Belanja</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/pencairan-kantin*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.pencairan-kantin.index') }}" class="menu-link">
                        <div>Pencairan Kantin</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/rekap-topup*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.rekap-topup.index') }}" class="menu-link">
                        <div>Rekap TOPUP</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/tap-ritel*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.tap-ritel.index') }}" class="menu-link">
                        <div>TAP RITEL</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/tap-laundry*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.tap-laundry.index') }}" class="menu-link">
                        <div>TAP LAUNDRY</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/tap-perpus*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.tap-perpus.index') }}" class="menu-link">
                        <div>TAP PERPUS</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/topup-saldo*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.topup-saldo.index') }}" class="menu-link">
                        <div>TOP UP Saldo</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/migrasi-saldo-awal*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.migrasi-saldo-awal.index') }}" class="menu-link">
                        <div>Migrasi Saldo Awal</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/debit-saldo-excel*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.debit-saldo-excel.index') }}" class="menu-link">
                        <div>Debit Saldo Excel</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/debit-saldo*']) && !Request::is(['admin/smartcard/debit-saldo-excel*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.debit-saldo.index') }}" class="menu-link">
                        <div>Debit Saldo</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/setting-merchant-mobile*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.setting-merchant-mobile.index') }}" class="menu-link">
                        <div>Setting Merchant Mobile</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/keluar-uang-saku*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.keluar-uang-saku.index') }}" class="menu-link">
                        <div>Keluar Uang Saku</div>
                    </a>
                </li>
                <li class="menu-item {{ Request::is(['admin/smartcard/cek-batas-jajan*']) ? 'active' : '' }}">
                    <a href="{{ route('admin.smartcard.cek-batas-jajan.index') }}" class="menu-link">
                        <div>CEK Batas Jajan</div>
                    </a>
                </li>
            </ul>
        </li>

        <li class="menu-item mt-auto pb-2">
            <a href="{{route('logout')}}" class="menu-link btn-danger text-white" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class="menu-icon ri ri-logout-box-r-line"></i>
                <div data-i18n="Logout">Logout</div>
            </a>
        </li>
    </ul>
</aside>
