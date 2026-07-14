<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectMembership extends Pivot
{
    protected $table = 'users_to_projects';

    /**
     * users_to_projects has its own auto-incrementing primary key.
     */
    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'project_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
