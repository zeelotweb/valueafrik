@extends('errors::minimal')

@section('title', __('Invalid Request'))
@section('code', '422')
@section('message', __('That action couldn\'t be completed. Please go back and try again.'))
