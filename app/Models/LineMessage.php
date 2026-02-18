<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineMessage extends Model
{
    protected $fillable = [
        'message_id',
        'type',
        'user_id',
        'group_id',
        'room_id',
        'text',
        'file_url',
        'file_type',
        'unsent_at',
        'user_name'
    ];
}