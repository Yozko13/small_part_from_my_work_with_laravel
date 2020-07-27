<?php

namespace App\Http\Controllers;

use App\Helpers\GeneralHelper;
use App\Helpers\UserHelper;
use App\Models\Common\ListingChats;
use App\Models\Shop\AttributeField;
use App\Models\Shop\ListingStatusHistory;
use App\Models\Shop\MotorVehicle;
use App\Models\User\UserStatusHistory;
use App\Models\Shop\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Shop\Listing;
use App\Models\Shop\Picture;
use App\Models\Shop\Currency;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;

class ListingController extends Controller
{
    protected $listingID = [
        'listing'
    ];

    private function viewsListings($listings, $route, $withOutSearch = false) {
        $listingsMobile = $listings->appends(Input::except('page'));

        if($route == 'all_listings' && $withOutSearch) {
            $listingsMobile = Listing::getMyAllListings();
        }

        $all_listings_count      = Listing::getMyAllListingsCount();
        $promoted_listings_count = Listing::getMyPromotedListingsCount();
        $active_listings_count   = Listing::getMyActiveListingsCount();
        $approve_listings_count  = Listing::getMyWaitingForApproveListingsCount();
        $inactive_listings_count = Listing::getMyInactiveListingsCount();

        return [
            'listingsMobile'          => $listingsMobile,
            'listings'                => $listings->appends(Input::except('page')),
            'title'                   => 'Моите обяви',
            'subtitle'                => 'Тук можеш да управляваш твоите активни и архивирани обяви',
            'active'                  => 'listings',
            'active_listing_type'     => $route,
            'all_listings_count'      => $all_listings_count[0]->listings_count,
            'promoted_listings_count' => $promoted_listings_count[0]->listings_count,
            'active_listings_count'   => $active_listings_count[0]->listings_count,
            'approve_listings_count'  => $approve_listings_count[0]->listings_count,
            'inactive_listings_count' => $inactive_listings_count[0]->listings_count,
            'type_view'               => Auth::user()->getRole(),
        ];
    }

    public function viewAllListings() {
        $listings = Listing::where('active', true)->orderBy('promoted', 'desc')->orderBy('created_at', 'desc')->paginate(18);

        return view('pages.listings.all_listings')->with(['listings' => $listings]);
    }

    public function viewProfileListings(Request $request) {
        if ($request->get('listing_search')) {
            $withOutSearch = false;
            $listings      = Listing::searchMyListings($request->get('listing_search'));
        } else {
            $withOutSearch = true;
            $listings      = Listing::getMyListings();
        }

        return view('pages.profile.listings')->with($this->viewsListings($listings, 'all_listings', $withOutSearch));
    }

    public function viewProfilePromotedListings(Request $request) {
        if ($request->get('listing_search')) {
            $listings = Listing::searchMyPromotedListings($request->get('listing_search'));
        } else {
            $listings = Listing::getMyPromotedListings();
        }

        return view('pages.profile.listings')->with($this->viewsListings($listings, 'promoted_listings'));
    }

    public function viewActiveListings(Request $request) {
        if ($request->get('listing_search')) {
            $listings = Listing::searchMyListings($request->get('listing_search'), true);
        } else {
            $listings = Listing::getMyActiveListings();
        }

        return view('pages.profile.listings')->with($this->viewsListings($listings, 'active_listings'));
    }

    public function viewWaitingForApprovedListings(Request $request) {
        if ($request->get('listing_search')) {
            $listings = Listing::searchMyListingsWaitingForApprove($request->get('listing_search'));
        } else {
            $listings = Listing::getMyWaitingForApproveListings();
        }

        return view('pages.profile.listings')->with($this->viewsListings($listings, 'approve_listings'));
    }

    public function viewInactiveListings(Request $request) {
        if ($request->get('listing_search')) {
            $listings = Listing::searchMyListings($request->get('listing_search'), false);
        } else {
            $listings = Listing::getMyInactiveListings();
        }

        return view('pages.profile.listings')->with($this->viewsListings($listings, 'inactive_listings'));
    }
    
