<?php

namespace App\Models\User;

use App\Models\Common\EmailNotificationsHistories;
use App\Models\Common\ListingChats;
use App\Models\Common\Locations;
use App\Models\Shop\BiddingTasks;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Config;
use App\Models\Shop\Listing;
use App\Helpers\UserHelper;
use App\Helpers\GeneralHelper;

class User extends Authenticatable
{
    use Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
    
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function verifyUser()
    {
        return $this->hasOne(VerifyUser::class, 'user_id', 'id');
    }
    
    public function assignRole($role) {
        $userRole = UserRole::firstOrCreate(['user_id' => $this->id]);
        $clientRole = Role::where('name', $role)->first();
        $userRole->role_id = $clientRole->id;
        $userRole->save();
    }
    
    public function getRole() {
        $userRole = UserRole::firstOrCreate(['user_id' => $this->id]);
        return $userRole->role()->first()->getName();
    }

    /**
     * Check multiple roles
     * @param array $roles
     */
    public function hasAnyRole($roles) {
        return null !== $this->roles()->whereIn('name', $roles)->first();
    }

    /**
     * Check one role
     * @param string $role
     */
    public function hasRole($role) {
        return null !== $this->roles()->where('name', $role)->first();
    }
    
    public function listings() {
        return $this->hasMany(Listing::class);
    }

    public function assistantListing() {
        return $this->hasMany(Listing::class, 'id', 'assistant_id');
    }

    public function userCanceledTask() {
        return $this->hasMany(CanceledTasks::class, 'id', 'user_id');
    }

    public function userBalancePayments() {
        return $this->hasMany(BalancePayments::class, 'id', 'user_id');
    }

    public function clientReview() {
        return $this->hasMany(Listing::class, 'id', 'client_id');
    }

    public function assistantReview() {
        return $this->hasMany(Listing::class, 'id', 'assistant_id');
    }

    public function clientBidding() {
        return $this->hasMany(BiddingTasks::class, 'id', 'client_id');
    }

    public function clientHistory() {
        return $this->hasMany(UserStatusHistory::class, 'id', 'user_id');
    }

    public function assistantHistory() {
        return $this->hasMany(UserStatusHistory::class, 'id', 'assistant_id');
    }

    public function assistantNotification() {
        return $this->hasMany(UserNotifications::class, 'id', 'assistant_id');
    }

    public function assistantPayment() {
        return $this->hasMany(Listing::class, 'id', 'assistant_id');
    }

    public function seenPhone() {
        return $this->hasMany(UserSeenPhones::class, 'id', 'phone_owner_id');
    }
    
    public function getListingsWithoutListing($listing_id, $limit = 5) {
        return Listing::where(
                    [
                        ['user_id', '=',$this->id],
                        ['id', '<>', $listing_id],
                    ]
                )
                ->limit($limit)
                ->orderBy('updated_at', 'DESC')->get();
    }
    
    
    public function getUserProfilePicture() {
        $pictureName = $this->profile_pic;

        if(!$pictureName) {
            return UserHelper::renderDefaultUserAvatar();
        }
        

        if(UserHelper::stringContainsLink($pictureName)) {
            return $pictureName;
        } else {
            return '<img class="user-picture" src="'. Config::get('constants.paths.user_images_url') . $pictureName .'">';
        }
        
        return UserHelper::renderDefaultUserAvatar();
    }
    
    public function getUserProfileType() {
        $profiles = [
            'person'      => 'частно лице',
            'assistant'   => 'частно лице',
            'admin'       => 'частно лице',
            'business'    => 'фирма',
            'super admin' => 'фирма',
        ];

        $userType = $this->profile_type;
        if(array_key_exists($userType, $profiles)) {
            return $profiles[$this->profile_type];
        }

        return false;
    }
    
    public function getRegisteredDate() {
        return $this->created_at->day . ' ' . GeneralHelper::getMonthName($this->created_at->month) . ' ' . $this->created_at->year;
    }
    
    public function getHiddenPhone() {
        if ($this->phone) {
            return GeneralHelper::getHiddenPhone($this->phone);
        } else {
            return '';
        }
    }
    
    public function getHiddenSkype() {
        if ($this->skype) {
            return GeneralHelper::getHiddenPhone($this->skype);
        } else {
            return '';
        }        
    }

    public function senderListingChat() {
        return $this->hasMany(ListingChats::class, 'id', 'sender_id');
    }

    public function recipientListingChat() {
        return $this->hasMany(ListingChats::class, 'id', 'recipient_id');
    }

    public function location() {
        return $this->belongsTo(Locations::class, 'location_id', 'id_location');
    }

    private static function checkHasEmailsByUserIDAndType($user, $type, $taskID = 0) {
        if($taskID > 0) {
            $hasEmails = EmailNotificationsHistories::where('user_id', $user)->where('type_email', $type)->where('task_id', $taskID)->count();
        } else {
            $hasEmails = EmailNotificationsHistories::where('user_id', $user)->where('type_email', $type)->count();
        }

        if($hasEmails > 0) {
            return true;
        }

        return false;
    }

    public function getName() {
        $userLastName = $this->last_name;
        $userName     = $this->first_name .' '. ucfirst(mb_substr($userLastName, 0, 1, 'utf-8')) .'.';

        return $userName;
    }
}
