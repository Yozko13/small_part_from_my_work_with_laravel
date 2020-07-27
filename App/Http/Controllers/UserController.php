<?php

namespace App\Http\Controllers;

use App\Helpers\GeneralHelper;
use App\Helpers\UserHelper;
use App\Models\Common\Locations;
use App\Models\Shop\Category;
use App\Models\Shop\Listing;
use App\Models\User\ProposalTypeCategories;
use App\Models\User\UserNotifications;
use App\Models\User\UserSeenPhones;
use Carbon\Carbon;
use App\Models\Shop\SubCategory;
use App\Models\User\BalanceHistories;
use App\Models\User\BalancePayments;
use App\Models\User\Reviews;
use App\Models\User\User;
use App\Models\User\UserCategories;
use App\Models\User\UserStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){}

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */

    private static function getAssistantStatusHistories($userID) {
        return UserStatusHistory::where('assistant_id', $userID)
            ->where(function ($uStatusQuery) {
                $uStatusQuery->where('status_id', config('constants.statuses.accepted_task_by_assistant'));
                $uStatusQuery->orWhere('status_id', config('constants.statuses.assistant_bidding_task'));
            })
            ->get();
    }

    public static function getUserNewNotificationsCount($userID) {
        return UserNotifications::where('user_id', $userID)->where('user_seen', false)->count();
    }

    private function getUserNotifications($userID, $takeNotifications = 50) {
        return UserNotifications::where('user_id', $userID)->take($takeNotifications)->orderBy('user_seen')->orderBy('updated_at', 'desc')->get();
    }

    private function getAssistantReviewCount($user) {
        return Reviews::where('assistant_id', $user->id)->where('created_at', '>', $user->date_last_seen_new_reviews)->count();
    }

    private function getAssistantTasks($userID, $chosenTasksFilterOption = 0, $chosenRadiusFilterOption = 0) {
        $listingsRadius = 15000;

        if($chosenRadiusFilterOption > 0) {
            $listingsRadius = $chosenRadiusFilterOption;
        }

        $currentDate       = Carbon::now();
        $getApprovedStatus = config('constants.statuses.approved');

        $assistantListings = DB::table(DB::raw('users, listings'))
            ->selectRaw('DISTINCT listings.id, listings.id')
            ->where(function ($qWithGeom) use ($userID, $listingsRadius, $currentDate, $getApprovedStatus, $chosenTasksFilterOption) {
                $qWithGeom->where('users.id', $userID);
                $qWithGeom->whereRaw('ST_Distance(ST_Transform(users.geom,32635), ST_Transform(listings.geom,32635)) <= ' . $listingsRadius);
                $qWithGeom->where('listings.end_task_date', '>', $currentDate);
                $qWithGeom->where('listings.approve', true);
                $qWithGeom->where('listings.status_id', $getApprovedStatus);
                $qWithGeom->whereRaw('listings.assistant_id IS NULL');

                if($chosenTasksFilterOption < config('constants.filters.online')) {
                    $qWithGeom->orWhere(function ($qWithoutGeom) use ($userID, $listingsRadius, $currentDate, $getApprovedStatus) {
                        $qWithoutGeom->where('users.id', $userID);
                        $qWithoutGeom->where('listings.end_task_date', '>', $currentDate);
                        $qWithoutGeom->where('listings.approve', true);
                        $qWithoutGeom->where('listings.status_id', $getApprovedStatus);
                        $qWithoutGeom->whereRaw('listings.assistant_id IS NULL');
                        $qWithoutGeom->whereRaw('listings.geom IS NULL');
                    });
                }
            })
            ->get();

        $listings = Listing::where(function ($qlID) use ($assistantListings) {
            if($assistantListings->count() > 0) {
                foreach ($assistantListings as $assistantListing) {
                    $qlID->orWhere('listings.id', $assistantListing->id);
                }
            } else {
                $qlID->orWhere('listings.id', 0);
            }
        });

        return $listings->orderBy('updated_at', 'desc')->get();
    }

    private function getPendingAssistantTasks($userID) {
        return Listing::selectRaw('listings.*, lc.id as chat_id, bt.status_id as history_status_id')
            ->leftJoin('bidding_tasks as bt', 'bt.task_id', '=', 'listings.id')
            ->leftJoin('listing_chats as lc', function($joinLC) use ($userID) {
                $joinLC->on('lc.listing_id', '=', 'listings.id');
                $joinLC->on(function ($srq) use ($userID) {
                    $srq->on('lc.sender_id', '=', DB::raw("'". $userID ."'"));
                    $srq->orOn('lc.recipient_id', '=', DB::raw("'". $userID ."'"));
                });
            })
            ->where('listings.end_task_date', '>', date('Y-m-d H:i:s'))
            ->where('listings.approve', true)
            ->where('listings.status_id', config('constants.statuses.approved'))
            ->where('bt.assistant_id', '=', $userID)
            ->where(function ($btq) use ($userID) {
                $btq->where('bt.status_id', '=', config('constants.statuses.accepted_task_by_assistant'));
                $btq->orWhere('bt.status_id', '=', config('constants.statuses.assistant_bidding_task'));
            })
            ->groupBy('listings.id', 'lc.id', 'bt.status_id')
            ->orderBy('listings.updated_at', 'desc')
            ->get();
    }

    private function getAssistantInProgressTasks($userID) {
        return Listing::selectRaw('listings.*, lc.id as chat_id')
            ->leftJoin('listing_chats as lc', 'lc.listing_id', '=', 'listings.id')
            ->where('listings.approve', true)
            ->where('listings.status_id', '<', config('constants.statuses.archived'))
            ->where('listings.assistant_id', $userID)
            ->where(function ($lcq) use ($userID) {
                $lcq->whereNull('lc.sender_id');
                $lcq->orWhere('lc.sender_id', $userID);
                $lcq->orWhere('lc.recipient_id', $userID);
            })
            ->orderBy('listings.status_id', 'asc')
            ->orderBy('listings.updated_at', 'desc')
            ->get();
    }

    private function getClientTasks($userID) {
        return Listing::leftJoin('listing_chats as lc', 'listings.id', '=', 'lc.listing_id')
            ->leftJoin('user_status_histories as ush', 'listings.id', '=', 'ush.listing_id')
            ->selectRaw('listings.*, count(DISTINCT(lc.id)) as sent_messages_count, SUM(CASE WHEN "ush"."status_id" = '.
                config('constants.statuses.accepted_task_by_assistant') .' or "ush"."status_id" = '.
                config('constants.statuses.assistant_bidding_task') .' THEN 1 ELSE 0 END) as has_offer')
            ->where('listings.user_id', $userID)
            ->where('listings.approve', true)
            ->where('listings.status_id', '<', config('constants.statuses.completed_by_admin'))
            ->where('ush.user_id', $userID)
            ->groupBy('listings.id')
            ->orderBy('listings.updated_at', 'desc')
            ->get();
    }

    private function assistantViewProfile($user, $viewType) {
        $userID   = $user->id;
        $userRole = $user->getRole();

        $userCategories      = UserCategories::where('user_id', $userID)->get();
        $assistantCategories = UserCategories::selectRaw('category_id, count(category_id) as count_cat')
            ->where('user_id', $userID)
            ->groupBy('category_id')
            ->orderBy('count_cat', 'desc')
            ->get();

        if($user->chosen_all_categories) {
            $userCategories = Category::all()->sortBy('name');
        }

        $assistantListings = self::getAssistantInProgressTasks($userID);

        $balance = BalancePayments::where('user_id', $userID)->first();

        $returnData = [
            'typeUser'            => $userRole,
            'rating'              => UserHelper::getAssistantRatingByUserID($userID),
            'balance'             => $balance,
            'notificationsCount'  => self::getUserNewNotificationsCount($userID),
            'newReviewsCount'     => self::getAssistantReviewCount($user),
            'assistantCategories' => $assistantCategories,
            'userCategories'      => $userCategories
        ];

        if($viewType == 'tasks') {
            $userPendingListings = self::getPendingAssistantTasks($userID);

            if(isset($_GET['filter'])) {
                if($_GET['filter'] == 0) {
                    $assistantHeadListings = self::getAssistantTasks($userID, $_GET['filter']);
                }

                if($_GET['filter'] > 0) {
                    $assistantHeadListings = self::getAssistantTasks($userID, $_GET['filter']);
                }
            } else {
                if(isset($_GET['location'])) {
                    $radiusMeters = $_GET['location'];

                    if($radiusMeters == 0) {
                        $radiusMeters = 800000;
                    }

                    $assistantHeadListings = self::getAssistantTasks($userID, 0, $radiusMeters);
                } else {
                    $assistantHeadListings = self::getAssistantTasks($userID);
                }
            }

            $returnData['userListings']             = $assistantHeadListings;
            $returnData['userPendingListings']      = $userPendingListings;
            $returnData['userPendingListingsCount'] = $userPendingListings->count();
            $returnData['assistantListings']        = $assistantListings;
            $returnData['assistantListingsCount']   = $assistantListings->count();

            $user->date_last_seen_new_tasks = date('Y-m-d H:i:s', time());
            $user->save();
        }

        if($viewType == 'settings') {
            $returnData['chosenLocation']     = Locations::find($user->location_id);
            $returnData['userEditCategories'] = $userCategories;
        }

        if($viewType == 'reviews') {
            $reviews = Reviews::where('assistant_id', $userID)->orderBy('id', "desc")->get();

            $returnData['reviews']      = $reviews;
            $returnData['reviewsCount'] = $reviews->count();

            $user->date_last_seen_new_reviews = date('Y-m-d H:i:s', time());
            $user->save();
        }

        if($viewType == 'archive') {
            $returnData['userArchives'] = Listing::where('assistant_id', $userID)
                ->orWhere('status_id', '>', config('constants.statuses.completed_by_client'))
                ->get();
        }

        if($viewType == 'balance') {
            $returnData['clearDuty'] = false;

            if(!is_null($balance) && $balance->duty > 0) {
                if($balance->duty < $balance->earned) {
                    $balance->earned = $balance->earned - $balance->duty;
                    $balance->duty   = 0;
                    $balance->save();

                    $newUserBalanceHistories = new BalanceHistories();
                    $newUserBalanceHistories->user_id            = $userID;
                    $newUserBalanceHistories->user_money         = $balance->earned;
                    $newUserBalanceHistories->user_duty          = $balance->duty;
                    $newUserBalanceHistories->balance_payment_id = $balance->id;
                    $newUserBalanceHistories->save();

                    $statusClearAssistantDuty = config('constants.statuses.clear_assistant_duty');

                    $newUserStatusHistoriesDuty = new UserStatusHistory();
                    $newUserStatusHistoriesDuty->user_id   = $userID;
                    $newUserStatusHistoriesDuty->status_id = config('constants.statuses.clear_assistant_duty');
                    $newUserStatusHistoriesDuty->date      = date('Y-m-d H:i:s');
                    $newUserStatusHistoriesDuty->save();

                    $newUserNotification = new UserNotifications();
                    $newUserNotification->user_id   = $userID;
                    $newUserNotification->user_type = $userRole;
                    $newUserNotification->status_id = $statusClearAssistantDuty;
                    $newUserNotification->save();

                    $returnData['clearDuty'] = true;
                }
            }
        }

        if($viewType == 'notifications') {
            $returnData['userNotifications'] = self::getUserNotifications($userID);
        }

        return $returnData;
    }

    private function clientViewProfile($user, $viewType) {
        $userID   = $user->id;
        $userRole = $user->getRole();

        $userCategories['user'] = Category::leftJoin('listings as l', 'categories.id', '=', 'l.category_id')
            ->selectRaw('categories.*, count(l.category_id) as category_count')
            ->where('l.user_id', $userID)
            ->groupBy('categories.id')
            ->orderBy('category_count', 'desc')
            ->take(3)
            ->get();

        $hasCategories = $userCategories['user']->count();

        $userCategories['other'] = [];
        if($hasCategories === 2) {
            $catIdes = [];
            foreach ($userCategories['user'] as $userCategory) {
                $catIdes[] = $userCategory->id;
            }

            $userCategories['other'] = Category::where('id', '<>', $catIdes[0])->where('id', '<>', $catIdes[1])->take(1)->get();
        }

        if($hasCategories === 1) {
            $userCategories['other'] = Category::where('id', '<>', $userCategories['user'][0]->id)->take(2)->get();
        }

        if($hasCategories === 0) {
            $userCategories['other'] = Category::take(3)->get();
        }

        $returnData = [
            'typeUser'           => $userRole,
            'newTasksCount'      => 0,
            'notificationsCount' => self::getUserNewNotificationsCount($userID),
            'userCategories'     => $userCategories
        ];

        if($viewType == 'tasks') {
            $returnData['userListings'] = self::getClientTasks($userID);
        }

        if($viewType == 'settings') {
            $returnData['user']           = $user;
            $returnData['chosenLocation'] = Locations::find($user->location_id);
        }

        if($viewType == 'archive') {
            $returnData['userArchives'] = Listing::where('user_id', $userID)
                ->orWhere('status_id', '>', config('constants.statuses.completed_by_client'))
                ->get();
        }

        if($viewType == 'notifications') {
            $returnData['userNotifications'] = self::getUserNotifications($userID);
        }

        return $returnData;
    }

    public function view($viewType = 'tasks') {
        $profileViews = [
            'tasks'         => true,
            'settings'      => true,
            'reviews'       => true,
            'archive'       => true,
            'balance'       => true,
            'notifications' => true
        ];

        if(!array_key_exists($viewType, $profileViews)) {
            abort(404);
        }

        $user = Auth::user();
        if ($user) {
            $userRole = $user->getRole();
            if($userRole == 'assistant') {
                self::assistantViewProfile($user, $viewType);
            } else {
                self::clientViewProfile($user, $viewType);
            }

            $returnData['user']           = $user;
            $returnData['subProfileView'] = $viewType;
            $returnData['userRole']       = $userRole;

            return view('pages.profile.user')->with($returnData);
        } else {
            return redirect('/login');
        }
    }

    public function viewProfileSettings() {
        return view('pages.profile.settings')->with([
            'title'    => 'Профил на потребителя',
            'subtitle' => 'Тук може да управляваш настройките на своя профил',
            'active'   => 'settings'
        ]);
    }

    public function viewProfileWallet() {
        return view('pages.profile.wallet')->with([
            'title'    => 'Моят портофейл',
            'subtitle' => 'От тук може да управляваш всичко, свързано с плащания',
            'active'   => 'wallet'
        ]);
    }

    public function editPassword(Request $request) {
        $user_password = Auth::user();

        $this->validate(
            $request,
            $this->getValidationFields('password'),
            $this->getValidationMessage('password')
        );

        $user_password->password = Hash::make($request->input('password'));

        $user_password->save();

        return back()->with('successMessage', 'Новата парола беше записана успешно.');
    }

    public function editEmail(Request $request) {
        $user_email = Auth::user();

        $this->validate(
            $request,
            $this->getValidationFields('email'),
            $this->getValidationMessage('email')
        );

        $user_email->email = $request->input('email');

        $user_email->save();

        return back()->with('successMessage', 'Новият имейл беше записан успешно.');
    }

    protected function profileRecovery() {
        return view('pages.profile.recovery_profile');
    }

    protected function getValidationFields($method) {
        $fields = [
            'settings' => [
                'first_name'         => 'required|min:3|max:50|no_html',
                'last_name'          => 'required|min:3|max:50|no_html',
                'phone'              => 'required|numeric|digits:9|no_html',
                'location_id'        => 'required|numeric|no_html',
                'about'              => 'nullable|min:10|max:500|no_html',
                'qualities'          => 'nullable|min:10',
                'qualifications'     => 'nullable|min:10',
                'avatar'             => 'nullable|image|mimes:jpg,jpeg,png',
                'why_want_assistant' => 'nullable|max:500|no_html',
                'add_pdf'            => 'nullable|mimes:pdf',
            ],
            'email' => [
                'email'    => 'required|string|email|max:255|unique:users|no_html',
                'password' => 'required|check_password|min:6'
            ],
            'password' => [
                'currentPassword' => 'required|check_password|min:6',
                'password'        => 'required|confirmed|min:6',
            ]

        ];

        return $fields[$method];
    }

    protected function getValidationMessage($method) {
        $fields = [
            'settings' => [
                'first_name.required'        => 'Първото име е задължително',
                'first_name.min'             => 'Първото име трябва да бъде поне от 3 символа',
                'first_name.max'             => 'Първото име трябва да бъде по-малко от 50 символа',
                'last_name.required'         => 'Фамилията е задължителна',
                'last_name.min'              => 'Фамилията трябва да бъде поне от 3 символа',
                'last_name.max'              => 'Фамилията трябва да бъде по-малко от 50 символа',
                'name.no_html'               => 'Открити са не разрешени символи в името',
                'phone.required'             => 'Телефона е задължителен',
                'phone.numeric'              => 'Телефона приема само числа',
                'phone.digits'               => 'Телефона трябва да бъде точно от 9 числа',
                'phone.no_html'              => 'Открити са не разрешени символи в мобилният номер',
                'location_id.required'       => 'Населеното място е задължително',
                'location_id.numeric'        => 'Не сте посочили населено място',
                'location_id.no_html'        => 'Открити са не разрешени символи в полето за населено място',
                'about.min'                  => 'Полето "Добави информация за себе си" трябва да съдържа поне 10 символа',
                'about.max'                  => 'Полето "Добави информация за себе си" трябва да бъде по-малко от 500 символа',
                'about.no_html'              => 'Открити са не разрешени символи в полето "добави информация за себе си"',
                'qualities.min'              => 'Полето "Добави качествата си" трябва да съдържа поне 10 символа',
                'qualifications.min'         => 'Полето "Добави квалификациите си" трябва да съдържа поне 10 символа',
                'avatar.image'               => 'Аватара може да бъде само от тип снимка',
                'avatar.mimes'               => 'Аватара може да бъде само снимка с jpg,jpeg,png формати',
                'why_want_assistant.max'     => 'Полето "защо искате да сте асистент" трябва да бъде по-малко от 500 символа',
                'why_want_assistant.no_html' => 'Открити са не разрешени символи в полето "защо искате да сте асистент"',
                'add_pdf.mimes'              => 'CV - то може да бъде само PDF формат',
            ],
            'email' => [
                'email.required'          => 'Моля въведете нов имейл.',
                'email.unique'            => 'Съществува такъв имейл. Моля въведете друг.',
                'email.no_html'           => 'Открити са не разрешени символи в името.',
                'password.required'       => 'Моля въведете парола.',
                'password.size'           => 'Паролата трябва да бъде поне 6 симвоа.',
                'password.check_password' => 'Въведена е грешна парола при смяна на имейла.'
            ],
            'password' => [
                'currentPassword.required'       => 'Моля въведете текущата парола.',
                'currentPassword.check_password' => 'Грешна текуща парола.',
                'currentPassword.size'           => 'Текущата парола трябва да бъде поне 6 симвоа.',
                'password.required'              => 'Моля въведете нова парола.',
                'password.size'                  => 'Паролата трябва да бъде поне 6 симвоа.',
                'password.confirmed'             => 'Моля потвърдете новата парола.'
            ]

        ];

        return $fields[$method];
    }

    public function hasVisitedTask(Request $request) {
        $user = Auth::user();
        if($user && $user->getRole() == 'assistant') {
            $taskID = $request->task_id;
            $task   = Listing::find($taskID);

            if(is_null($task)) {
                abort(404);
            }

            $userID             = $user->id;
            $clientID           = $task->user_id;
            $getVisitTaskStatus = config('constants.statuses.visit_task');

            $hasVisited = UserStatusHistory::where('assistant_id', $userID)
                ->where('user_id', $clientID)
                ->where('listing_id', $taskID)
                ->where('status_id', $getVisitTaskStatus)
                ->get();

            $taskViews = $task->page_views;

            if($hasVisited->count() == 0) {
                $task->page_views = $taskViews + 1;
                $task->save();

                $newUserStatusHistory               = new UserStatusHistory();
                $newUserStatusHistory->user_id      = $clientID;
                $newUserStatusHistory->status_id    = $getVisitTaskStatus;
                $newUserStatusHistory->date         = Carbon::now();
                $newUserStatusHistory->assistant_id = $userID;
                $newUserStatusHistory->listing_id   = $taskID;
                $newUserStatusHistory->save();
            }

            return json_encode(['oldTaskVisitorsCounter' => $taskViews, 'nowTaskVisitorsCounter' => $task->page_views]);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function sentClientReview(Request $request) {
        $user = Auth::user();

        if($user && $user->getRole() == 'client') {
            $task      = Listing::find($request->task_id);
            $assistant = User::find($request->assistant_id);

            if(is_null($task) || is_null($assistant)) {
                abort(403, 'Unauthorized action.');
            }

            $request->validate([
                'rating'      => 'required',
                'review_note' => 'nullable|min:10|max:500',
            ], [
                'rating.required' => 'Не сте дали оценка на асистента',
                'review_note.min' => 'Вашето впечатление не може да бъде по - късо от 10 символа',
                'review_note.max' => 'Вашето впечатление не може да бъде по - дълго от 500 символа',
            ]);

            $userID         = $user->id;
            $taskID         = $task->id;
            $assistantID    = $assistant->id;
            $assistantName  = $assistant->getName();
            $assistantEmail = $assistant->email;
            $userAddRating  = $request->rating;

            $createReview = new Reviews();
            $createReview->task_id      = $taskID;
            $createReview->client_id    = $userID;
            $createReview->assistant_id = $assistantID;
            $createReview->rating       = $userAddRating;
            $createReview->message      = $request->review_note;
            $createReview->save();

            $clientSentReviewStatus = config('constants.statuses.client_sent_review');

            $userStatusHistories = new UserStatusHistory();
            $userStatusHistories->user_id      = $userID;
            $userStatusHistories->status_id    = $clientSentReviewStatus;
            $userStatusHistories->date         = Carbon::now();
            $userStatusHistories->assistant_id = $assistantID;
            $userStatusHistories->listing_id   = $taskID;
            $userStatusHistories->save();

            $newUserNotification = new UserNotifications();
            $newUserNotification->user_id      = $assistantID;
            $newUserNotification->user_type    = 'assistant';
            $newUserNotification->status_id    = $clientSentReviewStatus;
            $newUserNotification->task_id      = $taskID;
            $newUserNotification->assistant_id = $assistantID;
            $newUserNotification->save();

            $uName          = $user->getName();
            $subject        = $uName .' ви оцени';
            $taskTitle      = $task->name;
            $messageContent = '<p>'. $uName .' постави рейтинг за задача №'. $taskID .'
                <br /><br />Задача: '. $taskTitle .'
                <br />Вие '. $assistantName .',
                <br />Получихте: '. $userAddRating .' звезди от 1 до 5.
                <br />Коментар на '. $uName .':
                <br />'. (isset($request->review_note)) ? $request->review_note : "Не е оставил коментар." .'
            </p>';

            GeneralHelper::sendDynamicSMSManualTemplate(( $assistant->phone_code . $assistant->phone ), 'client_added_rating_assistant', function() use($assistantID, $taskID) {
                UserHelper::addNewSMSNotificationHistories($assistantID, 'client_added_rating_assistant', $taskID);
            }, $task);
            GeneralHelper::sendDynamicalMail($assistantName, $assistantEmail, $subject, $messageContent);
            UserHelper::addNewEmailNotificationHistories($assistantID, 'client_added_rating_assistant', $taskID);
            GeneralHelper::sendDynamicalMail(config('mail.username'), config('mail.from.address'), $subject, $messageContent);

            return back()->with(['successMessage' => 'Отзивът ви бе записан и изпратен на асистента успешно.']);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function addCategory(Request $request) {
        $user = Auth::user();

        if($user && $user->getRole() == 'assistant') {
            UserCategories::where('user_id', $user->id)->delete();

            if(isset($request->chosen_all_categories)) {
                $user->chosen_all_categories = true;
                $user->save();

                return back()->with(['successMessage' => 'Вие избрахте всички категории.']);
            }

            $subCatLastId = SubCategory::latest()->orderBy('id', 'desc')->first()->id;

            for($c = 0; $c <= $subCatLastId; $c++) {
                if(isset($_POST['cat_main_'. $c]) || isset($_POST['cat_sub_'. $c])) {
                    $subCatVal = 0;

                    if(isset($_POST['cat_sub_'. $c])) {
                        $mainCatVal = $_POST['cat_sub_'. $c];
                        $subCatVal  = $c;
                    } else {
                        $mainCatVal = $_POST['cat_main_'. $c];
                    }

                    $userCategories = new UserCategories();

                    $userCategories->user_id         = $user->id;
                    $userCategories->category_id     = $mainCatVal;
                    $userCategories->sub_category_id = $subCatVal;

                    $userCategories->save();
                }
            }

            return back()->with(['successMessage' => 'Успешно добавихте избраните категории.']);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function checkHasNewActionUserProfile(Request $request) {
        $user   = Auth::user();
        $userID = $user->id;

        if($userID != $request->user_id) {
            abort(403, 'Unauthorized action.');
        }

        $assistantApproved = false;

        if($user->getRole() == 'assistant') {
            $getNotifications = self::getUserNewNotificationsCount($userID);
            $newReviewsCount  = self::getAssistantReviewCount($user);

            $assistantCompletedRegistration = true;

            if(is_null($user->convicted) || is_null($user->address) || is_null($user->location_lat) || is_null($user->location_lng)) {
                $assistantCompletedRegistration = false;
            }

            if($assistantCompletedRegistration && $user->approved) {
                $assistantApproved = true;
            }

            if(!$user->refresh_page && $assistantApproved) {
                $assistantApproved  = true;
                $user->refresh_page = true;
                $user->save();
            } else {
                $assistantApproved = false;
            }
        } else {
            $getNotifications  = self::getUserNewNotificationsCount($userID);
            $assistantApproved = false;
            $newReviewsCount   = 0;
        }

        return json_encode([
            'userNotifications' => $getNotifications,
            'assistantApproved' => $assistantApproved,
            'newReviewsCount'   => $newReviewsCount
        ]);
    }

    public function markAllNotificationsAsRead() {
        $user = Auth::user();

        if($user) {
            $getUserNotifications = UserNotifications::where('user_id', $user->id)->where('user_seen', false)->get();

            if($getUserNotifications->count() > 0) {
                foreach ($getUserNotifications as $getUserNotification) {
                    $getUserNotification->user_seen = !filter_var($getUserNotification->user_seen, FILTER_VALIDATE_BOOLEAN);
                    $getUserNotification->save();
                }
            }

            return back();
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function seenUserNotification(Request $request) {
        $user = Auth::user();

        if($user) {
            $getUserNotification = UserNotifications::find($request->notification_id);

            if(is_null($getUserNotification)) {
                abort(403, 'Unauthorized action.');
            }

            $getUserNotification->user_seen = !filter_var($getUserNotification->user_seen, FILTER_VALIDATE_BOOLEAN);

            $returnData = ['successSeen' => false];
            if($getUserNotification->save()) {
                $returnData['successSeen'] = true;
            }

            return json_encode($returnData);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function proposalTypeCategory(Request $request) {
        $user = Auth::user();

        if($user) {
            $request->validate([
                'proposal_type_category_field'    => 'required|min:3|max:30|no_html',
                'why_proposal_this_type_category' => 'nullable|max:500|no_html',
            ], [
                'proposal_type_category_field.required'    => 'Полето за предлагане на категория е задължително.',
                'proposal_type_category_field.min'         => 'Полето за предлагане на категория трябва да бъде поне 3 символа.',
                'proposal_type_category_field.max'         => 'Полето за предлагане на категория трябва да бъде не повече от 30 символа.',
                'proposal_type_category_field.no_html'     => 'Открити са не разрешени символи в полето за предлагане на категория.',
                'why_proposal_this_type_category.required' => 'Описанието е задължително.',
                'why_proposal_this_type_category.min'      => 'Описанието трябва да бъде по-дълго от 20 символа.',
                'why_proposal_this_type_category.no_html'  => 'Открити са не разрешени символи в подкатегория.',
            ]);

            $userID              = $user->id;
            $proposalCategory    = $request->proposal_type_category_field;
            $whyProposalCategory = $request->why_proposal_this_type_category;

            $newProposalTypeCategory = new ProposalTypeCategories();
            $newProposalTypeCategory->user_id           = $userID;
            $newProposalTypeCategory->category_name     = $proposalCategory;
            $newProposalTypeCategory->why_this_category = $whyProposalCategory;
            $newProposalTypeCategory->save();

            $newUserStatusHistories = new UserStatusHistory();
            $newUserStatusHistories->user_id   = $userID;
            $newUserStatusHistories->status_id = config('constants.statuses.user_proposal_category');
            $newUserStatusHistories->date      = Carbon::now();
            $newUserStatusHistories->save();

            $userName         = $user->getName();
            $userType         = ($user->getRole() == 'assistant') ? 'асистент' : 'клиент' ;
            $mailSubject      = 'Ново предложение за категория от '. $user->getName() .' ('. $userType .')';
            $towardsUsContent = '<p>Направено е предложение за категория от '. $userName .' ('. $userType .'),
                <br /><br />Предложената категория: '. $proposalCategory .'
                <br />Защо е била предложена: '. $whyProposalCategory .'
            </p>';

            GeneralHelper::sendDynamicalMail(config('mail.username'), config('mail.from.address'), $mailSubject , $towardsUsContent);

            return back()->with('successMessage', 'Успешно изпратихте вашето предложение за категория '. $proposalCategory);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function getHiddenPhone(Request $request) {
        $user = Auth::user();

        if($user) {
            $ownerPhoneID      = $request->user_id;
            $getOwnerPhoneUser = User::find($ownerPhoneID);

            if(is_null($getOwnerPhoneUser)) {
                abort(403, 'Unauthorized action.');
            }

            $userID     = $user->id;
            $ownerPhone = $getOwnerPhoneUser->phone;

            $getUserSeenPhonesCount = UserSeenPhones::where('user_id', $userID)->where('phone_owner_id', $ownerPhoneID)->count();

            if($getUserSeenPhonesCount == 0) {
                $newUserSeenPhone = new UserSeenPhones();
                $newUserSeenPhone->user_id        = $userID;
                $newUserSeenPhone->phone_owner_id = $ownerPhoneID;
                $newUserSeenPhone->phone          = $ownerPhone;
                $newUserSeenPhone->save();

                $newUserStatusHistories = new UserStatusHistory();
                $newUserStatusHistories->user_id   = $userID;
                $newUserStatusHistories->status_id = config('constants.statuses.user_seen_phone');
                $newUserStatusHistories->date      = Carbon::now();
                $newUserStatusHistories->save();
            }

            return json_encode(['ownerPhone' => $ownerPhone]);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }

    public function sentNewVerifyToken($id) {
        $user = User::find($id);

        if(is_null($user)) {
            abort(403, 'Unauthorized action.');
        }

        UserHelper::newGenerateVerifyTokenByUser($user);

        $responseData = array(
            'userID'              => $user->id,
            'emailVerifyMessage'  => 'За да завършите регистрацията си, трябва да потвърдите имейл адреса си. Отворете електронната си поща и потърсете имейл от нас с връзка за потвърждаване. Ако не сте получили такъв, потърсете в папката за спам или кликнете върху бутона по - долу, за да ви изпратим нов.',
            'successSendingEmail' => 'Имейлът бе изпратен'
        );
        return view('pages.profile.verify_user')->with($responseData);
    }
}
