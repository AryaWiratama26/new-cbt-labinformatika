<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = ['title', 'description', 'course_id', 'module_id', 'classroom_id', 'start_time', 'end_time', 'duration_minutes', 'is_active', 'passing_grade', 'max_attempts', 'max_tab_switches', 'require_fullscreen'];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_active' => 'boolean',
        'require_fullscreen' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class, 'exam_id');
    }

    public function moduleQuestions()
    {
        return $this->hasManyThrough(Question::class, Module::class, 'id', 'module_id', 'module_id');
    }

    public function getQuestions()
    {
        if ($this->module_id) {
            return Question::where('module_id', $this->module_id)->with('options')->get();
        }
        return $this->questions()->with('options')->get();
    }

    public function getQuestionsCount()
    {
        if ($this->module_id) {
            return Question::where('module_id', $this->module_id)->count();
        }
        return $this->questions()->count();
    }

    public function examSessions()
    {
        return $this->hasMany(ExamSession::class);
    }

    public function canRemedial(User $user): bool
    {
        if ($this->max_attempts <= 1) return false;

        $lastSession = ExamSession::where('user_id', $user->id)
            ->where('exam_id', $this->id)
            ->orderByDesc('attempt_number')
            ->first();

        if (!$lastSession || !$lastSession->finished_at) return false;

        return $lastSession->score < $this->passing_grade
            && $lastSession->attempt_number < $this->max_attempts;
    }
}
