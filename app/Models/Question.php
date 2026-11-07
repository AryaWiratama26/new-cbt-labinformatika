<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPE_CODING = 'coding';

    public const SUPPORTED_LANGUAGES = [
        'python' => 'Python',
        'php' => 'PHP',
        'javascript' => 'JavaScript',
        'cpp' => 'C / C++',
        'java' => 'Java',
        'sql' => 'SQL',
    ];

    protected $fillable = [
        'module_id',
        'exam_id',
        'content',
        'image',
        'category',
        'explanation',
        'type',
        'code_language',
        'starter_code',
        'solution_code',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    public function isCoding(): bool
    {
        return $this->type === self::TYPE_CODING;
    }

    public function languageLabel(): ?string
    {
        return $this->code_language ? (self::SUPPORTED_LANGUAGES[$this->code_language] ?? strtoupper($this->code_language)) : null;
    }

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
