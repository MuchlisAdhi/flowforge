<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepRun extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'run_id',
        'step_id',
        'step_name',
        'step_type',
        'status',
        'attempt',
        'output',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'output' => 'array',
        'attempt' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'run_id');
    }

    public function getDurationMs(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return (int) $this->started_at->diffInMilliseconds($this->completed_at);
    }
}
