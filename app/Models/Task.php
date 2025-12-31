<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
        use HasFactory;

     protected $fillable = [
        'title',
        'description',
        'status',
        'due_date',
        'assigned_to',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dependencies()
    {
        return $this->belongsToMany(
            Task::class,
            'task_dependencies',
            'task_id',
            'depends_on_task_id'
        )->withTimestamps();
    }

    public function dependents()
    {
        return $this->belongsToMany(
            Task::class,
            'task_dependencies',
            'depends_on_task_id',
            'task_id'
        )->withTimestamps();
    }

    public function hasPendingDependencies(): bool
    {
        return $this->dependencies()
            ->where('status', '!=', 'completed')
            ->exists();
    }

    public function canBeCompleted(): bool
    {
        return !$this->hasPendingDependencies();
    }
}

