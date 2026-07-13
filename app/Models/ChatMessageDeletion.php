<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessageDeletion extends Model
{
    protected $fillable = ['message_id', 'user_id'];
}
