<?php
namespace App\Helpers;

use App\Http\Controllers\S3FileController;
use App\Models\Common\ListingChats;
use App\Models\Shop\BiddingTasks;
use App\Models\User\VerifyUser;
use Illuminate\Support\Facades\Config;
use App\Models\Common\EmailNotificationsHistories;
use App\Models\Common\SmsNotificationsHistories;
use App\Models\User\Reviews;
use App\Models\User\UserStatusHistory;
use Auth;

class UserHelper
{
    public static function getStyleClassDetectNotificationLength($count) {
        switch ($count) {
            case $count < 10:
                return '';
                break;
            case $count < 100:
                return 'dozens-badge';
                break;
            case $count < 1000:
                return 'hundreds-badge';
                break;
            default:
                return '';
        }
    }

    public static function getAssistantRatingByUserID($userID) {
        $rating  = 0;
        $reviews = Reviews::where('assistant_id', $userID)->orderBy('id', "desc")->get();

        if($reviews->count() > 0) {
            $rating = $reviews->sum('rating') / $reviews->count();
        }

        return $rating;
    }

    public static function getCVUrlPath($cv_name) {
        return public_path(DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$cv_name);
    }

    public static function saveCVFile($user_cv) {
        $extension = $user_cv->extension();
        $fileName  = md5($user_cv) . '.' . $extension;
        $file      = public_path(DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.'files');

        if(env('S3_FILE_PATH')) {
            $s3FileController = new S3FileController();
            $filePath = Config::get('constants.paths.user_cv_url');
            $filePath = substr($filePath, 0, strlen($filePath)-1);

            $user_cv->move(Config::get('constants.paths.s3_temporary_path'), $fileName);

            $s3FileController->copyFileToS3($fileName, $filePath);
        } else {
            $user_cv->move($file, $fileName);
        }

        return $fileName;
    }

    public static function deleteUserCV($cv_name) {
        self::deleteFileByPath(public_path(DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$cv_name));
    }

    private function deletePicture($public_path) {
        if (file_exists($public_path) && !is_dir($public_path)) {
            unlink($public_path);
        }
    }

    private static function deleteFileByPath($public_path) {
        if (file_exists($public_path) && !is_dir($public_path)) {
            unlink($public_path);
        }
    }

    public static function checkCanceledAssistantTaskByTaskID($taskID) {
        return UserStatusHistory::where('assistant_id', Auth::user()->id)->where('status_id', config('constants.statuses.canceled_by_assistant'))->where('listing_id', $taskID)->exists();
    }

    public static function addNewEmailNotificationHistories($userID, $typeEmail, $taskID = 0) {
        $newEmailNotificationsHistories          = new EmailNotificationsHistories();
        $newEmailNotificationsHistories->user_id = $userID;

        if($taskID > 0) {
            $newEmailNotificationsHistories->task_id = $taskID;
        }

        $newEmailNotificationsHistories->type_email = $typeEmail;
        $newEmailNotificationsHistories->save();
    }

    public static function addNewSMSNotificationHistories($userID, $typeSMS, $taskID = 0) {
        try {
            $newSmsNotificationsHistories          = new SmsNotificationsHistories();
            $newSmsNotificationsHistories->user_id = $userID;
    
            if($taskID > 0) {
                $newSmsNotificationsHistories->task_id = $taskID;
            }
    
            $newSmsNotificationsHistories->type_sms = $typeSMS;
            $newSmsNotificationsHistories->save();
        } catch (Exception $e) {
            // catch exception
        }
    }

    public static function getAssistantLatestOfferTask($taskID) {
        $user = Auth::user();

        if($user) {
            $getLatestOffer = BiddingTasks::where('assistant_id', $user->id)
                ->where('task_id', $taskID)
                ->where('status_id', config('constants.statuses.assistant_bidding_task'))
                ->orderBy('id', 'desc')
                ->first();

            if(is_null($getLatestOffer)) {
                return false;
            }

            return $getLatestOffer;
        } else {
            abort(404);
        }
    }

    public static function hideCBtnIfHasAssistantConfirmedClientOffer($taskID) {
        $user = Auth::user();

        if($user) {
            $getLatestOffer = BiddingTasks::where('assistant_id', $user->id)
                ->where('task_id', $taskID)
                ->where('status_id', config('constants.statuses.assistant_confirmed_client_bidding'))
                ->orderBy('id', 'desc')
                ->first();

            if(is_null($getLatestOffer)) {
                return false;
            }

            return $getLatestOffer;
        } else {
            abort(404);
        }
    }

    public static function checkHasChangedTaskPrice($task) {
        $checkHasChangedTaskPrice = UserStatusHistory::where('assistant_id', $task->assistant_id)
            ->where('listing_id', $task->id)
            ->where('status_id', config('constants.statuses.in_progress'))
            ->where('user_id', $task->user_id)
            ->orderBy('id', 'desc')
            ->first();

        $newTaskPrice = $checkHasChangedTaskPrice->offer_price;
        if(is_null($newTaskPrice)) {
            return false;
        } else if($newTaskPrice <= $task->price) {
            return false;
        }

        return $checkHasChangedTaskPrice;
    }

    public static function checkHasWantPayTaskCard($task) {
        $checkHasWantPayTaskCard = UserStatusHistory::where('assistant_id', $task->assistant_id)
            ->where('listing_id', $task->id)
            ->where('status_id', config('constants.statuses.assistant_want_pay_task_card'))
            ->where('user_id', $task->user_id)
            ->orderBy('id', 'desc')
            ->first();

        if(is_null($checkHasWantPayTaskCard)) {
            return false;
        }

        return $checkHasWantPayTaskCard;
    }

