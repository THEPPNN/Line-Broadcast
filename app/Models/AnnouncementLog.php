<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementLog extends Model
{
    protected $fillable = [
        'announcement_id',
        'group_id',
        'status',
        'response',
        'error',
        'sent_at'
    ];
}