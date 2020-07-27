<?php

namespace App\Models\User;

use App\Models\Common\Status;
use App\Models\Shop\Listing;
use Illuminate\Database\Eloquent\Model;

class UserStatusHistory extends Model
{
    public function status() {
        return $this->belongsTo(Status::class);
    }

    public function client() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function assistant() {
        return $this->belongsTo(User::class, 'assistant_id', 'id');
    }

    public function task() {
        return $this->belongsTo(Listing::class, 'listing_id', 'id');
    }

    public static function getUsersHistoryStatus($requestListingsHistoryFromToDate, $sorting = 'desc') {
        return self::selectRaw('date, case when status_id = 1 then count(status_id) end as status_1, case when status_id = 2 then count(status_id) end as status_2, case when status_id = 3 then count(status_id) end as status_3, case when status_id = 4 then count(status_id) end as status_4, case when status_id = 5 then count(status_id) end as status_5, case when status_id = 6 then count(status_id) end as status_6, case when status_id = 7 then count(status_id) end as status_7, case when status_id = 8 then count(status_id) end as status_8')
            ->whereRaw($requestListingsHistoryFromToDate)
            ->groupBy('date', 'status_id')
            ->orderBy('date', $sorting)
            ->get();
    }
}