    private function sendAddTaskEmailToClient($task) {
        $taskTitle  = $task->name;
        $subject    = 'Добавихте задача!'. $taskTitle;
        $clientName = $task->user->getName();

        $messageContent = '<p>Здравейте '. $clientName .',
            <br /><br />Сега, когато '. $taskTitle .' бе добавена, асистенти ще започнат да я разглеждат, да оставят коментари и да правят оферти. Моля проверявайте и отговаряйте скоро на зададените въпроси и направените оферти. За по-добро взаимодействие, моля РАЗРЕШЕТЕ нотификациите на браузъра си. Ако не сте сигурни как да направите това, моля следвайте тези инструкции.
            <br /><br />КАКВО СЛЕДВА?
            <br /><br />1. Асистенти ще задават въпроси и ще дават оферти за помоща си - бъди в готовност да отговориш.
            <br />2. Разгледай направените оферти и избери най-подходящия човек за задачата и приеми неговата оферта.
            <br />3. За сигурност на плащанията, ще бъдете помолени да платите в брой на избрания от вас асистент или чрез кредитна/дебитна карта. В някои случай може да бъде необходимо задължително предварително плащане. Плащането ще бъде задържано в ...... акаунт, докато задачата не бъде завършена. Плащането ще бъде прието единствено когато одобрите завършването на задачата.
            <br /><br />Вие имате контрола.
            <br /><br />Благодарим!
            <br /><br />Екипът на АсистМи
            <br />--------------------------<br />
            <br />HI '. $clientName .',
            <br /><br />Now that '. $taskTitle .' has been posted, Assistants will be checking out your task, leaving comments and making offers. Make sure you check back soon to answer any questions, reply to comments or accept an offer! For better interaction make sure that your browser ALLOWS our notifications. If you are not sure how to do that, please view this guide
            <br /><br />WHAT HAPPENS NEXT?
            <br /><br />1. Taskers will ask you questions & offer their help - get ready to reply.
            <br />2. Review offers made, pick the right person for the task and accept their offer.
            <br />3. Payment is not taken until you confirm Task completion. Card payments are pre-authorized & processed securely via our payment processor.
            <br />4. In some specific cases we might require you to prepay if asking your Assistant to purchase materials for the Task.
            <br /><br />You are in control.
            <br /><br />Thanks!
            <br /><br />The AssistMe Team
        </p>';

        GeneralHelper::sendDynamicalMail($clientName, $task->user->email, $subject, $messageContent);
        UserHelper::addNewEmailNotificationHistories($task->user_id, 'client_add_new_task', $task->id);
    }

    private function sendTowardsUsEmailForAddTask($task) {
        $subCatName = '';

        if(!is_null($task->subCategory)) {
            $subCatName = ' Подкатегория '. $task->subCategory->name;
        }

        $taskLocation     = $task->location->name .' ('. $task->location->region .'), п.к.'. $task->location->post_code;
        $towardsUsContent = '<p>Направена е поръчка № '. $task->id .',
            <br /><br />Клиент: '. $task->user->getName() .'
            <br />Локация: '. $taskLocation .'
            <br />Стойност: '. $task->price .'
            <br />Начин на плащане: '. $task->payment_method .'
            <br /><br />Категория '. $task->category->name . $subCatName .'
        </p>';

        GeneralHelper::sendDynamicalMail(config('mail.username'), config('mail.from.address'), 'Нова обява', $towardsUsContent);
    }

