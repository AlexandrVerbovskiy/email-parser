<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;

class MailScrapper extends Command {

    protected $signature = 'command:mail';

    public function handle(){
        $scriptUrl = env("GOOGLE_SCRIPT_LINK")??null;
        if(!$scriptUrl) {
            var_dump("hasn't");
            return;
        }

        //$sender_filter = "<noreply@mailing.rabota.ua>";
        //<noreply@e.fiverr.com>
        //You've received messages from

        //$sender_filter = "Олександр Вербовський";
        $sender_filter = "yellowduckcoders@gmail.com";
        $subject_filter = "You've received messages from";
        //$subject_filter = "cofeeek";

        $data = array(
            "maxItems" => 30,
            "senderFilter" => $sender_filter,
            "subjectFilter" => $subject_filter,
            "timeFilter"=>60000 * 24 * 60//60000 - це одна хвилина
        );

        $ch = curl_init($scriptUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $result = curl_exec($ch);
        $result = json_decode($result, true);

        //<a href='read.php?id={$inbox['id']}'>

        if(!$result) return;
        $objects_to_send = [];
        foreach ($result as $inbox) {
            $crawler = new Crawler($inbox['body']);
            $type = "lead";

            $content = $crawler->filter(".MsoNormal+table .MsoNormal+table");
            if ($content->count() < 2) continue;

            $message_block = $content->eq(0);
            $message = $message_block->filter("span");
            if ($message->count() < 1) continue;
            $message = $message->text();

            $link_block = $content->eq(1);
            $link = $link_block->filter("a[href]");
            if ($link->count() < 1) continue;
            $link = $link->attr("href");

            if(str_contains($link, "order_id")) $type = "order";

            $name = explode($subject_filter, $inbox['subject']);
            if(count($name)<2) continue;

            $user_name = trim($name[1]);
            $objects_to_send[] = ["client"=>$user_name, "message"=>$message, "order_link" => $link, "type"=>$type];


            /*var_dump($inbox['id']);
            var_dump($inbox['sender']);
            var_dump($inbox['subject']);
            var_dump($inbox['time']);
            var_dump("");
            var_dump("");*/
            /*echo "<div><div style='display: flex;'>";
            echo "<div>Sender: {$inbox['sender']}</div>";
            echo "<div>Time: {$inbox['time']}</div>";
            echo "<div>Id: {$inbox['id']}</div>";
            echo "<div>Subject: {$inbox['subject']}</a></div>";
            echo "</div><div>{$inbox['body']}</div>";
            echo "</div>";*/
        }

        if(count($objects_to_send)<1) return;

        var_dump($objects_to_send);
    }
}
