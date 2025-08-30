@extends('layouts.app')

@section('content')
<div class="container">
    <h2>ویرایش صندوق</h2>
    <form id="accountEditForm"
          action="{{ route('admin.accounts.update', ['account' => $cashbox->id, 'kind' => 'cashbox']) }}"
          method="POST"
          class="modern-form shadow-sm p-4 rounded-4 bg-white mb-4">
        @csrf
        @method('PUT')
<input type="hidden" name="kind" id="kind" value="cashbox">

        <div class="mb-3">
            <label for="location" class="form-label">محل/توضیحات صندوق</label>
            <input type="text" name="location" id="location" class="form-control"
                   value="{{ old('location', $cashbox->location) }}">
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" id="is_active" value="1"
                   class="form-check-input" {{ old('is_active', $cashbox->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">فعال باشد</label>
        </div>
        <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
    </form>
</div>
@endsection