    public function sessionStorageAddTask(Request $request) {
        $user = Auth::user();

        if(!$user) {
            return abort(403, 'Unauthorized action.');
        }

        $formValidation = [
            'category'     => 'required|integer|no_html',
            'sub_category' => 'nullable|integer|no_html',
            'title'        => 'required|min:5|max:50|no_html',
            'description'  => 'required|min:20|no_html',
            'location_id'  => 'nullable|integer|no_html',
            'address'      => 'nullable|no_html',
            'location_lat' => 'nullable|regex:/^[0-9]+(\.[0-9]+[0-9]?)?$/',
            'location_lng' => 'nullable|regex:/^[0-9]+(\.[0-9]+[0-9]?)?$/',
            'valid_date'   => 'required',
            'price'        => 'required|numeric|min:5|max:10000000|no_html',
            'photos'       => 'nullable',
        ];

        $validationMessages = [
            'category.required'    => 'Категорията е задължителна.',
            'category.integer'     => 'Категорията е задължителна.',
            'category.no_html'     => 'Открити са не разрешени символи в категория.',
            'sub_category.integer' => 'Под категорията е задължителна.',
            'sub_category.no_html' => 'Открити са не разрешени символи в подкатегория.',
            'title.required'       => 'Моля въведете заглавие.',
            'title.min'            => 'Заглавието трябва да бъде поне 5 символа.',
            'title.max'            => 'Заглавието трябва да бъде не повече от 50 символа.',
            'title.no_html'        => 'Открити са не разрешени символи в заглавието.',
            'description.required' => 'Описанието е задължително.',
            'description.min'      => 'Описанието трябва да бъде по-дълго от 20 символа.',
            'description.no_html'  => 'Открити са не разрешени символи в описанието.',
            'location_id.integer'  => 'Населеното място е задължително.',
            'location_id.no_html'  => 'Открити са не разрешени символи в избора на населено място.',
            'address.no_html'      => 'Открити са не разрешени символи в адреса.',
            'location_lat.regex'   => 'Не сте избрали точен адрес от посочените от Google.',
            'location_lng.regex'   => 'Не сте избрали точен адрес от посочените от Google.',
            'valid_date.required'  => 'Времето за извършване на услугата е задължително.',
            'price.required'       => 'Цената е задължителна',
            'price.numeric'        => 'Цената приема само числа.',
            'price.min'            => 'Минималната цена може да бъде 5',
            'price.max'            => 'Максималната цена може да бъде 9999999.99',
            'price.no_html'        => 'Открити са не разрешени символи в полето цена.',
        ];

        $this->validate(
            $request,
            $formValidation,
            $validationMessages
        );

        $userID   = $user->id;
        $formData = $request->input();
        $reqLat   = $formData['location_lat'];
        $reqLng   = $formData['location_lng'];
        $newTask = new Listing();

        $newTask->user_id        = $userID;
        $newTask->delivery_type  = 1;
        $newTask->active         = true;
        $newTask->approve        = true;
        $newTask->category_id    = $formData['category'];
        $newTask->name           = $formData['title'];
        $newTask->description    = $formData['description'];
        $newTask->address        = $formData['address'];
        $newTask->location_lat   = $reqLat;
        $newTask->location_lng   = $reqLng;
        $newTask->end_task_date  = date($formData['valid_date']);
        $newTask->price          = $formData['price'];
        $newTask->status_id      = config('constants.statuses.approved');
        $newTask->contact_name   = $user->name;
        $newTask->contact_email  = $user->email;
        $newTask->contact_number = $user->phone;

        if(!is_null($reqLat) && !is_null($reqLng)) {
            $newTask->geom = DB::raw("ST_SetSRID(ST_MakePoint(". $reqLat .", ". $reqLng ."),4326)");
        }

        if(isset($formData['sub_category']) && $formData['sub_category'] > 0) {
            $newTask->sub_category_id = $formData['sub_category'];
        }

        if(isset($formData['location_id'])) {
            $newTask->location_id = $formData['location_id'];
        }

        $newTask->save();

        $taskID   = $newTask->id;
        $taskDate = $newTask->updated_at;

        if (!empty($formData['photos'])) {
            $clearNullItems = str_replace('null,', '', $formData['photos']);
            $files          = explode(",", preg_replace('/\,$/', '', $clearNullItems));

            PictureController::saveListingSessionPicture($files, $newTask);

//            return redirect('/o/' . $new_listing->slug);
        }

        $getStatusNew = config('constants.statuses.new');

        $newListingHistory             = new ListingStatusHistory();
        $newListingHistory->listing_id = $taskID;
        $newListingHistory->status_id  = $getStatusNew;
        $newListingHistory->date       = $taskDate;
        $newListingHistory->save();

        $newUserStatusHistories             = new UserStatusHistory();
        $newUserStatusHistories->user_id    = $userID;
        $newUserStatusHistories->status_id  = $getStatusNew;
        $newUserStatusHistories->date       = $taskDate;
        $newUserStatusHistories->listing_id = $taskID;
        $newUserStatusHistories->save();

//        $newUserNotification = new UserNotifications();
//        $newUserNotification->user_id   = $userID;
//        $newUserNotification->user_type = 'client';
//        $newUserNotification->status_id = $getStatusNew;
//        $newUserNotification->task_id   = $taskID;
//        $newUserNotification->save();

        self::sendTowardsUsEmailForAddTask($newTask);
        self::sendAddTaskEmailToClient($newTask);

        return json_encode(['successMessage' => 'Задачата е публикувана успешно.', 'successAddNewTaskClearSession' => true]);
    }
    
