<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = ['title', 'description', 'course_id', 'module_id', 'classroom_id', 'start_time', 'end_time', 'duration_minutes', 'is_active'];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
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

    /**
     * Questions come from the module's bank, not directly from exam.
     * This accessor provides backward-compat when a module exists.
     */
    public function questions()
    {
        if ($this->module_id) {
            // Return module's questions via hasManyThrough-like approach
            return $this->module->questions()->with('options');
        }
        // Legacy: questions tied directly to exam
        return $this->hasMany(Question::class);
    }

    public function examSessions()
    {
        return $this->hasMany(ExamSession::class);
    }
}