    public static function getTaskPriceAfterApprovedAssistant($task) {
        $checkHasWantPayTaskCard = UserStatusHistory::where('status_id', config('constants.statuses.in_progress'))
            ->where('listing_id', $task->id)
            ->where('user_id', $task->user_id)
            ->where('assistant_id', $task->assistant_id)
            ->orderBy('id', 'desc')
            ->first();

        if(is_null($checkHasWantPayTaskCard)) {
            return $task->price;
        }

        return $checkHasWantPayTaskCard->offer_price;
    }

    public static function getAssistantRankIconPathByEarnedMoney($totalEarnedMoney) {
        switch ($totalEarnedMoney >= 0) {
            case $totalEarnedMoney < config('constants.assistant.rank_conditions.rank_1_to'):
                return GeneralHelper::checkS3UrlPath('img/rank1.png');
                break;
            case $totalEarnedMoney >= config('constants.assistant.rank_conditions.rank_2_from') && $totalEarnedMoney < config('constants.assistant.rank_conditions.rank_2_to'):
                return GeneralHelper::checkS3UrlPath('img/rank2.png');
                break;
            case $totalEarnedMoney >= config('constants.assistant.rank_conditions.rank_3_from'):
                return GeneralHelper::checkS3UrlPath('img/rank3.png');
                break;
            default:
                return GeneralHelper::checkS3UrlPath('img/rank1.png');
        }
    }

    public static function getAssistantRankTooltipByEarnedMoney($totalEarnedMoney) {
        switch ($totalEarnedMoney >= 0) {
            case $totalEarnedMoney < config('constants.assistant.rank_conditions.rank_1_to'):
                return config('constants.assistant.rank_tooltip.rank_1');
                break;
            case $totalEarnedMoney >= config('constants.assistant.rank_conditions.rank_2_from') && $totalEarnedMoney < config('constants.assistant.rank_conditions.rank_2_to'):
                return config('constants.assistant.rank_tooltip.rank_2');
                break;
            case $totalEarnedMoney >= config('constants.assistant.rank_conditions.rank_3_from'):
                return config('constants.assistant.rank_tooltip.rank_3');
                break;
            default:
                return config('constants.assistant.rank_tooltip.rank_1');
        }
    }

    public static function getAssistantRankPercentByEarnedMoney($totalEarnedMoney) {
        switch ($totalEarnedMoney >= 0) {
            case $totalEarnedMoney < config('constants.assistant.rank_conditions.rank_1_to'):
                return config('constants.assistant.rank_percent.rank_1');
                break;
            case $totalEarnedMoney >= config('constants.assistant.rank_conditions.rank_2_from') && $totalEarnedMoney < config('constants.assistant.rank_conditions.rank_2_to'):
                return config('constants.assistant.rank_percent.rank_2');
                break;
            case $totalEarnedMoney >= config('constants.assistant.rank_conditions.rank_3_from'):
                return config('constants.assistant.rank_percent.rank_3');
                break;
            default:
                return config('constants.assistant.rank_percent.rank_1');
        }
    }

    public static function newGenerateVerifyTokenByUser($user) {
        $userID      = $user->id;
        $userEmail   = $user->email;
        $randomToken = str_random(40);
        $createToken = md5($userEmail.$user->created_at.$randomToken);

        $getOldVerifyRecord = VerifyUser::where('user_id', $userID)->orderBy('user_id', 'desc')->first();

        if(is_null($getOldVerifyRecord)) {
            $verifyUser = new VerifyUser();
            $verifyUser->user_id      = $userID;
            $verifyUser->random_token = $randomToken;
            $verifyUser->token        = $createToken;
            $verifyUser->save();
        } else {
            $getOldVerifyRecord->random_token = $randomToken;
            $getOldVerifyRecord->token        = $createToken;
            $getOldVerifyRecord->save();
        }

        $tokenUrl  = url('/verify-user?mail='. $userEmail .'&token='. $createToken );
        $msgVerify = '<p>За да завършите регистрацията си в AssistMe, потвърдете имейл адреса си като кликнете на линк-а по - долу 
            <br /><br /><a href="'. $tokenUrl .'" title="Линк за потвърждаване на имейла Ви">'. $tokenUrl .'</a>
            <br /><br />Ако се нуждаете от помощ, проверете <a href="'. route('frequently_asked_questions_page') .'" title="често задаваните въпроси">често задаваните въпроси</a> или <a href="'. route('contact_us_page') .'" title="свържете се с екипа ни за поддръжка">се свържете с екипа ни за поддръжка</a>.
            <br /><br />Благодарим Ви, че избрахте да използвате услугите ни.
            <br /><br />Поздрави,
            <br />Екипът на AssistMe
        </p>';

        GeneralHelper::sendDynamicalMail($user->getName(), $userEmail, 'AssistMe – потвърждаване на имейл адрес за регистрация', $msgVerify);
        self::addNewEmailNotificationHistories($userID, 'new_verify_token');

        return $user;
    }

    public static function getTaskChatIDByUsersIDs($taskID, $clientID, $assistantID) {
        if($taskID > 0 && $clientID > 0 && $assistantID) {
            return ListingChats::where('listing_id', $taskID)->where(function ($lcqu) use ($clientID, $assistantID) {
                $lcqu->where(function ($lcqs) use ($clientID, $assistantID) {
                    $lcqs->where('sender_id', $clientID);
                    $lcqs->where('recipient_id', $assistantID);
                });
                $lcqu->orWhere(function ($lcqr) use ($clientID, $assistantID) {
                    $lcqr->where('sender_id', $assistantID);
                    $lcqr->where('recipient_id', $clientID);
                });
            })->first();
        }

        return false;
    }
}