    public function addTask(Request $request) {
        $user = Auth::user();

        if(!$user) {
            return abort(403, 'Unauthorized action.');
        }

        $formValidation = [
            'main_category'    => 'required|integer|no_html',
            'sub_category'     => 'nullable|integer|no_html',
            'title'            => 'required|min:5|max:50|no_html',
            'description'      => 'required|min:20|no_html',
            'location_id'      => 'nullable|integer|no_html',
            'use_own_location' => 'nullable|integer|no_html',
            'address'          => 'nullable|no_html',
            'location_lat'     => 'nullable|regex:/^[0-9]+(\.[0-9]+[0-9]?)?$/',
            'location_lng'     => 'nullable|regex:/^[0-9]+(\.[0-9]+[0-9]?)?$/',
            'time_add_task'    => 'required',
            'price'            => 'required|numeric|min:5|max:10000000|no_html',
            'images'           => 'nullable',
            'images.*'         => 'mimes:jpg,jpeg,png'
        ];

        $validationMessages = [
            'main_category.required'   => 'Моля избери категория',
            'main_category.integer'    => 'Моля избери валидна категория',
            'main_category.no_html'    => 'Открити са не разрешени символи в категория.',
            'sub_category.integer'     => 'Моля изберете валидна подкатегория',
            'sub_category.no_html'     => 'Открити са не разрешени символи в подкатегория.',
            'title.required'           => 'Моля въведете заглавие.',
            'title.min'                => 'Заглавието трябва да бъде поне 5 символа.',
            'title.max'                => 'Заглавието трябва да бъде не повече от 50 символа.',
            'title.no_html'            => 'Открити са не разрешени символи в подкатегория.',
            'description.min'          => 'Описанието трябва да бъде по-дълго от 20 символа.',
            'description.no_html'      => 'Открити са не разрешени символи в подкатегория.',
            'location_id.integer'      => 'Моля изберете населено място.',
            'location_id.no_html'      => 'Открити са не разрешени символи в избора на населено място.',
            'use_own_location.integer' => 'Моля изберете населено място.',
            'use_own_location.no_html' => 'Открити са не разрешени символи в избора на населено място.',
            'address.no_html'          => 'Открити са не разрешени символи в подкатегория.',
            'location_lat.regex'       => 'Не сте избрали точен адрес от посочените от Google.',
            'location_lng.regex'       => 'Не сте избрали точен адрес от посочените от Google.',
            'time_add_task.required'   => 'Моля изберете време за извършване на услугата.',
            'price.required'           => 'Моля попълнете полето заплащане.',
            'price.numeric'            => 'Полето цена приема само числа.',
            'price.min'                => 'Минималната цена може да бъде 5',
            'price.max'                => 'Максималната цена на обявата е 9999999.99',
            'price.no_html'            => 'Открити са не разрешени символи в полето цена.',
            'images.mimes'             => 'Файла може да е само jpg,jpeg,png разширение.'
        ];

        $this->validate(
            $request,
            $formValidation,
            $validationMessages
        );

        $userID   = $user->id;
        $formData = $request->input();
        $reqLat   = $formData['location_lat'];
        $reqLng   = $formData['location_lng'];

        $newTask = new Listing();
        $newTask->user_id        = $userID;
        $newTask->delivery_type  = 1;
        $newTask->active         = true;
        $newTask->approve        = true;
        $newTask->category_id    = $formData['main_category'];
        $newTask->name           = $formData['title'];
        $newTask->description    = $formData['description'];
        $newTask->address        = $formData['address'];
        $newTask->location_lat   = $reqLat;
        $newTask->location_lng   = $reqLng;
        $newTask->end_task_date  = date($formData['time_add_task']);
        $newTask->price          = $formData['price'];
        $newTask->status_id      = config('constants.statuses.approved');
        $newTask->contact_name   = $user->name;
        $newTask->contact_email  = $user->email;
        $newTask->contact_number = $user->phone;

        if(!is_null($reqLat) && !is_null($reqLng)) {
            $newTask->geom = DB::raw("ST_SetSRID(ST_MakePoint(". $reqLat .", ". $reqLng ."),4326)");
        }

        if(isset($formData['sub_category']) && $formData['sub_category'] > 0) {
            $newTask->sub_category_id = $formData['sub_category'];
        }

        if(isset($formData['location_id'])) {
            $newTask->location_id = $formData['location_id'];
        }

        if(isset($formData['use_own_location'])) {
            $newTask->location_id = $formData['use_own_location'];
        }

        $newTask->save();

        if ($request->hasFile('images')) {
            $files = $request->file('images');

            PictureController::saveListingPicture($files, $newTask);

            //            return redirect('/o/' . $new_listing->slug);
        }

        $newTaskID    = $newTask->id;
        $getStatusNew = config('constants.statuses.new');

        $new_listing_history             = new ListingStatusHistory();
        $new_listing_history->listing_id = $newTaskID;
        $new_listing_history->status_id  = $getStatusNew;
        $new_listing_history->date       = $newTask->updated_at;
        $new_listing_history->save();

        $userStatusHistories             = new UserStatusHistory();
        $userStatusHistories->user_id    = $userID;
        $userStatusHistories->status_id  = $getStatusNew;
        $userStatusHistories->date       = $newTask->updated_at;
        $userStatusHistories->listing_id = $newTaskID;
        $userStatusHistories->save();

//        $newUserNotification = new UserNotifications();
//        $newUserNotification->user_id   = $userID;
//        $newUserNotification->user_type = 'client';
//        $newUserNotification->status_id = $getStatusNew;
//        $newUserNotification->task_id   = $newTaskID;
//        $newUserNotification->save();

        self::sendTowardsUsEmailForAddTask($newTask);
        self::sendAddTaskEmailToClient($newTask);

        return back()->with(['successMessage' => 'Задачата е публикувана успешно']);
    }

