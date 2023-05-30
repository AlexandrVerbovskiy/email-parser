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

        $sender_filter = "<noreply@e.fiverr.com>";
        $subject_filter = "You've received messages from";

        $data = array(
            "senderFilter" => $sender_filter,
            "subjectFilter" => $subject_filter,
            "timeFilter" => 60000*60*3//60000 - це одна хвилина
        );

        $ch = curl_init($scriptUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $result = curl_exec($ch);
        $result = json_decode($result, true);

        //<a href='read.php?id={$inbox['id']}'>

        if (!$result) return;

        $objects_to_send = [];
        foreach ($result as $inbox) {
            Storage::put($inbox['subject'], $inbox['body']);
            $crawler = new Crawler($inbox['body']);
            $type = "lead";

            $content = $crawler->filter(".content .content");
            if ($content->count() < 1) continue;
            $message = $content->text();

            $link = $crawler->filter("a[name='CTA']");
            if ($link->count() < 1) continue;

            $link = $link->attr("href");
            if (str_contains($link, "order_id")) $type = "order";

            $name = explode($subject_filter, $inbox['subject']);
            if (count($name) < 2) continue;

            $user_name = trim($name[1]);
            $objects_to_send[] = ["client" => $user_name, "message" => $message, "order_link" => $link, "type" => $type];
        }
        var_dump($objects_to_send);

        if (count($objects_to_send) < 1) return;
        return $controller->checkMessage($objects_to_send);
    }
}
