<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RoomToken extends Model
{
    protected $fillable = ['lesson_id', 'token', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
