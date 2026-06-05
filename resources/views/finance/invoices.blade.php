@extends('layouts.app')
@section('title', __('Invoices'))

@section('content')
    @include('finance._list', [
        'heading'           => __('Customer invoices'),
        'partnerLabel'      => __('Customer'),
        'searchPlaceholder' => __('Search invoices...'),
    ])
@endsection
