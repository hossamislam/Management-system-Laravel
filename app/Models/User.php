<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
     use HasApiTokens, HasFactory, Notifiable;
     
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];
     protected $hidden = [
        'password',
        'remember_token',
    ];
     protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
      public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function createdTasks()
    {
        return $this->hasMany(Task::class, 'created_by');
    }
}
