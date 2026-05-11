<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    protected $fillable = ['exam_session_id', 'question_id', 'option_id'];

    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function option()
    {
        return $this->belongsTo(Option::class);
    }
}
