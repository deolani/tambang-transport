<?php

namespace App\Models;

use App\Imports\ImporterRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $guarded = [];

    protected $casts = ['errors' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        $class = ImporterRegistry::find($this->type);

        return $class ? $class::label() : $this->type;
    }
}
