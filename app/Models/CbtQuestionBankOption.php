<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtQuestionBankOption extends Model
{
    protected $fillable = ['cbt_question_bank_id', 'option_text', 'is_correct', 'position'];
    protected $casts = ['is_correct' => 'boolean'];
    public function question(): BelongsTo { return $this->belongsTo(CbtQuestionBank::class, 'cbt_question_bank_id'); }
}
