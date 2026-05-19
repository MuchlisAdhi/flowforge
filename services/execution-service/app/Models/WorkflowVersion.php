<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorkflowVersion extends Model
{
    use HasUuids;

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
}
