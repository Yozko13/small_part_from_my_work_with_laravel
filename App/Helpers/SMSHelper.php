<?php
namespace App\Helpers;

use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client;

class SMSHelper
{

    public static function getBody($sms_type, $data = null) {
        $returnVal = '';
        switch($sms_type) {
            case 'new_task':                               $returnVal = self::newInArea( ); break;
            case 'assistant_one_hour_before_started_task': $returnVal = self::oneHourRemainingAssistant(); break;
            case 'reminder_client_one_hour':               $returnVal = self::oneHourRemainingClient(); break;
            case 'assistant_interested_task':              $returnVal = self::assistantInterestedInTask(); break;
            case 'client_canceled_task':                   $returnVal = self::taskCanceled($data); break;
            case 'assistant_completed_task':               $returnVal = self::taskCompletedToClient(); break;
            case 'client_completed_task':                  $returnVal = self::taskCompletedToAssistant(); break;
            case 'client_sent_message':                    $returnVal = self::newMessage($data); break;
            case 'assistant_sent_message':                 $returnVal = self::newMessage($data); break;
            case 'client_review_assistant':                $returnVal = self::reviewForAssistant($data); break;
            case 'client_added_rating_assistant':          $returnVal = self::clientAddedRating($data); break;
            case 'assistant_want_pay_task_card':           $returnVal = self::assistantWantPrepay($data); break;
        }

        return $returnVal;
    }

    private static function newInArea() {
        $messageContent = 'Assistme.bg - има нова обява във вашата сфера и район.
Вижте и наддавайте.';

        return $messageContent;
    }

    private static function oneHourRemainingAssistant() {
        $oneHourContent = 'Assistme.bg - Имате предстояща задача след около час!';
        return $oneHourContent;
    }

    private static function oneHourRemainingClient() {
        $oneHourContent = 'Assistme.bg - Имате предстояща задача след около час!';
        return $oneHourContent;
    }

    private static function assistantInterestedInTask() {
        $messageContent = 'Assistme.bg - Имате ново предложение за вашата обява.';
        return $messageContent;
    }

    private static function taskCanceled($task) {
        $canceledContent = 'Assistme.bg - Задача ' . $task->name . ' бе отменена.';
        return $canceledContent;
    }

    private static function taskCompletedToClient() {
        $messageContent = 'Assistme.bg - Вашата задача е маркирана като завършена.
Моля, потвърдете.';
        return $messageContent;
    }

    private static function taskCompletedToAssistant() {
        $messageContent  = 'Assistme.bg - Клиентът потвърди завършването на вашата задача.';
        return $messageContent;
    }

    private static function newMessage($senderName) {
        $messageContent  = 'Assistme.bg - Имате съобщение от ' . $senderName . ' за ваша задача
Моля проверете.';
        return $messageContent;
    }

    private static function reviewForAssistant($task) {
        $messageContent  = 'Assistme.bg - Имате ново ревю от за ' . $task->name . '.';
        return $messageContent;
    }

    private static function clientAddedRating($task) {
        $messageContent  = 'Assistme.bg - Поставен е рейтинг за ' . $task->name . '.';
        return $messageContent;
    }

    private static function assistantWantPrepay($task) {
        $messageContent  = 'Assistme.bg - Изискано предварително плащане по ' . $task->name . '.';
        return $messageContent;
    }

    public static function reformatTemplate($content) {
        $contentLines = explode(PHP_EOL, $content);

        $newContent = [];
        foreach($contentLines as $line) {
            if(strpos($line, '<a href="') > -1) {
                $link = substr($line, (strpos($line, ' href=') + 6));
                $link = explode($link[0], $link);
                $line = $link;
                array_push($newContent, $line[1]);
            } else {
                array_push($newContent, trim($line));
            }
        }
        $newContentString = join(PHP_EOL, $newContent);
        $newContent = strip_tags($newContentString);

        return $newContent;
    }

}