<?php

namespace App\Models\Shop;

use App\Helpers\UserHelper;
use App\Models\Common\ListingChats;
use App\Models\Common\Locations;
use App\Models\User\UserNotifications;
use App\Models\User\UserStatusHistory;
use Illuminate\Database\Eloquent\Model;
use \App\Models\User\User;
use App\Helpers\GeneralHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Common\Watching;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use App\Models\Common\Status;


class Listing extends Model {

    use Sluggable;
    use SluggableScopeHelpers;

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable()
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

    public function getPrice() {
        if($this->price_type == 1) {
            return $this->currency->formatPrice($this->price);
        } else {
            return Config::get('constants.price_type')[$this->price_type];
        }
    }

    public function getName() {
        return $this->name;
    }

    public function getDefaultPicture() {
        if ($this->picture) {
            if (file_exists(Config::get('constants.paths.listing_images_path') . $this->picture)) {
                return $this->picture;
            } else {
                return 'default.png';
            }
        } else {
            $picture = Picture::where('listing_id', $this->id)->first();

            if ($picture) {
                return $picture->name;
            } else {
                return 'default.png';
            }
        }
    }

    public function getDefaultPicturePath() {
        return Config::get('constants.paths.listing_images_url') . $this->getDefaultPicture();
    }
    
    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function subCategory() {
        return $this->belongsTo(SubCategory::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function assistant() {
        return $this->belongsTo(User::class, 'assistant_id', 'id');
    }

    public function pictures() {
        return $this->hasMany(Picture::class);
    }

    public function currency() {
        return $this->belongsTo(Currency::class);
    }

    public function location() {
        return $this->belongsTo(Locations::class, 'location_id', 'id_location');
    }

    public function status() {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }

    public function historyStatus() {
        return $this->hasMany(UserStatusHistory::class, 'id', 'listing_id');
    }

    public function notification() {
        return $this->hasMany(UserNotifications::class, 'id', 'task_id');
    }

    public function taskPayment() {
        return $this->hasMany(Listing::class, 'id', 'task_id');
    }

    public function getCreatedTime() {
        return $this->created_at->format('h:i');
    }

    public function getCreatedDate() {
        $created    = new Carbon($this->created_at);
        $now        = Carbon::now();
        $difference = ($created->diff($now)->days < 1) ? 'Днес' : $this->created_at->day . ' ' . GeneralHelper::getMonthName($this->created_at->month);

        return $difference;
    }

    public function getHiddenContactPhone() {
        ($this->contact_number) ? $return_response = GeneralHelper::getHiddenPhone($this->contact_number) : $return_response = '';
        return $return_response;
    }

    public function getDescriptionTemplate() {
        return 'includes.listings.view_descriptions.' . $this->category->tag . '.' . $this->subcategory->tag;
    }

    public function getImages() {
        $images      = [];
        $images_name = [];

        if ($this->picture == null) {
            for ($i = 1; $i < count($this->pictures); $i++) {
                $images[] = $this->pictures[$i];
            }
        } else {
            foreach ($this->pictures as $pic) {
                if ($pic->name != $this->picture) {
                    $images[] = $pic;
                }
            }
        }

        foreach ($images as $pic) {
            if(file_exists(Config::get('constants.paths.listing_images_path') . $pic->name)) {
                $images_name[] = $pic->name;
            } else {
                $images_name[] = 'default.png';
            }
        }

        return $images_name;
    }

    public function isWatched() {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $watch = Watching::where([
                    ['listing_id', '=', $this->id],
                    ['user_id', '=', $user->id]
                ])->count();

        return $watch ? true : false;
    }

    public function isMineListing() {

        $user = Auth::user();
        if (!$user) {
            return false;
        }

        return $this->user->id == $user->id;
    }
    
    public function watches() {
        return $this->hasMany(Watching::class);
    }

    public function updatePageViews() {
        if (Auth::user()) {
            if (Auth::user()->id != $this->user_id) {
                $this->page_views++;
                $this->save();
            }
        } else {
            $this->page_views++;
            $this->save();
        }
    }

    public static function getAllListingsCount() {
        $count = count(self::get());

        return $count;
    }

    public static function getListingsCount($searchCategory, $boolCat, $statusID, $operator = '=') {
        $count = self::selectRaw('count('. $searchCategory .') as '. $searchCategory)
            ->where($searchCategory, $boolCat)
            ->where('status_id', $operator, $statusID)
            ->groupBy($searchCategory)
            ->get();

        return empty($count[0]) ? 0 : $count[0][$searchCategory];
    }

    public static function getMyAllListings() {
        if (Auth::user()) {
            return self::where('user_id', Auth::user()->id)
                ->where('approve', true)
                ->orderBy('promoted', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            return redirect('/login');
        }
    }
    
    public static function getMyListings() {
        if (Auth::user()) {
            return self::where('user_id', Auth::user()->id)
                ->where('approve', true)
                ->orderBy('promoted', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            return redirect('/login');
        }
    }

    public static function getMyPromotedListings() {
        if (Auth::user()) {
            return self::where('user_id', Auth::user()->id)
                ->where('approve', true)
                ->where('promoted', true)
                ->orderBy('updated_at', 'asc')
                ->paginate(10);
        } else {
            return redirect('/login');
        }
    }

    public static function getMyActiveListings() {
        if (Auth::user()) {
            return self::getActiveInactiveListingsProfile(true);
        } else {
            return redirect('/login');
        }
    }

    public static function getMyWaitingForApproveListings() {
        if (Auth::user()) {
            return self::where('user_id', Auth::user()->id)
                ->where('approve', false)
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            return redirect('/login');
        }
    }

    public static function getMyInactiveListings() {
        if (Auth::user()) {
            return self::getActiveInactiveListingsProfile(false);
        } else {
            return redirect('/login');
        }
    }

    private static function getActiveInactiveListingsProfile ($active_inactive) {
        return self::where('user_id', Auth::user()->id)
            ->where('approve', true)
            ->where('active', $active_inactive)
            ->orderBy('promoted', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public static function  getMyAllListingsCount() {
        return self::getMyAllOrApproveListingsCount(true);
    }

    public static function  getMyPromotedListingsCount() {
        if (Auth::user()) {
            return self::selectRaw('count(*) as listings_count')
                ->where('user_id', Auth::user()->id)
                ->where('promoted', true)
                ->where('approve', true)
                ->get();
        } else {
            return redirect('/login');
        }
    }

    public static function  getMyActiveListingsCount() {
        return self::getMyActiveInactiveListingsCount(true);
    }

    public static function  getMyWaitingForApproveListingsCount() {
        return self::getMyAllOrApproveListingsCount(false);
    }

    public static function  getMyInactiveListingsCount() {
        return self::getMyActiveInactiveListingsCount(false);
    }

    private static function getMyAllOrApproveListingsCount($approve_listings) {
        if (Auth::user()) {
            return self::selectRaw('count(*) as listings_count')
                ->where('user_id', Auth::user()->id)
                ->where('approve', $approve_listings)
                ->get();
        } else {
            return redirect('/login');
        }
    }

    private static function getMyActiveInactiveListingsCount($active_listings) {
        if (Auth::user()) {
            return self::selectRaw('count(*) as listings_count')
                ->where('user_id', Auth::user()->id)
                ->where('approve', true)
                ->where('active', $active_listings)
                ->get();
        } else {
            return redirect('/login');
        }
    }

    public static function searchMyListings($search, $listing_active = null) {
        if (Auth::user()) {
            return self::where('user_id', Auth::user()->id)
                ->whereRaw("case when (? = true or ? = false) then active = ? else (active = true or active = false) end", [$listing_active, $listing_active, $listing_active])
                ->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', '%' . $search . '%');
                    $q->orWhere('description', 'ILIKE', '%' . $search . '%');
                })
                ->orderBy('promoted', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            return redirect('/login');
        }
    }

    public static function searchMyPromotedListings($search) {
        if (Auth::user()) {
            return self::where('user_id', Auth::user()->id)
                ->where('promoted', true)
                ->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', '%' . $search . '%');
                    $q->orWhere('description', 'ILIKE', '%' . $search . '%');
                })
                ->orderBy('updated_at', 'asc')
                ->paginate(10);
        } else {
            return redirect('/login');
        }
    }

    public static function searchMyListingsWaitingForApprove($search) {
        if (Auth::user()) {
            return self::where('user_id', Auth::user()->id)
                ->where('approve', false)
                ->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', '%' . $search . '%');
                    $q->orWhere('description', 'ILIKE', '%' . $search . '%');
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        } else {
            return redirect('/login');
        }
    }

    public function checkIfListingBelongsToLoggedUser() {
        if(Auth::user() != null) {
            return $this->user_id == Auth::user()->id;
        } else {
            return false;
        }
    }

    public function listingChat() {
        return $this->hasMany(ListingChats::class, 'id', 'listing_id');
    }

    public static function searchForAllFilterListing($search_filter_fields) {
        $filterPriceToAscending  = false;
        $filterPriceToDescending = false;

        $filter_listings = self::select(DB::raw('listings.*, categories.name as cat_name, categories.tag, sub_categories.name as sub_cat_name, locations.region, locations.name as location'))
            ->join('categories', 'categories.id', '=', 'listings.category_id')
            ->join('sub_categories', 'sub_categories.id', '=', 'listings.sub_category_id')
            ->join('locations', 'locations.id_location', '=', 'listings.location_id')
            ->where('active', true);

        if($search_filter_fields != null) {
            foreach ($search_filter_fields as $key_price => $val_price) {
                if($key_price == 'price_from') {
                    if(strlen($val_price) > 0) {
                        $price_from = $val_price;
                    }
                }

                if($key_price == 'price_to') {
                    if(strlen($val_price) > 0) {
                        $price_to = $val_price;
                    }
                }
            }

            if(isset($price_from)) {
                $filter_listings->where('price', '>', $price_from);
            }

            if(isset($price_to)) {
                $filter_listings->where('price', '<', $price_to);
            }

            $field_count = AttributeField::count();

            foreach ($search_filter_fields as $key => $val) {
                if($key == 'category') {
                    $filter_listings->where('listings.category_id', $val);
                }

                if($key == 'sub_category') {
                    $filter_listings->where('listings.sub_category_id', $val);
                }

                if($key == 'attribute') {
                    $filter_listings->where('listings.sub_category_attribute_id', $val);
                }

                if($key == 'filter_name') {
                    $filter_listings->where('listings.name', 'ILIKE', '%'.$val.'%');
                }

                if($key == 'region_id') {
                    $allLocationsToThisRegion = Locations::where('region_id', $val)->get();

                    $filter_listings->where(function($q) use($allLocationsToThisRegion) {
                        foreach ($allLocationsToThisRegion as $location) {
                            $q->orWhere('location_id', $location->id_location);
                        }
                    });
                }

                if($key == 'location') {
                    $filter_listings->where('location_id', $val);
                }

                if($key == 'private_person_listing' || $key == 'business_listing') {
                    $filter_listings->where('listing_type', $val);
                }

                if($key == 'negotiate_listing' || $key == 'free_listing') {
                    $filter_listings->where('price_type', $val);
                }

                if($key == 'brand') {
                    $filter_listings->where('motor_vehicle_brand', $val);
                }

                if($key == 'model') {
                    $filter_listings->where('motor_vehicle_model_id', $val);
                }

                if(is_int($key)) {
                    $filter_listings->whereRaw("extra_fields->'fields'->>'$key' = '$val' ");
                }

                for($i = 0; $i < $field_count; $i++) {
                    if($key == $i.'-field') {
                        $filter_listings->whereRaw("extra_fields->'fields'->>'$key' = '$val' ");
                    }

                    if($key == $i.'-field-from') {
                        $filter_listings->whereRaw("extra_fields->'fields'->>'$i-field' >= '$val' ");
                    }

                    if($key == $i.'-field-to') {
                        $filter_listings->whereRaw("extra_fields->'fields'->>'$i-field' <= '$val' ");
                    }
                }

                if($key == 'ascending') {
                    $filterPriceToAscending = true;
                }

                if($key == 'descending') {
                    $filterPriceToDescending = true;
                }
            }
        }

        $filter_listings->orderBy('listings.promoted', 'desc');

        if($filterPriceToAscending) {
            $filter_listings->orderBy('listings.price', 'asc');
        }

        if($filterPriceToDescending) {
            $filter_listings->orderBy('listings.price', 'desc');
        }

        return $filter_listings->get();
    }

    public function getUrl() {
        return url("/o/{$this->slug}");
    }

    public static function archivingExpiredTasks() {
        $expiredTasks = Listing::whereRaw('assistant_id IS NULL')
            ->where('end_task_date', '<', date('Y-m-d H:i:s'))
            ->where('approve', true)
            ->where('status_id', '<', config('constants.statuses.completed_by_admin'))
            ->get();

        $expiredTasksIDs   = [];
        $archivedStatus    = config('constants.statuses.archived');
        $expiredTaskStatus = config('constants.statuses.expired_task');

        foreach ($expiredTasks as $expiredTask) {
            $expiredTask->status_id = $archivedStatus;
            $expiredTask->save();

            $taskID   = $expiredTask->id;
            $clientID = $expiredTask->user_id;

            $newListingStatusHistories = new ListingStatusHistory();
            $newListingStatusHistories->listing_id = $taskID;
            $newListingStatusHistories->status_id  = $archivedStatus;
            $newListingStatusHistories->date       = $expiredTask->updated_at;
            $newListingStatusHistories->client_id  = $clientID;
            $newListingStatusHistories->save();

            $newUserNotification = new UserNotifications();
            $newUserNotification->user_id      = $clientID;
            $newUserNotification->user_type    = 'client';
            $newUserNotification->status_id    = $expiredTaskStatus;
            $newUserNotification->task_id      = $taskID;
            $newUserNotification->save();

            $expiredTasksIDs[$taskID] = $taskID;

            $uName          = $expiredTask->user->getName();
            $subject        = 'Задачата ви изтече без избран Асистент';
            $taskTitle      = $expiredTask->name;
            $messageContent = '<p>Здравейте'. $uName .',
                <br />Срокът за изпълнение на задачата Ви „'. $taskTitle .'“ изтече. Понеже не сте избрали Асистент за нейното извършване, задачата Ви бе преместена в архива.
                <br /><a href="'. config('app.url') .'/profile-archive" title="връзка към архива" target="_blank">връзка към архива</a>
                <br />С уважение, 
                <br />Екипът на AssistMe
            </p>';

            GeneralHelper::sendDynamicalMail($uName, $expiredTask->user->email, $subject, $messageContent);
            UserHelper::addNewEmailNotificationHistories($clientID, 'expired_task_client', $taskID);
        }

        $getUserStatusHistory = UserStatusHistory::where(function ($qStatuses) {
            $qStatuses->where('status_id', config('constants.statuses.accepted_task_by_assistant'));
            $qStatuses->orWhere('status_id', config('constants.statuses.assistant_bidding_task'));
        });

        $getUserStatusHistory->where(function ($qAssistantID) use ($expiredTasksIDs) {
            foreach ($expiredTasksIDs as $expiredTasksID) {
                $qAssistantID->orWhere('listing_id', $expiredTasksID);
            }
        });

        $getExpiredTasksApplyAssistants = $getUserStatusHistory->get();

        foreach ($getExpiredTasksApplyAssistants as $getExpiredTasksApplyAssistant) {
            $assistantID = $getExpiredTasksApplyAssistant->assistant_id;
            $ushTaskID   = $getExpiredTasksApplyAssistant->listing_id;

            $newUserNotification = new UserNotifications();
            $newUserNotification->user_id      = $assistantID;
            $newUserNotification->user_type    = 'assistant';
            $newUserNotification->status_id    = $expiredTaskStatus;
            $newUserNotification->task_id      = $ushTaskID;
            $newUserNotification->assistant_id = $assistantID;
            $newUserNotification->save();

            $assistantName = $getExpiredTasksApplyAssistant->assistant->getName();
            $toSubject     = 'Изтече срока за задача, за която сте кандидатствали';
            $ushTaskTitle  = $getExpiredTasksApplyAssistant->task->name;
            $toMsgContent  = '<p>Здравейте'. $assistantName .',
                <br />Кандидатствахте за задачата „'. $ushTaskTitle .'“. Срокът за нейното изпълнение изтече, преди някой Aсистент да бъде избран. Има още много задачи, за които можете да кандидатствате. Отворете профила си, за да ги разгледате.
                <br />С уважение, 
                <br />Екипът на AssistMe
            </p>';

            GeneralHelper::sendDynamicalMail($assistantName, $getExpiredTasksApplyAssistant->assistant->email, $toSubject, $toMsgContent);
            UserHelper::addNewEmailNotificationHistories($assistantID, 'expired_task_assistant', $ushTaskID);
        }
    }
}
