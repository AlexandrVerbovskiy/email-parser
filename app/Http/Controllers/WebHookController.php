<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WebHookController extends Controller
{
    protected string $TOKEN_TG;

    protected string $METHOD_TG;

    protected string $BASE_URL_TG;

    protected array $optionsTg;

    protected string $TOKEN_TRELLO;

    protected string $KEY_TRELLO;

    protected string $BASE_URL_TRELLO;

    protected string $ID_MODEL;

    protected array $optionsTrello;

    public function __construct($ID_MODEL = "63a94fa55fe2011491349d3b", $callbackUrl = "https://pasha-trello-bot.dev.yeducoders.com/api/trello")
    {
        $this->TOKEN_TG = "5892002404:AAFVeb0t4OiCMt3jlCZ5b8P6cXIwIjrCQbw";

        $this->METHOD_TG = "setWebhook";

        $this->BASE_URL_TG = "https://api.telegram.org/bot" . $this->TOKEN_TG . "/" . $this->METHOD_TG;

        $this->optionsTg = [
            "url" => "https://pasha-trello-bot.dev.yeducoders.com/api/tg"
        ];

        $this->TOKEN_TRELLO = "ATTA8300ef671f6f1efaad494df68048e3b8e010e8b7d45b2b57dbeaa879a79fdb82461187C5";

        $this->KEY_TRELLO = "15b5c7d5ab35953d609e5228792d4758";

        $this->BASE_URL_TRELLO = "https://api.trello.com/1/tokens/{$this->TOKEN_TRELLO}/webhooks/?key={$this->KEY_TRELLO}";

        $this->ID_MODEL = $ID_MODEL;

        $this->optionsTrello = [
            "callbackURL" => $callbackUrl,
            "idModel" => $this->ID_MODEL
        ];
    }

    public function tg()
    {
        $response = file_get_contents($this->BASE_URL_TG . "?" . http_build_query($this->optionsTg));
        var_dump($response);
    }

    public function trello()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->BASE_URL_TRELLO,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($this->optionsTrello),
        ));
        $response_trello = curl_exec($curl);
        curl_close($curl);
        var_dump($response_trello);
    }
}
