<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = ['name'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function exams()
    {
        return $this->hasMany(Exam::class);
    }
}
