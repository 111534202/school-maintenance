<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'loggable_type', 'loggable_id', 'changes', 'description',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
