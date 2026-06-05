@extends('layouts.app')
@section('title', __('Bills'))

@section('content')
    @include('finance._list', [
        'heading'           => __('Vendor bills'),
        'partnerLabel'      => __('Vendor'),
        'searchPlaceholder' => __('Search bills...'),
    ])
@endsection
