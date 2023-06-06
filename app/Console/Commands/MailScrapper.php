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

    private $limitations = [
        "fiver" => ["sender_filter" => "<noreply@e.fiverr.com>", "subject_filter" => "You've received messages from"],
        "upwork" => ["sender_filter" => "via Upwork", "subject_filter" => "You have unread messages about the job"],
    ];

    private function fiverParse($inbox)
    {
        $crawler = new Crawler($inbox['body']);
        $email_template = null;

        $content = $crawler->filter(".responsive-table");
        if ($content->count() < 1) {
            $content = $crawler->filter(".content-section>table>tbody>tr>td>table>tbody>tr");
            if ($content->count() < 1) return null;
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
            if ($content->count() < 1) return null;
            $message = $content->text();
        }

        if (is_null($email_template)) return null;

        $type = "lead";
        if ($email_template == 1) {
            $reply_btn = $crawler->filter("a[href*='www.fiverr.com/'][href*='linker'][href*='email_name=consolidated_messages']");
        } else if ($email_template == 2) {
            $reply_btn = $crawler->filter("a[href*='www.fiverr.com/inbox']");
        }

        if (count($reply_btn) < 1) return null;

        $reply_btn = $reply_btn->attr("href");
        if (str_contains($reply_btn, "order_id")) $type = "order";

        $name = explode($this->limitations["fiver"]["subject_filter"], $inbox['subject']);
        if (count($name) < 2) return null;

        $pattern = '/@([^)]+)\)/';
        preg_match($pattern, $inbox['textBody'], $matches);

        if (!isset($matches[1])) return null;

        $link = "https://www.fiverr.com/inbox/" . $matches[1];
        $user_name = trim($name[1]);
        return ["client" => $user_name, "message" => $message,
            "order_link" => $link, "type" => $type, "time" => $inbox["time"]];
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


    public function handle(TrelloController $controller)
    {
        $scriptUrl = env("GOOGLE_SCRIPT_LINK") ?? null;
        if (!$scriptUrl) {
            var_dump("hasn't");
            return;
        }

        $data = array(
            "limitations" => $this->limitations,
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
            switch ($inbox['platform']) {
                case 'fiver':
                    $res = $this->fiverParse($inbox);
                    if (!is_null($res)) $objects_to_send[] = $res;
                    break;
                case 'upwork':
                    $res = $this->upworkParse($inbox);
                    if (!is_null($res)) $objects_to_send[] = $res;
                    break;
                default:
                    break;
            }

        }
//        var_dump($objects_to_send);
        if (count($objects_to_send) < 1) return;
        return $controller->checkMessage($objects_to_send);
    }
}
