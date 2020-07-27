<?php

namespace App\Models\Shop;

use App\Models\Common\Status;
use Illuminate\Database\Eloquent\Model;

class ListingStatusHistory extends Model
{
    public function status() {
        return $this->belongsTo(Status::class);
    }

    public static function getListingsHistoryStatus($requestListingsHistoryFromToDate, $sorting = 'desc') {
        return self::selectRaw('date, case when status_id = 1 then count(status_id) end as status_1, case when status_id = 2 then count(status_id) end as status_2, case when status_id = 3 then count(status_id) end as status_3, case when status_id = 4 then count(status_id) end as status_4, case when status_id = 6 then count(status_id) end as status_6, case when status_id = 7 then count(status_id) end as status_7')
            ->whereRaw($requestListingsHistoryFromToDate)
            ->groupBy('date', 'status_id')
            ->orderBy('date', $sorting)
            ->get();
    }

    public static function getPromoListingsHistoryStatus($requestListingsHistoryFromToDate, $sorting = 'desc') {
        return self::selectRaw('date, case when status_id = 5 then count(status_id) end as status_5, case when status_id = 8 then count(status_id) end as status_8')
            ->whereRaw($requestListingsHistoryFromToDate)
            ->groupBy('date', 'status_id')
            ->orderBy('date', $sorting)
            ->get();
    }
}
