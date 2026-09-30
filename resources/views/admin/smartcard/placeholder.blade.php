@extends('layouts.admin_new')
@section('content')
    <h3 class="page-heading d-flex text-gray-900 fw-bold flex-column justify-content-center my-0">
        {{ $dataTitle ?? ($mainTitle ?? '') }}
    </h3>
    <ul class="breadcrumb breadcrumb-style2">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.index') }}" class="text-hover-primary">Beranda</a>
        </li>
        <li class="breadcrumb-item">smartCARD</li>
        <li class="breadcrumb-item active">{{ $mainTitle ?? '' }}</li>
    </ul>

    <div class="card">
        <div class="card-body text-center py-5">
            <h5 class="mb-2">{{ $mainTitle ?? 'smartCARD' }}</h5>
            <p class="text-muted mb-0">
                Halaman siap. Fitur akan diisi setelah penjelasan detail.
            </p>
        </div>
    </div>
@endsection
