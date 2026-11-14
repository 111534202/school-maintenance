{{-- 403：沒有權限。 --}}
@extends('errors.layout', ['code' => 403])
@section('title', '你沒有權限使用這個功能')
@section('body')
    <p>這個功能需要額外的權限，你目前的身分不能使用。</p>
    <p class="hint">如果工作上需要，請聯絡資訊組（系統管理員）開通；也可能是你點到了別人的資料。</p>
@endsection
