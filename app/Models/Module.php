<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['course_id', 'name', 'module_number', 'description'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->module_number
            ? "{$this->module_number}: {$this->name}"
            : $this->name;
    }
}
