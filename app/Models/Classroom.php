<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = ['name', 'academic_year', 'semester'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_classroom')
            ->withPivot('start_time', 'end_time', 'duration_minutes', 'pin', 'is_active')
            ->withTimestamps();
    }
}
