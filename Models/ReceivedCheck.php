<?php

// app/Models/ReceivedCheck.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivedCheck extends Model
{
    protected $table = 'received_checks';
    public $timestamps = true;
    protected $fillable = [
        'cheque_serial',
        'cheque_account_number',
        'cheque_bank_name',
        'cheque_amount',
        'cheque_issue_date',
        'cheque_due_date',
        'cheque_status',
        'cheque_issuer',
        'cheque_issuer_type',
        'cheque_issuer_id',
        'receiver',
        'receiver_type',
        'receiver_id',
        'deposit_account_id',
        'transaction_id',
        'status_changed_at',
        'description',
        'transferred_to_type',
        'transferred_to_id',
        'transferred_at',
        'invoice_deposit_id',
        'invoice_income_id',

    ];
    protected $casts = [
        'cheque_amount'      => 'float',
        'cheque_issue_date'  => 'date',
        'cheque_due_date'    => 'date',
        'status_changed_at'  => 'datetime',
        'transferred_at'     => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // اگر نیاز داشتی روابط polymorphic اضافه کن

    public function transferredParty()
    {
        if ($this->transferred_to_type && $this->transferred_to_id) {
            $model = match ($this->transferred_to_type) {
                'customer' => \App\Models\Customer::class,
                'staff' => \App\Models\Staff::class,
                'contact' => \App\Models\Contact::class,
                default => null
            };
            if ($model) return $model::find($this->transferred_to_id);
        }
        return null;
    }
}
