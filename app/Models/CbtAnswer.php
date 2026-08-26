<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtAnswer extends Model
{
    protected $fillable = ['cbt_attempt_id', 'cbt_question_id', 'cbt_question_option_id'];
    public function attempt(): BelongsTo { return $this->belongsTo(CbtAttempt::class, 'cbt_attempt_id'); }
    public function question(): BelongsTo { return $this->belongsTo(CbtQuestion::class, 'cbt_question_id'); }
    public function option(): BelongsTo { return $this->belongsTo(CbtQuestionOption::class, 'cbt_question_option_id'); }
}