    public function editListing(Request $request) {
        $taskID            = $request->task_id;
        $editTask          = Listing::find($taskID);
        $taskBelongsToUser = $editTask->checkIfListingBelongsToLoggedUser();

        //        if ($taskBelongsToUser || Auth::user()->getRole() === env("EDITOR_ROLE")) {
        if ($taskBelongsToUser) {
            $formValidation = [
                'edit_category'      => 'required|integer|no_html',
                'edit_sub_category'  => 'nullable|integer|no_html',
                'edit_title'         => 'required|min:5|max:50|no_html',
                'edit_description'   => 'required|min:20|no_html',
                'edit_location_id'   => 'nullable|integer|no_html',
                'edit_address'       => 'nullable|no_html',
                'location_lat'       => 'nullable|regex:/^[0-9]+(\.[0-9]+[0-9]?)?$/',
                'location_lng'       => 'nullable|regex:/^[0-9]+(\.[0-9]+[0-9]?)?$/',
                'edit_time_add_task' => 'required',
                'edit_price'         => 'required|numeric|min:5|max:10000000|no_html',
                'oldImages'          => 'nullable|array|max:3',
                'oldImages.*'        => 'nullable|integer|distinct|min:1',
                'images'             => 'nullable',
                'images.*'           => 'mimes:jpg,jpeg,png'
            ];

            $validationMessages = [
                'edit_category.required'      => 'Моля избери категория',
                'edit_category.integer'       => 'Моля избери валидна категория',
                'edit_category.no_html'       => 'Открити са не разрешени символи в категория.',
                'edit_sub_category.integer'   => 'Моля изберете валидна подкатегория',
                'edit_sub_category.no_html'   => 'Открити са не разрешени символи в подкатегория.',
                'edit_title.required'         => 'Моля въведете заглавие.',
                'edit_title.min'              => 'Заглавието трябва да бъде поне 5 символа.',
                'edit_title.max'              => 'Заглавието трябва да бъде не повече от 50 символа.',
                'edit_title.no_html'          => 'Открити са не разрешени символи в подкатегория.',
                'edit_description.required'   => 'Моля въведете описание.',
                'edit_description.min'        => 'Описанието трябва да бъде по-дълго от 20 символа',
                'edit_description.no_html'    => 'Открити са не разрешени символи в подкатегория.',
                'edit_location_id.integer'    => 'Моля изберете населено място',
                'edit_location_id.no_html'    => 'Открити са не разрешени символи в избора на населено място.',
                'edit_address.no_html'        => 'Открити са не разрешени символи в подкатегория.',
                'location_lat.regex'          => 'Не сте избрали точен адрес от посочените от Google.',
                'location_lng.regex'          => 'Не сте избрали точен адрес от посочените от Google.',
                'edit_time_add_task.required' => 'Моля изберете време за извършване на услугата.',
                'edit_price.required'         => 'Моля попълнете полето заплащане.',
                'edit_price.numeric'          => 'Полето цена приема само числа.',
                'edit_price.min'              => 'Минималната цена може да бъде 5',
                'edit_price.max'              => 'Максималната цена на обявата е 9999999.99',
                'edit_price.no_html'          => 'Открити са не разрешени символи в полето цена.',
                'oldImages.max'               => 'Максималния брой снимки е 3 броя.',
                'oldImages.integer'           => 'Максималния брой снимки е 3 броя.',
                'oldImages.distinct'          => 'Максималния брой снимки е 3 броя.',
                'oldImages.min'               => 'Максималния брой снимки е 3 броя.',
                'images.mimes'                => 'Файла може да е само jpg,jpeg,png разширение.'
            ];

            $this->validate(
                $request,
                $formValidation,
                $validationMessages
            );

            $formData = $request->input();
            $reqLat   = $formData['location_lat'];
            $reqLng   = $formData['location_lng'];

            $editTask->category_id   = $formData['edit_category'];
            $editTask->name          = $formData['edit_title'];
            $editTask->description   = $formData['edit_description'];
            $editTask->address       = $formData['edit_address'];
            $editTask->location_lat  = $reqLat;
            $editTask->location_lng  = $reqLng;
            $editTask->end_task_date = date('Y-m-d H:i:s', strtotime($formData['edit_time_add_task']));
            $editTask->price         = $formData['edit_price'];

            if(!is_null($reqLat) && !is_null($reqLng)) {
                $editTask->geom = DB::raw("ST_SetSRID(ST_MakePoint(". $reqLat .", ". $reqLng ."),4326)");
            }

            if(isset($formData['edit_sub_category']) && $formData['edit_sub_category'] > 0) {
                $editTask->sub_category_id = $formData['edit_sub_category'];
            }

            if(isset($formData['edit_location_id'])) {
                $editTask->location_id = $formData['edit_location_id'];
            }

            if(isset($formData['oldImages'])) {
                $deletePictures = Picture::select('pictures.*')
                    ->join('listings', 'listings.id', '=', 'pictures.listing_id')
                    ->whereRaw('pictures.id not in ('. implode(",", $formData['oldImages']) .') and pictures.listing_id = '. $taskID)
                    ->where('listings.user_id', Auth::user()->id)
                    ->get();

                if($deletePictures->count() > 0) {
                    foreach ($deletePictures as $deletePicture) {
                        $s3FileController = new S3FileController();
                        $s3FileController->deleteFileFromS3($deletePicture->getPublicAddress());
                        $deletePicture->deleteFileFromServer();
                        $deletePicture->delete();
                    }
                }
            }

            $editTask->save();

            if ($request->hasFile('images')) {
                $files = $request->file('images');

                if ($files) {
                    if(!isset($formData['oldImages'])) {
                        $deletePictures = Picture::where('listing_id', $taskID)->get();

                        foreach ($deletePictures as $deletePicture) {
                            $s3FileController = new S3FileController();
                            $s3FileController->deleteFileFromS3($deletePicture->getPublicAddress());
                            $deletePicture->deleteFileFromServer();
                            $deletePicture->delete();
                        }
                    }

                    PictureController::saveListingPicture($files, $editTask);
                }
            }

            $makeDefaultPicture = Picture::where('listing_id', $taskID)->get();
            foreach ($makeDefaultPicture as $pKey => $pValue) {
                if($pKey == 0) {
                    $editTask->picture = $pValue->name;
                    $editTask->save();

                    $pValue->default = true;
                } else {
                    $pValue->default = false;
                }

                $pValue->position = $pKey;
                $pValue->save();
            }

            return back()->with(['successMessage' => 'Промените бяха записани успешно.', 'taskId' => $taskID]);
        } else {
            return abort(403, 'Unauthorized action.');
        }
    }

