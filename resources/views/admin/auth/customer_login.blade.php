<!-- # resources/views/admin/discount_codes/customer_login.blade.php -->


@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-4">
        <h4 class="text-center">ورود مشتری</h4>
        <form method="POST" action="{{ route('customer.login') }}">
            @csrf
            <div class="mb-3">
                <label>موبایل یا کد ملی</label>
                <input type="text" name="national_code" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>رمز عبور</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success w-100">ورود</button>
        </form>
    </div>
</div>
@endsection
