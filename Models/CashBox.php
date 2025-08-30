<?php
// app/Models/CashBox.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashBox extends Model {
    protected $table = 'cash_boxes';
    protected $fillable = ['location','is_active'];
    public $timestamps = true;
}