    public function getContactPhone(Request $request, $listing_id) {
        $valid = $this->validateListingID($request, $listing_id);
        if(is_array($valid)) {
            return $valid;
        }

        $listing = Listing::find($listing_id);

        return $this->handleResponse($request, $listing, 'contact_number');
    }

    public function getContactSkype(Request $request, $listing_id) {
        $valid = $this->validateListingID($request, $listing_id);
        if(is_array($valid)) {
            return $valid;
        }
        $listing = Listing::find($listing_id);
        return $this->handleResponse($request, $listing, 'contact_skype');
    }

    public function getAccountPhone(Request $request, $listing_id) {
        $valid = $this->validateListingID($request, $listing_id);
        if(is_array($valid)) {
            return $valid;
        }
        $listing = Listing::find($listing_id);
        return $this->handleResponse($request, $listing, 'user', 'phone');
    }

    public function getAccountSkype(Request $request, $listing_id) {
        $valid = $this->validateListingID($request, $listing_id);
        if(is_array($valid)) {
            return $valid;
        }
        $listing = Listing::find($listing_id);
        return $this->handleResponse($request, $listing, 'user', 'skype');
    }
    
    private function handleResponse($request, $data, $field, $subfield = '') {
        if($data) {
            $fieldData   = '$field';
            $returnField = $data->$field;
            if($subfield) {
                $subFieldData = '$subfield';
                $returnField  = $data->$field->$subfield;
            }
            return [
                'success' => true,
                'data'    => $returnField
            ];
        } else {
            if ($request->ajax()) {
                return [
                    'success' => false,
                    'data'    => "Не е намерен такъв обект"
                ];   
            } else {
                abort(404);
            }
        }
    }

