<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    protected $table = 'role_user';
    protected $fillable = ['user_id','name'];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function role() {
        return $this->belongsTo(Role::class);
    }
}
