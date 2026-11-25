@extends('errors.layout')
@section('code', '403')
@section('icon', 'shield-lock')
@section('color', 'warning')
@section('heading', "You don't have access to this page")
@section('message'){{ $exception->getMessage() ?: 'Your role does not include this function. Ask the administrator if you need access.' }}@endsection
