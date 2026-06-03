@extends('layouts.customer')

@section('title', 'Tidak tersedia | ')

@section('content')
    <div class="alert alert-warning">
        <h5 class="alert-heading">Toko belum tersedia</h5>
        <p class="mb-0">{{ $message ?? 'Hubungi admin untuk menghubungkan akun ke cabang.' }}</p>
    </div>
@endsection
