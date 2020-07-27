<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

class UserSeenPhones extends Model
{
    public function owner() {
        return $this->belongsTo(User::class, 'phone_owner_id', 'id');
    }
}
