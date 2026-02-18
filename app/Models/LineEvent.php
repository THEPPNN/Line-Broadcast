<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineEvent extends Model
{
    protected $fillable = [
        'event_id',
        'type',
        'source_type',
        'user_id',
        'group_id',
        'room_id',
        'timestamp',
        'raw'
    ];
}