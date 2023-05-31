<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\TrelloController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;

class MailScrapper extends Command
{

    protected $signature = 'command:mail';

    public function handle(TrelloController $controller)
    {
        $scriptUrl = env("GOOGLE_SCRIPT_LINK") ?? null;
        if (!$scriptUrl) {
            var_dump("hasn't");
            return;
        }

        var_dump($scriptUrl);

        $sender_filter = "<noreply@e.fiverr.com>";
        $subject_filter = "You've received messages from";

        $data = array(
            "subjectFilter" => $subject_filter,
            "senderFilter" => $sender_filter,
            "timeFilter" => 60000//60000 - це одна хвилина
        );

        $ch = curl_init($scriptUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        if (!$result) return;

        $objects_to_send = [];
        foreach ($result as $key => $inbox) {
            if (!$inbox['body']) continue;

            $crawler = new Crawler($inbox['body']);
            $email_template = null;

            $content = $crawler->filter(".responsive-table");
            if ($content->count() < 1) {
                $content = $crawler->filter(".content-section>table>tbody>tr>td>table>tbody>tr");
                if ($content->count() < 1) {
                    Storage::put("_ERROR_" . $inbox['id'] . "(" . $inbox['subject'] . ")" . ".html", $inbox['body']);
                    continue;
                }
                $email_template = 2;

                $filteredArray = [];
                $content->each(function ($node) use (&$filteredArray) {
                    $filteredArray[] = $node;
                });

                $message = array_slice($filteredArray, -3, 1)[0];
                $message = $message->text();
            } else {
                $email_template = 1;
                $content = $content->filter(".content table");
                if ($content->count() < 1) {
                    Storage::put("_ERROR_" . $inbox['id'] . "(" . $inbox['subject'] . ")" . ".html", $inbox['body']);
                    continue;
                }
                $message = $content->text();
            }

            if (is_null($email_template)) {
                Storage::put("_ERROR_" . $inbox['id'] . "(" . $inbox['subject'] . ")" . ".html", $inbox['body']);
                continue;
            }

            $type = "lead";
            if ($email_template == 1) {
                $reply_btn = $crawler->filter("a[href*='www.fiverr.com/'][href*='linker'][href*='email_name=consolidated_messages']");
            } else if ($email_template == 2) {
                $reply_btn = $crawler->filter("a[href*='www.fiverr.com/inbox']");
            }

            if (count($reply_btn) < 1) {
                Storage::put("_ERROR_" . $inbox['id'] . "(" . $inbox['subject'] . ")" . ".html", $inbox['body']);
                continue;
            }

            $reply_btn = $reply_btn->attr("href");
            if (str_contains($reply_btn, "order_id")) $type = "order";

            $name = explode($subject_filter, $inbox['subject']);
            if (count($name) < 2) continue;

            $pattern = '/@([^)]+)\)/';
            preg_match($pattern, $inbox['textBody'], $matches);

            if (!isset($matches[1])) {
                Storage::put("_ERROR_" . $inbox['id'] . "(" . $inbox['subject'] . ")" . ".html", $inbox['body']);
                continue;
            } else {
                $link = "https://www.fiverr.com/inbox/".$matches[1];
            }

            $user_name = trim($name[1]);
            $objects_to_send[] = ["client" => $user_name, "message" => $message,
                "order_link" => $link, "type" => $type, "time" => $inbox["time"]];
        }
//        var_dump($objects_to_send);
        if (count($objects_to_send) < 1) return;
        return $controller->checkMessage($objects_to_send);
    }
}
