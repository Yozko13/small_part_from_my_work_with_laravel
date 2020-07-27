<?php

namespace App\Models\User;

use App\Models\Shop\Listing;
use Illuminate\Database\Eloquent\Model;

class UserNotifications extends Model
{
    public function assistant() {
        return $this->belongsTo(User::class, 'assistant_id', 'id');
    }

    public function task() {
        return $this->belongsTo(Listing::class, 'task_id', 'id');
    }
}
