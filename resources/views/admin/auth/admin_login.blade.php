<!-- # resources/views/admin/discount_codes/admin_login.blade.php -->

@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-4">
        <h4 class="text-center mb-4">ورود مدیر</h4>
        
        {{-- نمایش پیام خروج موفق یا سایر پیام‌ها --}}
        @if (session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
        @endif

        {{-- نمایش پیام خطا --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login') }}">
            @csrf

            <div class="mb-3">
                <label>نام کاربری</label>
                <input type="text" name="adminusername" class="form-control" value="{{ old('adminusername') }}" required autofocus>
            </div>
            
            <div class="mb-3">
                <label>رمز عبور</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">ورود</button>
        </form>
    </div>
</div>
@endsection
