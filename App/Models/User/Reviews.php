<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

class Reviews extends Model
{
    public function client() {
        return $this->belongsTo(User::class, 'client_id', 'id');
    }

    public function assistant() {
        return $this->belongsTo(User::class, 'assistant_id', 'id');
    }
}