    private function getTaskDataForAssistant($user, $taskID, $tasksType = 'head_tasks', $taskAccepted = false) {
        $task = Listing::find($taskID);

        if(is_null($task)) {
            abort(404);
        }

        $userPercent          = $user->percent;
        $statusID             = $task->status_id;
        $chatID               = 0;
        $ushStatusID          = 0;
        $newPrice             = 0;
        $newEarnedPrice       = 0;
        $lastOfferPrice       = 0;
        $lastOfferEarnedPrice = 0;
        $phoneOwnerID         = 0;
        $ownerPhone           = '0811111111';
        $confirmOfferButton   = false;
        $completedButton      = false;
        $validateDate         = Carbon::parse($task->end_task_date);
        if($tasksType == 'pending_tasks' || $tasksType == 'accepted_tasks') {
            $taskAccepted       = true;
            $confirmOfferButton = true;

            $userID = $user->id;
            $chatID = ListingChats::where('listing_id', $taskID)->where(function ($lcq) use ($userID) {
                $lcq->where('sender_id', $userID);
                $lcq->orWhere('recipient_id', $userID);
            })->orderBy('id', 'desc')->first();

            if(is_null($chatID)) {
                $chatID = 0;
            } else {
                $chatID = $chatID->id;
            }

            $clientID    = $task->user_id;
            $ushStatusID = UserStatusHistory::where('listing_id', $taskID)->where('user_id', $clientID)->where('assistant_id', $userID)->orderBy('id', 'desc')->first();

            if(is_null($ushStatusID)) {
                $ushStatusID = 0;
            } else {
                $ushStatusID = $ushStatusID->status_id;
            }

            if($tasksType == 'pending_tasks') {
                $hasBidding = UserClientController::getClientSuggestion($clientID, $taskID, $userID);

                if($hasBidding) {
                    $newPrice       = $hasBidding->offer_price;
                    $newEarnedPrice = ($newPrice * $userPercent) / 100;

                    $userConfirmClientOffer = UserHelper::hideCBtnIfHasAssistantConfirmedClientOffer($taskID);

                    if($userConfirmClientOffer) {
                        $confirmOfferButton = false;
                    }
                }
            }

            if($tasksType == 'accepted_tasks') {
                $hasChangedPrice = UserHelper::checkHasChangedTaskPrice($task);

                if($hasChangedPrice) {
                    $newPrice       = $hasChangedPrice->offer_price;
                    $newEarnedPrice = ($newPrice * $userPercent) / 100;
                }

                if($statusID > 8) {
                    $ownerPhone   = GeneralHelper::getHiddenPhone($task->user->phone);
                    $phoneOwnerID = $clientID;

                    if($statusID == 9) {
                        $currentDate = Carbon::now();
                        if(strtotime($validateDate) <= strtotime($currentDate)) {
                            $completedButton = true;
                        }
                    }
                }
            }

            if($ushStatusID == 16) {
                $getLastOffer = UserHelper::getAssistantLatestOfferTask($taskID);

                if($getLastOffer) {
                    $lastOfferPrice       = $getLastOffer->offer_price;
                    $lastOfferEarnedPrice = ($lastOfferPrice * $userPercent) / 100;
                }
            }
        }

        $location   = 'Задачата няма адрес защото се изпълнява от разтояние.';
        $locationID = $task->location_id;
        if (!is_null($locationID)) {
            $location = 'обл. ' . $task->location->region . ', ' . $task->location->type . ' ' . $task->location->name . ', п.к. ' . $task->location->post_code;
        }

        $taskPrice = $task->price;

        $taskImagesArr = [];
        foreach ($task->pictures()->get() as $image) {
            $taskImagesArr[$image->id] = GeneralHelper::checkS3UrlPath($image->getPublicAddress());
        }

        $task = [
            'id' => $task->id,
            'name' => $task->name,
            'description' => $task->description,
            'categoryName' => $task->category->name,
            'locationLat' => $task->location_lat,
            'locationLng' => $task->location_lng,
            'address' => $task->address,
            'paymentMethod' => $task->payment_method,
            'statusID' => $statusID,
            'newPrice' => $newPrice,
            'newEarnedPrice' => $newEarnedPrice,
            'lastOfferPrice' => $lastOfferPrice,
            'lastOfferEarnedPrice' => $lastOfferEarnedPrice,
            'chatID' => $chatID,
            'accepted' => $taskAccepted,
            'confirmOfferButton' => $confirmOfferButton,
            'completedButton' => $completedButton,
            'tasksType' => $tasksType,
            'price' => $taskPrice,
            'location' => $location,
            'ushStatusID' => $ushStatusID,
            'ownerPhone' => $ownerPhone,
            'phoneOwnerID' => $phoneOwnerID,
            'earnedPrice' => ($taskPrice * $userPercent) / 100,
            'clientName' => $task->user->getName(),
            'dateFormatBG' => GeneralHelper::dateFormatAssistme($validateDate),
        ];

        return [
            'task' => $task,
            'taskImages' => $taskImagesArr
        ];
    }

