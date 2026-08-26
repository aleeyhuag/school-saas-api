<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtQuestion extends Model
{
    protected $fillable = ['cbt_exam_id', 'question_text', 'topic', 'marks', 'position'];
    protected $casts = ['marks' => 'decimal:2'];
    public function exam(): BelongsTo { return $this->belongsTo(CbtExam::class, 'cbt_exam_id'); }
    public function options(): HasMany { return $this->hasMany(CbtQuestionOption::class)->orderBy('position'); }
}
