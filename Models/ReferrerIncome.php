<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferrerIncome extends Model
{
    protected $table = 'referrer_incomes';
    public $timestamps = true;

    protected $fillable = [
        'referrer_code',
        'amount',
        'invoice_id',
        'invoice_item_id',
        'commission_status',
        'created_at',
        'updated_at',
    ];
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id');
    }
}
