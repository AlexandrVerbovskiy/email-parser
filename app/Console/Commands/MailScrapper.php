<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\TrelloController;
use Dotenv\Util\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;

class MailScrapper extends Command
{

    protected $signature = 'command:mail {email_name}';

    private $limitations = [
        "fiver" => ["sender_filter" => "<noreply@e.fiverr.com>", "subject_filter" => "You've received messages from"],
        "upwork" => ["sender_filter" => "via Upwork", "subject_filter" => "You have unread messages about the job"],
    ];

    private $email_type_infos = [
        "main" => ["link" => "MAIN_GOOGLE_SCRIPT_LINK", "owner_email" => "yellowduckcoders@gmail.com"],
        "igor" => ["link" => "IGOR_GOOGLE_SCRIPT_LINK", "owner_email" => "igorpmyedu@gmail.com"]
    ];

    private function getBetween($content, $start, $end)
    {
        $r = explode($start, $content);
        if (isset($r[1])) {
            $r = explode($end, $r[1]);
            return $r[0];
        }
        return null;
    }

    private function fiverParse($inbox)
    {
        $crawler = new Crawler($inbox['body']);

        $content_selector = "td[style*='background-color'][style*='#ffffff']";
        $table_selector = $content_selector . ">table";
        $rows_selector = $table_selector . ">tbody>tr";

        $rows = $crawler->filter($rows_selector);
        if ($rows->count() <= 1) return null;

        $title = $rows->eq(2)->text();
        $message = $rows->eq(3)->text();

        $part_title_nick = explode(") left you message", $title)[0];
        if (!$part_title_nick) return null;
        $part_title_nick_split = explode("(@", $part_title_nick);
        if (!$part_title_nick_split) return null;
        $nick = $part_title_nick_split[1];
        $name = $part_title_nick_split[0];

        $answer_btn = $crawler->filter("a[href*='www.fiverr.com/inbox'], a[href*='www.fiverr.com/'][href*='linker'][href*='email_name=consolidated_messages']");
        if ($answer_btn->count() < 1) return null;

        $answer_link = $answer_btn->attr("href");
        $type = str_contains($answer_link, "order_id") ? "order" : "lead";

        return ["client" => $nick, "message" => $message,
            "order_link" => $answer_link, "type" => $type, "time" => $inbox["time"]];
    }

    private function upworkParse($inbox)
    {

        $crawler = new Crawler($inbox['body']);
        $message = [];
        $link = null;

        $index = 0;
        $crawler->filter("table[class*='card-box'][class*='first']")->each(function ($box) use (&$message, &$link, &$index) {
            $index++;
            $box->filter("td[class$='card-row'] div[class*='p-xl-left-md'][class*='m-0-top-md']")->each(function ($row) use (&$message) {
                $message[] = $row->text();
            });

            $link = $box->filter("[class$='button-holder'] a")->first()->attr("href");
            //$time = $box->filter("td[class*='card-row'] table table td div[style*='65735']")->first()->text();
        });

        $user_name = str_replace("\"", "", $inbox["sender"]);
        $user_name = explode($this->limitations["upwork"]["sender_filter"], $user_name)[0];
        return ["client" => trim($user_name), "message" => implode("\n", $message),
            "order_link" => $link, "type" => "lead", "time" => $inbox["time"]];
    }

    private function customParse($inbox)
    {
        $crawler = new Crawler($inbox['body']);
        $message = $crawler->filter("div")->first();
        if (!$message) return null;

        return [
            "time" => $inbox["time"],
            "subject" => $inbox["subject"],
            "client" => $inbox["sender"],
            "type" => "lead",
            "message" => "Title: " . $inbox["subject"] . "\nMessage: " . $message->text(),
            "order_link" => "",
            "platform" => "0"
        ];
    }

    public function handle(TrelloController $controller)
    {
        $email_name = strtolower($this->argument('email_name'));

        if (!array_key_exists($email_name, $this->email_type_infos)) {
            var_dump("hasn't email in env scripts link");
            return;
        }

        $mail_info = $this->email_type_infos[$email_name];

        $script_url = env($mail_info["link"]) ?? null;
        var_dump($script_url);

        if (!$script_url) {
            var_dump("hasn't script link in env");
            return;
        }

        /*To do: get client emails from DB*/
        $client_emails = ["daria.dudka@dataforseo.com"];

        $limitations = $this->limitations;
        foreach ($client_emails as $email) $limitations[] = ["sender_filter" => $email, "subject_filter" => ""];

        $data = array(
            "limitations" => $limitations,
            "timeFilter" => 5 * 60000
//            "timeFilter" => /*1 * 24 * 60 */ 60000 //60000 - це одна хвилина
        );

        $ch = curl_init($script_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $result = curl_exec($ch);
        $result = json_decode($result, true);

        if (array_key_exists("error", $result)) {
            $filename = random_bytes(10);
            $time = now()->setTimezone('Europe/Kiev')->format('Y-m-d H:i:s');
            Storage::put("messages-fails/not-parsing-error/" . $email_name . "_" . $filename . ".txt", $time . ": " . $result["error"]);
        }

        if (!$result) return;

        $objects_to_send = [];

        foreach ($result as $key => $inbox) {
            if (!$inbox['body']) continue;

            $type = null;
            $res = null;

            switch ($inbox['platform']) {
                case 'fiver':
                    $type = "fiver";
                    $res = $this->fiverParse($inbox);
                    break;
                case 'upwork':
                    $type = 'upwork';
                    $res = $this->upworkParse($inbox);
                    break;
                default:
                    $type = 'client';
                    $res = $this->customParse($inbox);
                    break;
            }

            if (!is_null($res)) {
                //$res["type"] = $type;
                $res["owner_email"] = $mail_info["owner_email"];
                $res["message"] = "On mail " . $mail_info["owner_email"] . ":\n" . $res["message"];
                $objects_to_send[] = $res;
            }

            if ($type && is_null($res)) {
                var_dump("Fails $type: ", $inbox['id']);
                Storage::put("messages-fails/$type/" . $inbox['id'] . ".html", $inbox['body']);
            }

        }

        var_dump($objects_to_send);

        if (count($objects_to_send) < 1) return;

        //return $controller->checkMessage($objects_to_send);
    }
}
