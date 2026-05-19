<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowVersion extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'workflow_id',
        'version',
        'definition',
        'timeout_seconds',
        'change_note',
        'created_by',
    ];

    protected $casts = [
        'definition' => 'array',
        'version' => 'integer',
        'timeout_seconds' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function getSteps(): array
    {
        return $this->definition['steps'] ?? [];
    }
}
