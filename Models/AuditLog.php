<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_id','actor_type','action','status',
        'target_type','target_id','message','meta','ip','user_agent'
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
