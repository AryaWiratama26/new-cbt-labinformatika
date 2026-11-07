<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProctorEvent extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['exam_session_id', 'source', 'type', 'meta'];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function examSession()
    {
        return $this->belongsTo(ExamSession::class);
    }
}
