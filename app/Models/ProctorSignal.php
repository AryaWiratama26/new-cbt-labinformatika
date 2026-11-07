<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProctorSignal extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['exam_session_id', 'channel', 'viewer_id', 'sender', 'type', 'payload'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function examSession()
    {
        return $this->belongsTo(ExamSession::class);
    }
}
