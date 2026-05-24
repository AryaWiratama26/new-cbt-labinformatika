<?php

namespace App\Models;

use AllowDynamicProperties;
use Illuminate\Database\Eloquent\Model;

#[AllowDynamicProperties]
class Exam extends Model
{
    protected $fillable = ['title', 'description', 'course_id', 'module_id', 'is_active', 'passing_grade', 'max_attempts', 'max_tab_switches', 'require_fullscreen'];

    protected $casts = [
        'is_active' => 'boolean',
        'require_fullscreen' => 'boolean',
        'passing_grade' => 'integer',
        'max_attempts' => 'integer',
        'max_tab_switches' => 'integer',
    ];

    public function hasPin(?int $classroomId = null): bool
    {
        if ($classroomId) {
            if ($this->relationLoaded('classrooms')) {
                return !empty($this->classrooms->firstWhere('id', $classroomId)?->pivot?->pin);
            }
            return $this->classrooms()
                ->where('classroom_id', $classroomId)
                ->whereNotNull('pin')
                ->exists();
        }
        if ($this->relationLoaded('classrooms')) {
            return $this->classrooms->contains(fn($c) => !empty($c->pivot?->pin));
        }
        return $this->classrooms()->whereNotNull('pin')->exists();
    }

    public function isClassroomActive(int $classroomId): bool
    {
        if ($this->relationLoaded('classrooms')) {
            $pivot = $this->classrooms->firstWhere('id', $classroomId)?->pivot;
            if ($pivot !== null) {
                return (bool) $pivot->is_active;
            }
        }
        $pivot = $this->classrooms()
            ->where('classroom_id', $classroomId)
            ->first()?->pivot;
        return $pivot ? (bool) $pivot->is_active : false;
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function classrooms()
    {
        return $this->belongsToMany(Classroom::class, 'exam_classroom')
            ->withPivot('start_time', 'end_time', 'duration_minutes', 'pin', 'is_active')
            ->withTimestamps();
    }

    public function getScheduleForClassroom($classroomId)
    {
        if ($this->relationLoaded('classrooms')) {
            $pivot = $this->classrooms->firstWhere('id', $classroomId)?->pivot;
        } else {
            $pivot = $this->classrooms()
                ->where('classroom_id', $classroomId)
                ->first()?->pivot;
        }

        if (!$pivot) return null;

        return (object) [
            'start_time' => $pivot->start_time instanceof \Carbon\Carbon
                ? $pivot->start_time
                : \Carbon\Carbon::parse($pivot->start_time),
            'end_time' => $pivot->end_time instanceof \Carbon\Carbon
                ? $pivot->end_time
                : \Carbon\Carbon::parse($pivot->end_time),
            'duration_minutes' => (int) $pivot->duration_minutes,
        ];
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
