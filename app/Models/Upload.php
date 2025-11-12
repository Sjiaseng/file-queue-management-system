<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'file_path',
        'file_hash',
        'status',
        'total_rows',
        'processed_rows',
        'error_message',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function getUploadedAtAttribute()
    {
        return $this->created_at->format('n-j-y g:ia');
    }
}