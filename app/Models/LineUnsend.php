<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineUnsend extends Model
{
    protected $fillable = [
        'message_id',
        'user_id',
        'group_id',
        'unsent_at'
    ];
}