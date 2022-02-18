@extends('layouts.app')

@section('content')
<div class="container">
    <file-uploader :chunk-size="{{ config('filesystems.chunk_size') }}" />
</div>
@endsection
