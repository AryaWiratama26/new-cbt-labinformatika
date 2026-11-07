<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    use HasFactory;

    public const GRADE_AUTO = 'auto';

    public const GRADE_PENDING = 'pending';

    public const GRADE_GRADED = 'graded';

    public const GRADED_BY_AI = 'ai';

    public const GRADED_BY_MANUAL = 'manual';

    protected $fillable = ['exam_session_id', 'question_id', 'option_id', 'answer_text', 'grade_status', 'score', 'graded_by', 'ai_grade_detail'];

    protected $casts = [
        'score' => 'decimal:2',
        'ai_grade_detail' => 'array',
    ];

    public function isAiGraded(): bool
    {
        return $this->grade_status === self::GRADE_GRADED && $this->graded_by === self::GRADED_BY_AI;
    }

    /**
     * Kondisi "belum dinilai" sisi jawaban, selaras dengan
     * ExamScoringService::recompute(): ber-teks (tidak kosong/blank)
     * dan belum berstatus GRADED. Panggil hanya untuk soal koding.
     */
    public function isUnscoredCodingAnswer(): bool
    {
        return $this->answer_text !== null
            && trim((string) $this->answer_text) !== ''
            && $this->grade_status !== self::GRADE_GRADED;
    }

    public function isManuallyGraded(): bool
    {
        return $this->grade_status === self::GRADE_GRADED && $this->graded_by === self::GRADED_BY_MANUAL;
    }

    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function option()
    {
        return $this->belongsTo(Option::class);
    }
}
