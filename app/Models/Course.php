<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['code', 'name'];

    public function modules()
    {
        return $this->hasMany(Module::class)->orderBy('module_number');
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }
}
