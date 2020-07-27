<?php
namespace App\Helpers;

use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Config;

class GeneralHelper
{    
    public static function getRandomString($length = 16) {
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle(str_repeat($pool, $length)), 0, $length);
    }
    
    public static function getMonthName($month) {
        $monthNames = [
            1  => 'януари',
            2  => 'февруари',
            3  => 'март',
            4  => 'април',
            5  => 'май',
            6  => 'юни',
            7  => 'юли',
            8  => 'август',
            9  => 'септември',
            10  => 'октомври',
            11  => 'ноември',
            12  => 'декември'
        ];
        
        return $monthNames[$month];
    }
    
    public static function getHiddenPhone($phone) {
        return substr('0'.$phone, 0, 2) . str_repeat('X', strlen('0'.$phone) - 2);
    }

    public static function shortenText($text, $char_count) {
        return mb_substr($text,0, $char_count - 3, "utf-8") . '...';
    }

    public static function formatDate($date) {
        $created    = new Carbon($date);
        $now        = Carbon::now();
        if ($created->diffInHours($now) < 24) {
            if($now->day - $created->day > 0 ){
                return 'Вчера, ' . $created->format('h:i') . ' часа';
            }
            return 'Днес, ' . $created->format('h:i') . ' часа';
        } else {
            return $created->day . ' ' . self::getMonthName($created->month) . ' ' . $created->year . ' г.';
        }
    }

    public static function getValidTimeTaskByDiffDates($createdOrUpdatedDate, $validDate) {
        $taskDate  = Carbon::parse($createdOrUpdatedDate);
        $diffHours = $validDate->diffInHours($taskDate);

        if($diffHours > 23) {
            return 31;
        }

        if($diffHours > 1) {
            return 23;
        }

        return 1;
    }

    public static function sendDynamicalMail($userName, $userEmail, $subject, $message) {
        $dataMessage = [
            'uName'     => $userName,
            'uEmail'    => $userEmail,
            'subject'   => $subject,
            'emailText' => $message
        ];

        Mail::send('email_templates.mails', $dataMessage, function($message) use ($dataMessage){
            $message->from(config('mail.username'), config('mail.from.address'));
            $message->to($dataMessage['uEmail'], $dataMessage['uName']);
            $message->subject($dataMessage['subject']);
        });
    }

    public static function dateFormatAssistme($date) {
        $day   = date_format($date, 'd');
        $month = date_format($date, 'F');
        $year  = date_format($date, 'Y');
        $hours = date_format($date, 'H:i');

        $months = [
            'January'   => 'Януари',
            'February'  => 'Февруари',
            'March'     => 'Март',
            'April'     => 'Април',
            'May'       => 'Май',
            'June'      => 'Юни',
            'July'      => 'Юли',
            'August'    => 'Август',
            'September' => 'Септември',
            'October'   => 'Октомври',
            'November'  => 'Ноември',
            'December'  => 'Декември'
        ];

        if(array_key_exists($month, $months)) {
            return $day .' '. lcfirst($months[$month]) . ' '. $year .', '. $hours .'ч.';
        }

        return  'Днес: '. $hours;
    }

    public static function sendDynamicSMSManualTemplate($phone, $sms_type, $cb = null, $data = null) {
        try {
            $account_sid = env('TWILIO_ACCOUNT_SID');
            $auth_token = env('TWILIO_AUTH_TOKEN');
            $twilio_number = env('TWILIO_NUMBER');
    
            $client = new Client($account_sid, $auth_token);
            $body = SMSHelper::getBody($sms_type, $data);
            $client->messages->create(
                $phone,
                [
                    'from' => $twilio_number,
                    'body' => $body
                ]
            );
            if($cb != null) {
                $cb();
            }
        } catch (Exception $e) {
            return 'Error sending sms..';
        }
    }

    public static function sendDynamicSMS($phone, $body, $cb = null) {
        try {
            $account_sid = env('TWILIO_ACCOUNT_SID');
            $auth_token = env('TWILIO_AUTH_TOKEN');
            $twilio_number = env('TWILIO_NUMBER');

            $client = new Client($account_sid, $auth_token);

            $client->messages->create(
                $phone,
                [
                    'from' => $twilio_number,
                    'body' => $body
                    ]
                );
            if($cb != null) {
                $cb();
            }
        } catch (Exception $e) {
            return 'Error sending sms..';
        }
    }

    public static function checkS3UrlPath($path) {
        if($path[0] == '/') $path = substr($path, 1, strlen($path));

        if(env('S3_FILE_PATH')) {
            return Config::get('constants.paths.s3_main_path') . $path;
        } else {
            return asset($path);
        }
    }
}