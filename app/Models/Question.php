<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['module_id', 'exam_id', 'content', 'image', 'category', 'explanation'];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    // Keep backward compat for old data
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function options()
    {
        return $this->hasMany(Option::class);
    }
}
