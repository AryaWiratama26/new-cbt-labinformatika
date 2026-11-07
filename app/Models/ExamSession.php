<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSession extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'exam_id', 'started_at', 'finished_at', 'score', 'graded_at', 'attempt_number', 'tab_switches', 'laptop_seen_at', 'phone_seen_at'];

    protected $casts = [
        'user_id' => 'integer',
        'exam_id' => 'integer',
        'attempt_number' => 'integer',
        'tab_switches' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'graded_at' => 'datetime',
        'laptop_seen_at' => 'datetime',
        'phone_seen_at' => 'datetime',
        'score' => 'decimal:2',
    ];

    public function hasPendingCoding(): bool
    {
        $codingIds = $this->exam
            ? $this->exam->getQuestions()->filter(fn ($q) => $q->isCoding())->pluck('id')
            : collect();

        return $this->answers()
            ->whereIn('question_id', $codingIds)
            ->get()
            ->contains(fn ($answer) => $answer->isUnscoredCodingAnswer());
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    public function proctorSignals()
    {
        return $this->hasMany(ProctorSignal::class);
    }

    public function proctorEvents()
    {
        return $this->hasMany(ProctorEvent::class);
    }
}