    private function getTaskDataForClient($userID, $taskID) {
        $task = Listing::leftJoin('listing_chats as lc', 'listings.id', '=', 'lc.listing_id')
            ->leftJoin('user_status_histories as ush', 'listings.id', '=', 'ush.listing_id')
            ->selectRaw('listings.*, count(DISTINCT(lc.id)) as sent_messages_count, SUM(CASE WHEN "ush"."status_id" = '.
                config('constants.statuses.accepted_task_by_assistant') .' or "ush"."status_id" = '.
                config('constants.statuses.assistant_bidding_task') .' THEN 1 ELSE 0 END) as has_offer')
            ->where('listings.id', $taskID)
            ->where('listings.user_id', $userID)
            ->where('listings.approve', true)
            ->where('listings.status_id', '<', config('constants.statuses.completed_by_admin'))
            ->where('ush.user_id', $userID)
            ->groupBy('listings.id')
            ->orderBy('listings.updated_at', 'desc')
            ->get();

        if($task->count() > 0) {
            $task            = $task[0];
            $validateDate    = Carbon::parse($task->end_task_date);
            $statusID        = $task->status_id;
            $subCategoryID   = $task->sub_category_id;
            $subCategoryName = 'Изберете Подкатегория';

            if (!is_null($subCategoryID)) {
                $subCategoryName = $task->subCategory->name;
            } else {
                $subCategoryID = 0;
            }

            $locationID = $task->location_id;
            $location = 'Задачата няма адрес защото се изпълнява от разтояние.';
            if (!is_null($locationID)) {
                $location = 'обл. ' . $task->location->region . ', ' . $task->location->type . ' ' . $task->location->name . ', п.к. ' . $task->location->post_code;
            }

            $assistantID     = $task->assistant_id;
            $assistantName   = '';
            $getUserPhone    = '0811111111';
            $hasWantPayTask  = 0;
            $getTaskChatID   = 0;
            $taskPrice       = $task->price;
            $latestTaskPrice = $taskPrice;

            if (!is_null($assistantID)) {
                if ($statusID > 8) {
                    $assistantName = $task->assistant->getName();

                    $getTaskChat = UserHelper::getTaskChatIDByUsersIDs($taskID, $userID, $assistantID);
                    if(is_null($getTaskChat)) {
                        $getTaskChatID = 0;
                    } else {
                        $getTaskChatID = $getTaskChat->id;
                    }

                    $getUserPhone = GeneralHelper::getHiddenPhone($task->assistant->phone);
                }

                if ($statusID == 9) {
                    $wantPayTaskCard = UserHelper::checkHasWantPayTaskCard($task);

                    if ($wantPayTaskCard) {
                        $hasWantPayTask = 1;
                        $latestTaskPrice = $wantPayTaskCard->offer_price;
                    }
                }
            } else {
                $assistantID = 0;
            }

            $taskImagesArr = [];
            foreach ($task->pictures()->get() as $image) {
                $taskImagesArr[$image->id] = GeneralHelper::checkS3UrlPath($image->getPublicAddress());
            }

            $task = [
                'id' => $taskID,
                'name' => $task->name,
                'description' => $task->description,
                'statusID' => $statusID,
                'categoryID' => $task->category_id,
                'categoryName' => $task->category->name,
                'viewsCount' => $task->page_views,
                'locationID' => $task->location_id,
                'locationLat' => $task->location_lat,
                'locationLng' => $task->location_lng,
                'address' => $task->address,
                'price' => $taskPrice,
                'latestTaskPrice' => $latestTaskPrice,
                'location' => $location,
                'subCategoryID' => $subCategoryID,
                'subCategoryName' => $subCategoryName,
                'hasWantPayTask' => $hasWantPayTask,
                'assistantID' => $assistantID,
                'assistantName' => $assistantName,
                'getTaskChatID' => $getTaskChatID,
                'getUserPhone' => $getUserPhone,
                'taskCompleted' => ($statusID == 10) ? true : false,
                'validDateFormat' => date_format($validateDate, 'd.m.Y H:i:s'),
                'dateFormatBG' => GeneralHelper::dateFormatAssistme($validateDate),
                'validTimeTask' => GeneralHelper::getValidTimeTaskByDiffDates($task->updated_at, $validateDate),
            ];

            return [
                'task' => $task,
                'taskImages' => $taskImagesArr
            ];
        } else {
            abort(404);
        }
    }

    public function getTaskDataForModalByTaskID(Request $request) {
        $user = Auth::user();

        if($user) {
            $taskID = $request->task_id;

            if($user->getRole() == 'assistant') {
                $data = self::getTaskDataForAssistant($user, $taskID, $request->type_task);
            } else {
                $data = self::getTaskDataForClient($user->id, $taskID);
            }

            return json_encode($data);
        } else {
            abort(403, 'Unauthorized action.');
        }
    }
}
