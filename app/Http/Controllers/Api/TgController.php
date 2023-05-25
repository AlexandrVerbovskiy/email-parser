<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\tg_users;
use App\Models\trello_users;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;

class TgController extends Controller
{
    protected string $token;

    protected string $base_url;

    protected $data;

    protected $chat_id;

    protected string $ApiToken;

    protected string $ApiKey;

    function __construct()
    {
        $this->token = "5892002404:AAFVeb0t4OiCMt3jlCZ5b8P6cXIwIjrCQbw";
        $this->base_url = "https://api.telegram.org/bot" . $this->token . "/";
//        $this->ApiToken = "ATTA8300ef671f6f1efaad494df68048e3b8e010e8b7d45b2b57dbeaa879a79fdb82461187C5";
//        $this->ApiKey = "15b5c7d5ab35953d609e5228792d4758";
    }

    function sendMessage($method = "sendMessage")
    {
//        try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://webhook.site/6ac25fe9-24a0-4dd0-b34e-3d709184830f");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            123);
        curl_exec($ch);
        curl_close($ch);
        $this->data = json_decode(file_get_contents('php://input'), JSON_OBJECT_AS_ARRAY);
        $mes = $this->data["message"]["text"];
        $this->chat_id = $this->data["message"]["chat"]["id"];
        if (isset($this->data["message"]["reply_to_message"]) && $this->data["message"]["reply_to_message"]["text"] != "" && $mes != "/start") {
            preg_match('/(Card Id: \w+)/', $this->data["message"]["reply_to_message"]["text"], $matches);
            preg_match('/(BY \w+ \w+)/', $this->data["message"]["reply_to_message"]["text"], $userTrello);
            $userTrello = str_replace(["BY ", "(", ")"], "", $userTrello);
            $userTrello = trello_users::where("name", $userTrello)->first();
            if (!str_contains($mes, "***"))
                $mes = $userTrello->tag . " " . $mes;
            else
                $mes = str_replace("***", "", $mes);
            $user = trello_users::where("tg_username", $this->data["message"]["from"]["username"])->first();
            $cardId = $matches[0];
            if ($cardId) {
                $cardId = str_replace(["Card Id: ", "(", ")"], "", $cardId);
                $query = [
                    'text' => $mes,
                    'key' => $user->key,
                    'token' => $user->token
                ];
                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => "https://api.trello.com/1/cards/{$cardId}/actions/comments",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => json_encode($query),
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json',
                    ),
                ));
                $res = curl_exec($curl);
                curl_close($curl);
            }
        }
        if (isset($mes) && $mes == "/start") {
            $id = $this->data["message"]["from"]["id"];
            $first_name = $this->data["message"]["from"]["first_name"];
            $username = $this->data["message"]["from"]["username"];
            $url = $this->base_url . $method . "?" . http_build_query(["chat_id" => $this->chat_id, "text" => "Hello, " . $first_name]);
            tg_users::updateOrCreate([
                "id" => $id,
                "name" => $username,
                "chat_id" => $this->chat_id
            ]);
            return json_decode(
                file_get_contents($url),
                JSON_OBJECT_AS_ARRAY
            );
        }
//        }catch (Exception $e){
//            $ch = curl_init();
//            curl_setopt($ch, CURLOPT_URL, "https://webhook.site/6ac25fe9-24a0-4dd0-b34e-3d709184830f");
//            curl_setopt($ch, CURLOPT_POST, 1);
//            curl_setopt($ch, CURLOPT_POSTFIELDS,
//                312);
//            curl_exec($ch);
//            curl_close($ch);
//        }

    }
}
