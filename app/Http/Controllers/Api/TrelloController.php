<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WebHookController;
use App\Models\trello_users;
use App\Models\tg_users;
use App\Models\TgSubtask;
use App\Models\Board;
use App\Models\ClientStatistic;
use App\Models\DevStatistic;
use App\Models\AllClientsStatistic;
use App\Models\ProjectStatistic;
use App\Models\MilestoneStatistic;
use App\Models\ReleaseStatistic;
use App\Models\Card;
use App\Models\Project;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\SyncCards;
use App\Models\Release;
use App\Models\CardDate;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use function Symfony\Component\VarDumper\Dumper\esc;


class TrelloController extends Controller
{
    protected string $token;

    protected string $base_url;

    protected string $ApiToken;

    protected string $ApiKey;

    protected $dataTrello;

    protected $taskModel;

    protected $chat_id;

    protected $devs;

    function __construct()
    {
        $this->devs = [
            "Inna Pogrebna",
//            "Pavlo Melnyk",
            "Alex Verbovskiy",
            "Igor Dzhenkov",
            "Valerii Nuzhnyi"
        ];
        $this->token = "5892002404:AAFVeb0t4OiCMt3jlCZ5b8P6cXIwIjrCQbw";
        $this->base_url = "https://api.telegram.org/bot" . $this->token . "/";
        $this->ApiToken = "ATTA8300ef671f6f1efaad494df68048e3b8e010e8b7d45b2b57dbeaa879a79fdb82461187C5";
        $this->ApiKey = "15b5c7d5ab35953d609e5228792d4758";
        $this->taskModel = new Task();
    }

    public function manyCardsInProgress()
    {
        foreach ($this->devs as $dev) {
            $cards = Card::where("member", $dev)->where("board_id", "!=", 23)->where("column", "In progress")->get();
            if (count($cards) > 1) {
                $user = trello_users::where("name", $dev)->first();
                if ($user) {
                    $tgUser = tg_users::where("name", $user->tg_username)->first();
                    if ($tgUser) {
                        $params = [
                            'text' => "<b>ATTENTION!</b>
You have more than one task in progress
Please go to dashboard to move completed task to done column",
                            'chat_id' => $tgUser->chat_id,
                            'parse_mode' => 'HTML'
                        ];
                        $url = $this->base_url . "sendMessage" . "?" . http_build_query($params);
                        file_get_contents($url);
                    }
                }
            }
        }
    }

    public function removeDuplicate(Request $request)
    {
        $name = $request->input("name") ?? null;
        $options = [
            "key" => $this->ApiKey,
            "token" => $this->ApiToken
        ];
        $BOARD = null;
        $allBoards = json_decode(
            file_get_contents("https://api.trello.com/1/organizations/61a62d1ab530a327f2e18ce5/boards" . "?" . http_build_query($options))
        );
        foreach ($allBoards as $board) {
            if ($board->name == $name) {
                $BOARD = $board;
                break;
            }
        }
        if ($BOARD) {
            $allCards = json_decode(
                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/cards" . "?" . http_build_query($options))
            );
            $arr = [];
            $allCustomFields = json_decode(
                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
            );
            foreach ($allCustomFields as $customField) {
                if ($customField->name == "ID")
                    $customFieldId = $customField->id;
            }
            if (isset($customFieldId)) {
                foreach ($allCards as $card) {
                    $customFieldValueUrl = "https://api.trello.com/1/cards/{$card->id}/customFieldItems?" . http_build_query($options);
                    $result = file_get_contents($customFieldValueUrl);
                    $customFieldValue = json_decode($result, true);
                    foreach ($customFieldValue as $item) {
                        if (isset($item['value']['text'])) {
                            $trello_id = $card->id;
                            $custom_id = $item['value']['text'];
                        }
                        if (isset($item['value']['number']) && $item['value']['number']) {
                            $trello_id = $card->id;
                            $est = $item['value']['number'];
                        }
                    }
                    if (isset($trello_id))
                        $arr [] = ["trello_id" => $trello_id, "custom_id" => $custom_id ?? null, "est" => $est ?? null];
                }
            }
            if (count($arr)) {
                $grouped = [];
                foreach ($arr as $item) {
                    $grouped[$item['custom_id']][] = $item;
                }
                $duplicates = [];
                foreach ($grouped as $items) {
                    if (count($items) > 1) {
                        $duplicates = array_merge($duplicates, $items);
                    }
                }
                while (!empty($duplicates)) {
                    $duplicates2 = [];
                    $first = array_shift($duplicates);
                    $duplicates2[] = $first;

                    foreach ($duplicates as $key => $item) {
                        if ($item['custom_id'] === $first['custom_id']) {
                            $duplicates2[] = $item;
                            unset($duplicates[$key]);
                        }
                    }
                    $duplicates3 = [];

                    foreach ($duplicates2 as $item) {
                        $trello_id = $item["trello_id"];
                        $url = "https://api.trello.com/1/cards/$trello_id/actions?filter=commentCard&" . http_build_query($options);
                        $response = file_get_contents($url);
                        $comments = json_decode($response);
                        if (!empty($comments)) {
                            $item["check"] = true;
                        } else {
                            $item["check"] = false;
                        }
                        $duplicates3 [] = $item;
                    }
                    $tmp = false;
//                    $result = array_reduce($duplicates3, function($carry, $item) {
//                        return $carry && ($item["check"] == true);
//                    }, true);
                    $default = null;
                    for ($i = 0; $i < count($duplicates3); $i++) {
                        if ($duplicates3[$i]["check"])
                            $tmp = true;
                        if (empty($duplicates3[$i]["est"]))
                            $default = $duplicates3[$i];
                        if ($i != count($duplicates3) - 1 || $tmp) {
                            if (!$duplicates3[$i]["check"]) {
                                var_dump($duplicates3[$i]);
                                $trello_id1 = $duplicates3[$i]["trello_id"];
                                $url = "https://api.trello.com/1/cards/$trello_id1?closed=true&key=$this->ApiKey&token=$this->ApiToken";
                                $ch = curl_init($url);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_HEADER, false);

                                curl_exec($ch);

                                curl_close($ch);
                            }
                        } elseif (isset($default)) {
                            $trello_id1 = $default["trello_id"];
                            $url = "https://api.trello.com/1/cards/$trello_id1?closed=true&key=$this->ApiKey&token=$this->ApiToken";
                            $ch = curl_init($url);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HEADER, false);

                            curl_exec($ch);

                            curl_close($ch);
                        }

                    }
                }
            }

        }
    }

//    public function test()
//    {
//        $curl = curl_init();
////        $proxy = "161.123.93.35:5765";
////        $proxyAuth = "jicuoneg:6hkd2vk078ix";
////        curl_setopt($curl, CURLOPT_PROXY, $proxy);
////        curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
////        curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
//        curl_setopt_array($curl, array(
//            CURLOPT_URL => 'https://curl-workflow-dev.yeducoders.com/comments/',
//            CURLOPT_ENCODING => '',
//            CURLOPT_MAXREDIRS => 10,
//            CURLOPT_TIMEOUT => 0,
//            CURLOPT_FOLLOWLOCATION => true,
//            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//            CURLOPT_CUSTOMREQUEST => 'GET',
//        ));
//
//        $response = curl_exec($curl);
//        $info = curl_getinfo($curl);
//
//        curl_close($curl);
//        var_dump($response);
//    }

    function check()
    {
        var_dump("ok");
    }

    function moveCardsToWait()
    {
        $url = "https://api.trello.com/1/organizations/61a62d1ab530a327f2e18ce5/boards?key={$this->ApiKey}&token={$this->ApiToken}";
        $response = file_get_contents($url);
        if ($response === FALSE) {
            die('Error occurred!');
        }
        $boards = json_decode($response, true);
        $ch = curl_init();
        foreach ($boards as $board) {
            if ($board["name"] == "LEADS-PROJECTS")
                continue;
            $url = "https://api.trello.com/1/boards/{$board['id']}/lists?key={$this->ApiKey}&token={$this->ApiToken}";
            $BOARD = Board::where("board_id", $board['id'])->first();
            if ($BOARD && $BOARD->pm == "Igor Dzhenkov") {
                $response = file_get_contents($url);
                $lists = json_decode($response, true);
                $waitListId = null;
                $inProgressListId = null;
                $inProgressQAListId = null;
                if (is_array($lists)) {
                    foreach ($lists as $list) {
                        if ($list['name'] == 'Wait list') {
                            $waitListId = $list['id'];
                        }
                        if ($list['name'] == 'In progress') {
                            $inProgressListId = $list['id'];
                        }
                        if ($list['name'] == 'In progress for QA') {
                            $inProgressQAListId = $list['id'];
                        }
                    }
                    if ($inProgressListId && $waitListId) {
                        $url = "https://api.trello.com/1/lists/{$inProgressListId}/cards?key={$this->ApiKey}&token={$this->ApiToken}";
                        $response = file_get_contents($url);
                        $cards = json_decode($response, true);
                        foreach ($cards as $card) {
                            $url = "https://api.trello.com/1/cards/{$card['id']}/idList?key={$this->ApiKey}&token={$this->ApiToken}";
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('value' => $waitListId)));
                            $response = curl_exec($ch);
                        }
                        if ($inProgressQAListId) {
                            $url = "https://api.trello.com/1/lists/{$inProgressQAListId}/cards?key={$this->ApiKey}&token={$this->ApiToken}";
                            $response = file_get_contents($url);
                            $cards = json_decode($response, true);
                            foreach ($cards as $card) {
                                $url = "https://api.trello.com/1/cards/{$card['id']}/idList?key={$this->ApiKey}&token={$this->ApiToken}";
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array('value' => $waitListId)));
                                $response = curl_exec($ch);
                            }
                        }
                    }
                }
            }
        }
        curl_close($ch);
    }

    function checkMessage($data)
    {
        var_dump($data);
        if (is_array($data) && count($data)) {
            $options = [
                "key" => $this->ApiKey,
                "token" => $this->ApiToken
            ];
            foreach ($data as $item) {
                $check = false;
//                switch ($item["type"]) {
//                    case "order":
//                    {
                $allBoards = json_decode(
                    file_get_contents("https://api.trello.com/1/organizations/61a62d1ab530a327f2e18ce5/boards" . "?" . http_build_query($options))
                );
                foreach ($allBoards as $board) {
                    $client = explode(" | ", $board->name)[0];
                    if ($client == $item["client"]) {
                        $members = json_decode(
                            file_get_contents("https://api.trello.com/1/boards/{$board->id}/members" . "?" . http_build_query($options))
                        );
                        if (is_array($members) && count($members)) {
                            $mes = $item["message"];
                            $link = $item["order_link"];
                            $subtask = TgSubtask::create([
                                "name" => $mes,
                                "client" => $client
                            ]);
                            $keyboard = [
                                [
                                    ["text" => "Create Sub Task on this card", "callback_data" => "subtask_" . $subtask->id]
                                ]
                            ];
                            $encodedKeyboard = json_encode([
                                "inline_keyboard" => $keyboard,
                                "resize_keyboard" => true,
                                "one_time_keyboard" => true
                            ]);
                            foreach ($members as $member) {
                                if ($member->id == "63533314084f3800186552ca") {
                                    $trello_user = trello_users::where(["trello_id" => "630e0412ffc3b900d905f65a"])->first();
                                    $tg_user = tg_users::where(["name" => $trello_user->tg_username])->first();
                                    $tag = "@" . $tg_user->name;
                                    $params = [
                                        'text' => "Fiverr message: for $tag\nFROM: $client\nTEXT: $mes\nTYPE: Order\nREPLY: $link",
                                        'chat_id' => $tg_user->chat_id,
                                        'reply_markup' => $encodedKeyboard,
                                        'parse_mode' => 'HTML'
                                    ];
                                    $r = json_decode(
                                        file_get_contents($this->base_url . "sendMessage?" . http_build_query($params))
                                    );
                                    $check = true;
                                }
                            }
                        }
                        break;
                    }
                }
//                    case "lead":
//                    {
                if (!$check) {
                    $allCards = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/633a9b6a1cfa35019f8d27e7/cards" . "?" . http_build_query($options))
                    );
                    foreach ($allCards as $card) {
                        $client = explode(" - ", $card->name)[0];
                        $client = str_replace("Lead/", "", $client);
                        $client = str_replace("Lead  |  ", "", $client);
                        $client = str_replace("Lead | ", "", $client);
                        $client = str_replace("Project/", "", $client);
                        $client = str_replace("Project  |  ", "", $client);
                        $client = str_replace("Project | ", "", $client);
                        if ($client == $item["client"]) {
                            $members = json_decode(
                                file_get_contents("https://api.trello.com/1/cards/{$card->id}/members" . "?" . http_build_query($options)),
                            );
                            if (is_array($members) && count($members)) {
                                foreach ($members as $member) {
                                    if ($member->id == "63533314084f3800186552ca") {
                                        $trello_user = trello_users::where(["trello_id" => "630e0412ffc3b900d905f65a"])->first();
                                        $tg_user = tg_users::where(["name" => $trello_user->tg_username])->first();
                                        $tag = "@" . $tg_user->name;
                                        $mes = $item["message"];
                                        $link = $item["order_link"];
                                        $params = [
                                            'text' => "Fiverr message: for $tag\nFROM: $client\nTEXT: $mes\nTYPE: Lead\nREPLY: $link",
                                            'chat_id' => $tg_user->chat_id,
                                            'parse_mode' => 'HTML'
                                        ];
                                        $r = json_decode(
                                            file_get_contents($this->base_url . "sendMessage?" . http_build_query($params))
                                        );
                                        break;
                                    }
                                }
                            }
                            break;
                        }
//                    }
//                break;
//            }
                    }
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    function setStatistics($cardId, $listId, $options)
    {
        $est = 0;
        $inProgressTimes = [];
        $actions = json_decode(file_get_contents("https://api.trello.com/1/cards/{$cardId}/actions" . "?" . http_build_query($options)), true);
        $now = new DateTime();
        $startOfDay = clone $now;
        $startOfDay->setTime(0, 0, 0);

        foreach ($actions as $action) {
            $actionDate = new DateTime($action['date']);
            if ($actionDate < $startOfDay) {
                break;
            }
            if ($action['type'] == 'updateCard') {
                $data = $action['data'];
                if (isset($data['listAfter']) && $data['listAfter']['id'] == $listId) {
                    $inProgressTimes[] = $action['date'];
                } else if (isset($data['listBefore']) && $data['listBefore']['id'] == $listId) {
                    $inProgressTimes[] = $action['date'];
                }
            }
        }
        $totalTime = 0;
        for ($i = 0; $i < count($inProgressTimes); $i += 2) {
            $start = new DateTime($inProgressTimes[$i]);
            if (isset($inProgressTimes[$i + 1])) {
                $end = new DateTime($inProgressTimes[$i + 1]);
            } else {
                $end = new DateTime();
            }
            $diff = $start->diff($end);
            $totalTime += $diff->i + $diff->h * 60;
        }
        return ["date" => $startOfDay, "hours" => $totalTime / 60];
    }

    function parseComment($text)
    {
        $replace_callback = function ($matches) {
            switch ($matches[0]) {
                case "@vn":
                {
                    $replace = "@educationphp7";
                    break;
                }
                case "@rp":
                {
                    $replace = "@romanpaz1uk";
                    break;
                }
                case "@jl":
                {
                    $replace = "@julia75498585";
                    break;
                }
                case "@pm":
                {
                    $replace = "@user12779792";
                    break;
                }
                case "@rl":
                {
                    $replace = "@litvinroman2009";
                    break;
                }
                case "@av":
                {
                    $replace = "@alexverbovskiy";
                    break;
                }
                case "@ip":
                {
                    $replace = "@innapogrebna";
                    break;
                }
                case "@id":
                {
                    $replace = "@igordzhenkov2";
                    break;
                }
                case "@vk":
                {
                    $replace = "@user30922771";
                    break;
                }
                case "@vs":
                {
                    $replace = "@user13409428";
                    break;
                }
                case "@ks":
                {
                    $replace = "@user39959808";
                    break;
                }
//                case "@sk":
//                {
//                    $replace = "@sergiocorto";
//                    break;
//                }
            }
            return $replace ?? null;
        };

        $newText = preg_replace_callback('/@\w+/', $replace_callback, $text);
        return $newText;
    }

    function sendMessage($method = "sendMessage", $flag = false, $data = [])
    {
        if (!$flag) {
            $this->dataTrello = json_decode(file_get_contents('php://input'), true);
        } else {
            $this->dataTrello = $data;
        }
        $action = $this->dataTrello["action"]["display"]["translationKey"] !== "unknown" ? $this->dataTrello["action"]["display"]["translationKey"] : $this->dataTrello["action"]["type"];
        $options = [
            "key" => $this->ApiKey,
            "token" => $this->ApiToken
        ];
        if (isset($action)) {
            switch ($action) {
                case "action_add_to_organization_board":
                {
                    $board = $this->dataTrello["action"]["display"]["entities"]["board"]["id"];
                    $name = $this->dataTrello["action"]["display"]["entities"]["board"]["text"];
                    Board::create([
                        "board_id" => $board,
                        "name" => $name
                    ]);
                    $webhook = new WebHookController($board);
                    $webhook->trello();
                    break;
                }
                case "action_delete_card":
                {
                    $cardId = $this->dataTrello["action"]["data"]["card"]["id"];
                    $urlWorkflow = env("WORKFLOW_URL") . 'trello/card/delete';
                    $card = Card::where("card_id", $cardId)->first();
                    if ($card) {
                        $board = Board::where("id", $card->board_id)->first();
                        if ($board) {
                            $data = [
                                "trello_card_id" => $cardId,
                                "board_name" => $board->name,
                            ];
                            $ch = curl_init($urlWorkflow);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                            $response = curl_exec($ch);
                            curl_close($ch);
                            $card->delete();
                        }
                    }
                    break;
                }
                case "action_changed_a_start_date":
                {
                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                    $input_date = $this->dataTrello["action"]["display"]["entities"]["card"]["start"];
                    $date = new DateTime($input_date);
                    $output_date = $date->format('Y-m-d');
                    Card::where("card_id", $cardId)->update([
                        "start_date" => $output_date
                    ]);
                    break;
                }
                case "action_changed_a_due_date":
                {
                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                    $input_date = $this->dataTrello["action"]["display"]["entities"]["card"]["due"];
                    $date = new DateTime($input_date);
                    $output_date = $date->format('Y-m-d');
                    Card::where("card_id", $cardId)->update([
                        "end_date" => $output_date
                    ]);
                    break;
                }
                case "action_added_member_to_card":
                {
                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                    $member = $this->dataTrello["action"]["display"]["entities"]["member"]["text"];
                    $res = Card::where("card_id", $cardId)->update([
                        "member" => $member
                    ]);
                    break;
                }
                case "action_add_label_to_card":
                {
                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                    $part = $this->dataTrello["action"]["display"]["entities"]["label"]["text"];
                    $arr = ["Backend", "Frontend", "Meeting", "Estimation", "custom task", "Testing", 'Research'];
                    if (in_array($part, $arr))
                        Card::where("card_id", $cardId)->update([
                            "part" => $part
                        ]);
                    break;
                }
                case "action_comment_on_card":
                {
                    $board = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $boardId = $this->dataTrello["model"]["id"];
                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
                    $boardLink = "https://trello.com/b/" . $this->dataTrello["action"]["data"]["board"]["shortLink"];
                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                    $members = json_decode(
                        file_get_contents("https://api.trello.com/1/cards/{$cardId}/members" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $check = false;
                    if (isset($members)) {
//                        foreach ($members as $member) {
//                            $tmp = trello_users::where(["trello_id" => $member["id"]]);
//                            if ($tmp) {
//                                $check = true;
//                                break;
//                            }
//                        }
//                        if ($check) {
                        $card = $this->dataTrello["action"]["display"]["entities"]["card"]["text"];
                        $comment = $this->dataTrello["action"]["display"]["entities"]["comment"]["text"];
                        $pattern_name = '/@\w+/';
                        $isMatched = preg_match_all($pattern_name, $comment, $username);
                        $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
                        $creatorId = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["id"];
                        $communication = "";
                        $nameArrLink = explode(" | ", $this->dataTrello["action"]["data"]["board"]["name"]);
                        $nameLink = $nameArrLink[0] == "IP" || !isset($nameArrLink[2]) ? "IP" : $nameArrLink[0] . " - " . $nameArrLink[2];
                        $workflowLink = "https://projects.dev.yeducoders.com/" . $nameLink . ".html";
                        $urlWorkflow = env("WORKFLOW_URL") . 'comments/trello';
                        $card_id = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                        if ($this->dataTrello["action"]["data"]["list"]["name"] != "ARCHIVED COMMENTS") {
                            $cf = json_decode(
                                file_get_contents("https://api.trello.com/1/cards/$card_id/customFieldItems" . "?" . http_build_query($options))
                            );
                            $status = null;
                            $to = null;
                            $commentWorkflow = $comment;
                            $user_id = null;
                            $comment_bug = null;
                            preg_match('/\[(Bugs)\]\s+/', $commentWorkflow, $user_id2);
                            if (isset($user_id2[1])) {
                                $comment_bug = $user_id2[1];
                                $commentWorkflow = preg_replace('/\[(Bugs)\]\s+/', "", $commentWorkflow);
                            }
                            foreach ($cf as $item) {
                                if (isset($item->value->text)) {
                                    $replaceFunction = function ($matches) {
                                        return $matches[1];
                                    };
                                    $pattern = '/\[(https:\/\/imgur\.com.*?)\].*?\"smartCard-inline\"\)/';
                                    $commentWorkflow = preg_replace_callback($pattern, $replaceFunction, $commentWorkflow);
                                    $pattern = '/\[(https:\/\/i\.imgur\.com.*?)\].*?\"smartCard-inline\"\)/';
                                    $commentWorkflow = preg_replace_callback($pattern, $replaceFunction, $commentWorkflow);
                                    preg_match('/\[(.+)\]\s+/', $commentWorkflow, $user_id1);
                                    if (isset($user_id1[1])) {
                                        $user_id = $user_id1[1];
                                        $status = "client";
                                        $commentWorkflow = preg_replace('/\[(.+)\]\s+/', "", $commentWorkflow);
                                    }
                                    if ($isMatched) {
                                        $userTrello = trello_users::where("tag", $username[0][0])->first();
                                        if ($userTrello->name == "Client YDC")
                                            $status = "for client";
                                        $to = $userTrello->trello_id;
                                        $commentWorkflow = preg_replace($pattern_name, "", $commentWorkflow);
                                    }
                                    $commentWorkflow = preg_replace("/\[[^]]*]/", "", $commentWorkflow, 1);
                                    $data = array(
                                        'trello_user_id' => $creatorId,
                                        'client_id' => $user_id,
                                        'task_id' => strval($item->value->text),
                                        'comment' => trim($commentWorkflow),
                                        'status' => $status,
                                        "for_trello_user_id" => $to,
                                        "board_name" => $board["name"],
                                        "is_bug" => $comment_bug,
                                        "workflow_link" => $workflowLink
                                    );
                                }
                            }
                            if (isset($cf) && isset($data)) {
//                                $proxy = "161.123.93.35:5765";
//                                $proxyAuth = "jicuoneg:6hkd2vk078ix";

                                $curl = curl_init($urlWorkflow);
                                curl_setopt($curl, CURLOPT_POST, true);
//                                curl_setopt($curl, CURLOPT_PROXY, $proxy);
//                                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
//                                curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                                $r = curl_exec($curl);
                                curl_close($curl);
                            }
                        }
                        if ($this->dataTrello["action"]["data"]["list"]["id"] == "635665e24bbce6013c0c9ec1") {
                            $dataCard = json_decode(
                                file_get_contents("https://api.trello.com/1/cards/{$this->dataTrello["action"]["display"]["entities"]["card"]["id"]}" . "?" . http_build_query($options)),
                                JSON_OBJECT_AS_ARRAY
                            );
                            $communication = $dataCard["desc"];
                            $res = preg_match('/(Communication: \S+)/', $communication, $matches);
                            if (!$res)
                                preg_match('/(\(\S+)/', $communication, $matches);
                            if (isset($matches[0]) && $matches[0] && count($matches)) {
                                $communication = str_replace(["(", "Communication: ", ")"], "", $matches[0]);
                                $communication = preg_replace('/(\[\S+\])/', "", $communication);
                                $communication = "Communication: <a href='{$communication}'>{$communication}</a>";
                            }

                        }
                        $allCustomFields = json_decode(
                            file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                        );
                        $custom_id = "";
                        foreach ($allCustomFields as $customField) {
                            if ($customField->name == "ID")
                                $customFieldId = $customField->id;
                        }
                        if (isset($customFieldId)) {
                            $customFieldValueUrl = "https://api.trello.com/1/cards/{$cardId}/customFieldItems?" . http_build_query($options);
                            $result = file_get_contents($customFieldValueUrl);
                            $customFieldValue = json_decode($result, true);
                            foreach ($customFieldValue as $item) {
                                if (isset($item['value']['text']))
                                    $custom_id = $item['value']['text'];
                            }
                        }
                        $replaceFunction = function ($matches) {
                            return $matches[1];
                        };
                        $pattern = '/\[(https:\/\/imgur\.com.*?)\].*?\"smartCard-inline\"\)/';
                        $comment = preg_replace_callback($pattern, $replaceFunction, $comment);
                        $pattern = '/\[(https:\/\/i\.imgur\.com.*?)\].*?\"smartCard-inline\"\)/';
                        $comment = preg_replace_callback($pattern, $replaceFunction, $comment);

                        $workflowLink = $workflowLink . "?focus_card=" . $custom_id;
                        $dbCard = Card::where("card_id", $card_id)->first();
                        if ($dbCard){
                            $dbBoard = Board::where("id", $dbCard->board_id)->first();
                            $dbProject = Project::where("id", $dbCard->project_id)->first();
                            $dbMilestone = Milestone::where("id", $dbCard->milestone_id)->first();
                            $dbRelease = Release::where("id", $dbCard->release_id)->first();
                            $subtask = TgSubtask::create([
                                "name" => $comment,
                                "board" => $dbBoard->name,
                                "project" => $dbProject->text,
                                "milestone" => $dbMilestone->text,
                                "release" => $dbRelease->text
                            ]);
                            $keyboard = [
                                [
                                    ["text" => "Create Sub Task on this card", "callback_data" => "subtask_" . $subtask->id]
                                ]
                            ];
                            $encodedKeyboard = json_encode([
                                "inline_keyboard" => $keyboard,
                                "resize_keyboard" => true,
                                "one_time_keyboard" => true
                            ]);
                        }
                        if (isset($status) && $status == "client") {
                            $membersBoard = json_decode(
                                file_get_contents("https://api.trello.com/1/boards/{$boardId}/members" . "?" . http_build_query($options)),
                                JSON_OBJECT_AS_ARRAY
                            );
                            if (!empty($membersBoard)) {
                                foreach ($membersBoard as $member) {
                                    $user = trello_users::where(["trello_id" => $member["id"]])->first();
                                    if (!$user || $member["id"] == $creatorId || $user->tg_username == "julialipa")
                                        continue;
                                    $chat = tg_users::where(["name" => $user->tg_username])->get();
                                    if (isset($chat[0])) {
                                        $params = [
                                            'text' => "<b>ON BOARD</b> {$board["name"]}
<b>BY</b> {$creator}
<b>ON CARD</b> {$card}

<b>ADDED COMMENT</b> <em>{$comment}</em>

Trello Link: <a href='{$cardLink}'>{$cardLink}</a>
Card Id: {$this->dataTrello["action"]["display"]["entities"]["card"]["id"]}
PMD Link: <a href='{$workflowLink}'>{$workflowLink}</a>
PMD Id: {$custom_id}
{$communication}",
                                            'chat_id' => $chat[0]->chat_id,
                                            'reply_markup' => $encodedKeyboard ?? "",
                                            'parse_mode' => 'HTML'
                                        ];
                                        $response = json_decode(
                                            file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                            JSON_OBJECT_AS_ARRAY
                                        );
                                    }
                                }
                            }
                        }
                        elseif ($isMatched) {
                            foreach ($username[0] as $item) {
                                $user = trello_users::where(["tag" => $item])->get();
                                $chat = tg_users::where(["name" => $user[0]->tg_username])->get();
                                if (isset($chat[0])) {
                                    $params = [
                                        'text' => "<b>ON BOARD</b> {$board["name"]}
<b>BY</b> {$creator}
<b>ON CARD</b> {$card}

<b>ADDED COMMENT</b> <em>{$comment}</em>

Trello Link: <a href='{$cardLink}'>{$cardLink}</a>
Card Id: {$this->dataTrello["action"]["display"]["entities"]["card"]["id"]}
PMD Link: <a href='{$workflowLink}'>{$workflowLink}</a>
PMD Id: {$custom_id}
{$communication}",
                                        'chat_id' => $chat[0]->chat_id,
                                        'reply_markup' => $encodedKeyboard ?? "",
                                        'parse_mode' => 'HTML'
                                    ];
//                                    $url = $this->base_url . $method . "?" . http_build_query($params);
                                    json_decode(
                                        file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                        JSON_OBJECT_AS_ARRAY
                                    );
                                }
                            }
                        } else {
                            foreach ($members as $member) {
                                if (trello_users::where(["trello_id" => $member["id"]])->exists()) {
                                    $user = trello_users::where(["trello_id" => $member["id"]])->first();
                                    if (!$user || $member["id"] == $creatorId || $user->tg_username == "julialipa")
                                        continue;
                                    $chat = tg_users::where(["name" => $user->tg_username])->get();
                                    if (isset($chat[0])) {
                                        $params = [
                                            'text' => "<b>ON BOARD</b> {$board["name"]}
<b>BY</b> {$creator}
<b>ON CARD</b> {$card}

<b>ADDED COMMENT</b> <em>{$comment}</em>

Trello Link: <a href='{$cardLink}'>{$cardLink}</a>
Card Id: {$this->dataTrello["action"]["display"]["entities"]["card"]["id"]}
PMD Link: <a href='{$workflowLink}'>{$workflowLink}</a>
PMD Id: {$custom_id}
{$communication}",
                                            'chat_id' => $chat[0]->chat_id,
                                            'reply_markup' => $encodedKeyboard ?? "",
                                            'parse_mode' => 'HTML'
                                        ];
                                        json_decode(
                                            file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                            JSON_OBJECT_AS_ARRAY
                                        );
                                    }
                                }
                            }
//                            }
                        }
                    }
                    break;
                }
//                case "createCheckItem":
//                {
//                    $board = json_decode(
//                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
//                        JSON_OBJECT_AS_ARRAY
//                    );
//                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
//                    $boardLink = "https://trello.com/b/" . $this->dataTrello["action"]["data"]["board"]["shortLink"];
//                    $cardId = $this->dataTrello["action"]["data"]["card"]["id"];
//                    $members = json_decode(
//                        file_get_contents("https://api.trello.com/1/cards/{$cardId}/members" . "?" . http_build_query($options)),
//                        JSON_OBJECT_AS_ARRAY
//                    );
//                    $creatorId = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["id"];
//                    foreach ($members as $member) {
//                        if (trello_users::where(["trello_id" => $member["id"]])->exists()) {
//                            $card = $this->dataTrello["action"]["data"]["card"]["name"];
//                            $name = $this->dataTrello["action"]["data"]["checkItem"]["name"];
//                            $checklist = $this->dataTrello["action"]["data"]["checklist"]["name"];
//                            $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
//                            $user = trello_users::where(["trello_id" => $member["id"]])->get();
//                            if ($member["id"] == $creatorId)
//                                continue;
//                            $chat = tg_users::where(["name" => $user[0]->tg_username])->get();
//                            $params = [
//                                'text' => "<b>ON BOARD</b> {$board["name"]}
//<b>BY</b> {$creator}
//<b>ON CARD</b> {$card}
//<b>IN CHECKLIST</b> {$checklist}
//
//<b>ADD TASK</b> <em>{$name}</em>
//
//Card Link: <a href='{$cardLink}'>{$cardLink}</a>
//Card Id: {$this->dataTrello["action"]["data"]["card"]["id"]}
//Board Link: <a href='{$boardLink}'>{$boardLink}</a>",
//                                'chat_id' => $chat[0]->chat_id,
//                                'parse_mode' => 'HTML'
//                            ];
//                            json_decode(
//                                file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
//                                JSON_OBJECT_AS_ARRAY
//                            );
//                        }
//                    }
//                    break;
//                }
                case "action_move_card_from_list_to_list":
                {
                    Log::info("col", ["col" => $this->dataTrello]);
                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                    $board = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $dbBoard = Board::where("board_id", $board["id"])->first();
                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
                    $boardLink = "https://trello.com/b/" . $this->dataTrello["action"]["data"]["board"]["shortLink"];
                    $members = json_decode(
                        file_get_contents("https://api.trello.com/1/cards/{$cardId}/members" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $membersBoard = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/{$board["id"]}/members" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    if (isset($membersBoard)) {
                        $card = $this->dataTrello["action"]["display"]["entities"]["card"]["text"];
                        $listBefore = $this->dataTrello["action"]["display"]["entities"]["listBefore"]['text'];
                        $listAfter = $this->dataTrello["action"]["display"]["entities"]["listAfter"]['text'];
                        $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
                        $creatorId = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["id"];
                        $workflowLink = "https://projects.dev.yeducoders.com/" . str_replace(" ", "%20", $this->dataTrello["action"]["data"]["board"]["name"]) . ".html";
                        $boardId = $board["id"];
                        $boardName = $board["name"];
                        $custom_id = null;
                        $estimation = null;
                        $allCustomFields = json_decode(
                            file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                        );
                        foreach ($allCustomFields as $customField) {
                            if ($customField->name == "ID")
                                $customFieldId = $customField->id;
                        }
                        if (isset($customFieldId)) {
                            $customFieldValueUrl = "https://api.trello.com/1/cards/{$cardId}/customFieldItems?" . http_build_query($options);
                            $result = file_get_contents($customFieldValueUrl);
                            $customFieldValue = json_decode($result, true);
                            foreach ($customFieldValue as $item) {
                                if (isset($item['value']['text']))
                                    $custom_id = $item['value']['text'];
                            }
                        }
                        $customFieldValueUrl = "https://api.trello.com/1/cards/{$cardId}/customFieldItems?" . http_build_query($options);
                        $result = file_get_contents($customFieldValueUrl);
                        $customFieldValue = json_decode($result, true);
                        foreach ($customFieldValue as $item) {
                            if (isset($item['value']['number']) && $item['value']['number']) {
                                $estimation = $item['value']['number'];
                                $idCustomFieldEst = $item["idCustomField"];
                                $customFieldEstName = "https://api.trello.com/1/customFields/$idCustomFieldEst?" . http_build_query($options);
                                $customFieldEstName = file_get_contents($customFieldEstName);
                                $customFieldEstName = json_decode($customFieldEstName, true)['name'];
                                $customFieldEstName = lcfirst($customFieldEstName);
                            }
                        }
                        if ($dbBoard) {
                            $dbCard = Card::firstOrCreate(["card_id" => $cardId], ["custom_id" => $custom_id, "name" => $card, "board_id" => $dbBoard->id]);
                            if ($dbCard) {
                                $res = $this->setDbCard($dbCard, $dbBoard, $dbCard->pmd_link, $dbCard->part, null, "update");
                            }
                            if (isset($members[0]["fullName"]) && $dbCard->member != $members[0]["fullName"]) {
                                $dbCard->member = $members[0]["fullName"];
                                $dbCard->save();
                            }
                            if (isset($customFieldEstName) && $dbCard[$customFieldEstName] != $estimation) {
                                $dbCard[$customFieldEstName] = $estimation;
                                $dbCard->save();
                            }
                            if ($boardName == "LEADS-PROJECTS") {
                                switch ($members[0]["id"]) {
                                    case "630e0412ffc3b900d905f65a":
                                    {
                                        $dbCard->pm = "Igor Dzhenkov";
                                        $dbCard->save();
                                        break;
                                    }
                                    case "5a0dcfb2239a4e028a186b4b":
                                    case "5d6fc27133aed9512fa8eef8":
                                    {
                                        $dbCard->pm = "Nazar Platonov";
                                        $dbCard->save();
                                        break;
                                    }
                                    default:
                                        break;
                                }
                            } else {
                                $pm = null;
                                $counter = 0;
                                foreach ($membersBoard as $member) {
                                    if ($member['id'] == "630e0412ffc3b900d905f65a") {
                                        $pm = "Igor Dzhenkov";
                                        $counter++;

                                    } elseif ($member['id'] == "5a0dcfb2239a4e028a186b4b") {
                                        $pm = "Nazar Platonov";
                                        $counter++;
                                    }
                                }
                                if (!empty($pm)) {
                                    if ($counter == 1) {
                                        Board::where("id", $dbBoard->id)->update([
                                            "pm" => $pm
                                        ]);
                                        Card::where("board_id", $dbBoard->id)->update([
                                            "pm" => $pm
                                        ]);
                                    } elseif ($counter == 2) {
                                        Board::where("id", $dbBoard->id)->update([
                                            "pm" => "multi"
                                        ]);
                                        Card::where("board_id", $dbBoard->id)->update([
                                            "pm" => "multi"
                                        ]);
                                    }
                                }

                            }
                            $cardTrello = json_decode(file_get_contents("https://api.trello.com/1/cards/{$cardId}?key={$this->ApiKey}&token={$this->ApiToken}"), true);
                            if ($cardTrello && $cardTrello["start"] && $cardTrello["due"]) {
                                $dateStart = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $cardTrello["start"]);
                                $dateEnd = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $cardTrello["due"]);
                                $cardStart = $dateStart->format('Y-m-d');
                                $cardEnd = $dateEnd->format('Y-m-d');
                                $dbCard->start_date = $cardStart;
                                $dbCard->end_date = $cardEnd;
                                $dbCard->save();
                            }
                            $allCustomFields = json_decode(
                                file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                            );
                            $custom_id = "";
                            foreach ($allCustomFields as $customField) {
                                if ($customField->name == "ID")
                                    $customFieldId = $customField->id;
                            }
                            if (isset($customFieldId)) {
                                $customFieldValueUrl = "https://api.trello.com/1/cards/{$cardId}/customFieldItems?" . http_build_query($options);
                                $result = file_get_contents($customFieldValueUrl);
                                $customFieldValue = json_decode($result, true);
                                foreach ($customFieldValue as $item) {
                                    if (isset($item['value']['text']))
                                        $custom_id = $item['value']['text'];
                                }
                            }
                            if ($listBefore == "In progress" || $listBefore == "In progress for QA") {
                                if (isset($members) && is_array($members)) {
                                    $statistics = $this->setStatistics($cardId, $this->dataTrello["action"]["display"]["entities"]["listBefore"]["id"], $options);
                                    $dbBoard = Board::where("board_id", $boardId)->first();
                                    if ($dbBoard) {
                                        if ($listBefore == "In progress for QA") {
                                            $qa = true;
                                        } else {
                                            $qa = false;
                                        }
                                        $dbCardDate = CardDate::updateOrCreate(["card_id" => $dbCard->id, "date" => $statistics["date"], "qa" => $qa], ["hours" => $statistics["hours"]]);
                                    }
                                }
                            }
                            if (isset($res) && $res) {
                                $this->setColumn($res);
                            }
                            if ($listAfter == "Wait list" && $creator != $members[0]["fullName"]) {
                                if (isset($members) && is_array($members)) {
                                    $workflowLink = $workflowLink . "?focus_card=" . $custom_id;
                                    $user = trello_users::where(["name" => $members[0]["fullName"]])->first();
                                    $chat = tg_users::where(["name" => $user->tg_username])->first();
                                    $params = [
                                        'text' => "<b>ON BOARD </b> {$board["name"]}
<b>BY</b> {$creator}
<b>MOVED CARD</b> {$card}
<b>FROM LIST</b> {$listBefore}
<b>TO LIST</b> {$listAfter}

Trello Link: <a href='{$cardLink}'>{$cardLink}</a>,
Card Id: {$cardId}
PMD Link: <a href='{$workflowLink}'>{$workflowLink}</a>
PMD Id: {$custom_id}",
                                        'chat_id' => $chat->chat_id,
                                        'parse_mode' => 'HTML'
                                    ];
                                    json_decode(
                                        file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                        JSON_OBJECT_AS_ARRAY
                                    );
                                }
                            }
                            if ($listAfter == "Ready for QA" || $listAfter == "Ready for Deploy") {
                                $allCustomFields = json_decode(
                                    file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                                );

                                foreach ($allCustomFields as $customField) {
                                    if ($customField->name == "ID")
                                        $customFieldId = $customField->id;
                                }
                                if (isset($customFieldId)) {
                                    $customFieldValueUrl = "https://api.trello.com/1/cards/{$cardId}/customFieldItems?" . http_build_query($options);
                                    $result = file_get_contents($customFieldValueUrl);
                                    $customFieldValue = json_decode($result, true);
                                    foreach ($customFieldValue as $item) {
                                        if (isset($item['value']['text']))
                                            $value = $item['value']['text'];
                                    }
                                    if (isset($value)) {
                                        $dataWorkflow = array(
                                            "card_name" => $card,
                                            'trello_card_id' => $cardId,
                                            'board_name' => $boardName,
                                            'status' => $listAfter
                                        );
//                                    $proxy = "161.123.93.35:5765";
//                                    $proxyAuth = "jicuoneg:6hkd2vk078ix";
                                        $curl = curl_init();
//                                    curl_setopt($curl, CURLOPT_PROXY, $proxy);
//                                    curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
//                                    curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/update/status',
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($dataWorkflow),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json',
                                                'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                    }

                                }
                                $tmp = false;
                                foreach ($membersBoard as $member) {
                                    if ($member["id"] == "630e0412ffc3b900d905f65a")
                                        $tmp = true;
                                }
                                if ($tmp) {
                                    $workflowLink = $workflowLink . "?focus_card=" . $custom_id;
                                    $user = trello_users::where(["trello_id" => "630e0412ffc3b900d905f65a"])->get();
                                    $chat = tg_users::where(["name" => $user[0]->tg_username])->get();
                                    $params = [
                                        'text' => "<b>ON BOARD </b> {$board["name"]}
<b>BY</b> {$creator}
<b>MOVED CARD</b> {$card}
<b>FROM LIST</b> {$listBefore}
<b>TO LIST</b> {$listAfter}

Trello Link: <a href='{$cardLink}'>{$cardLink}</a>,
Card Id: {$cardId}
PMD Link: <a href='{$workflowLink}'>{$workflowLink}</a>
PMD Id: {$custom_id}",
                                        'chat_id' => $chat[0]->chat_id,
                                        'parse_mode' => 'HTML'
                                    ];
                                    json_decode(
                                        file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                        JSON_OBJECT_AS_ARRAY
                                    );
                                }
                            } elseif ($listAfter == "Bugs") {
                                $allLabels = json_decode(
                                    file_get_contents("https://api.trello.com/1/boards/$boardId/labels" . "?" . http_build_query($options)), true
                                );
                                $labelColor = $this->setColorLabel('1');
                                $labelId = false;
                                foreach ($allLabels as $l) {
                                    if ($l['name'] == '1') {
                                        $labelId = $l['id'];
                                        break;
                                    }
                                }
                                if ($labelColor !== false && $labelId === false) {
                                    $url = "https://api.trello.com/1/labels?name=1&color=$labelColor&idBoard=$boardId&key=$this->ApiKey&token=$this->ApiToken";
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, $url);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                    $response = curl_exec($ch);
                                    curl_close($ch);
                                    $labelId = json_decode($response)->id;
                                }
                                if ($labelColor !== false && $labelId) {
                                    $url = "https://api.trello.com/1/cards/$cardId/idLabels?" . http_build_query($options);
                                    $postFields = array(
                                        'value' => $labelId
                                    );
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, $url);
                                    curl_setopt($ch, CURLOPT_POST, 1);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    $response = curl_exec($ch);
                                    curl_close($ch);
                                }

                                $allCustomFields = json_decode(
                                    file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                                );

                                foreach ($allCustomFields as $customField) {
                                    if ($customField->name == "ID")
                                        $customFieldId = $customField->id;
                                }
                                if (isset($customFieldId)) {
                                    $customFieldValueUrl = "https://api.trello.com/1/cards/{$cardId}/customFieldItems?" . http_build_query($options);
                                    $result = file_get_contents($customFieldValueUrl);
                                    $customFieldValue = json_decode($result, true);
                                    foreach ($customFieldValue as $item) {
                                        if (isset($item['value']['text']))
                                            $value = $item['value']['text'];
                                    }
                                    if (isset($value)) {
                                        $dataWorkflow = array(
                                            "card_name" => $card,
                                            'trello_card_id' => $cardId,
                                            'board_name' => $boardName,
                                            'status' => $listAfter
                                        );
//                                    $proxy = "161.123.93.35:5765";
//                                    $proxyAuth = "jicuoneg:6hkd2vk078ix";
                                        $curl = curl_init();
//                                    curl_setopt($curl, CURLOPT_PROXY, $proxy);
//                                    curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
//                                    curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/update/status',
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($dataWorkflow),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json',
                                                'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                            ),
                                        ));

                                        $response = curl_exec($curl);

                                    }
                                }
                                $workflowLink = $workflowLink . "?focus_card=" . $custom_id;
                                foreach ($members as $member) {
                                    if (trello_users::where(["trello_id" => $member["id"]])->exists()) {
                                        $user = trello_users::where(["trello_id" => $member["id"]])->get();
                                        if ($member["id"] == $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["id"])
                                            continue;
                                        $chat = tg_users::where(["name" => $user[0]->tg_username])->get();
                                        $params = [
                                            'text' => "<b>ON BOARD </b> {$board["name"]}
<b>BY</b> {$creator}
<b>MOVED CARD</b> {$card}
<b>FROM LIST</b> {$listBefore}
<b>TO LIST</b> {$listAfter}

Trello Link: <a href='{$cardLink}'>{$cardLink}</a>,
Card Id: {$cardId}
PMD Link: <a href='{$workflowLink}'>{$workflowLink}</a>
PMD Id: {$custom_id}",
                                            'chat_id' => $chat[0]->chat_id,
                                            'parse_mode' => 'HTML'
                                        ];
                                        json_decode(
                                            file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                            JSON_OBJECT_AS_ARRAY
                                        );
                                    }
                                }
                            } elseif ($listAfter == "In progress" || $listAfter == "In progress for QA") {
                                $allCustomFields = json_decode(
                                    file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                                );

                                foreach ($allCustomFields as $customField) {
                                    if ($customField->name == "ID")
                                        $customFieldId = $customField->id;
                                }
                                if (isset($customFieldId)) {
                                    $customFieldValueUrl = "https://api.trello.com/1/cards/{$cardId}/customFieldItems?" . http_build_query($options);
                                    $result = file_get_contents($customFieldValueUrl);
                                    $customFieldValue = json_decode($result, true);
                                    foreach ($customFieldValue as $item) {
                                        if (isset($item['value']['text']))
                                            $value = $item['value']['text'];
                                    }
                                    if (isset($value)) {
                                        $dataWorkflow = array(
                                            "card_name" => $card,
                                            'trello_card_id' => $cardId,
                                            'board_name' => $boardName,
                                            'status' => $listAfter
                                        );
//                                    $proxy = "161.123.93.35:5765";
//                                    $proxyAuth = "jicuoneg:6hkd2vk078ix";
                                        $curl = curl_init();
//                                    curl_setopt($curl, CURLOPT_PROXY, $proxy);
//                                    curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
//                                    curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/update/status',
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($dataWorkflow),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json',
                                                'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                    }

                                }
                            } elseif ($listAfter == "Backlog") {
                                $allCustomFields = json_decode(
                                    file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                                );

                                foreach ($allCustomFields as $customField) {
                                    if ($customField->name == "ID")
                                        $customFieldId = $customField->id;
                                }
                                if (isset($customFieldId)) {
                                    $customFieldValueUrl = "https://api.trello.com/1/cards/{$cardId}/customFieldItems?" . http_build_query($options);
                                    $result = file_get_contents($customFieldValueUrl);
                                    $customFieldValue = json_decode($result, true);
                                    foreach ($customFieldValue as $item) {
                                        if (isset($item['value']['text']))
                                            $value = $item['value']['text'];
                                    }
                                    if (isset($value)) {
                                        $dataWorkflow = array(
                                            "card_name" => $card,
                                            'trello_card_id' => $cardId,
                                            'board_name' => $boardName,
                                            'status' => $listAfter
                                        );
//                                    $proxy = "161.123.93.35:5765";
//                                    $proxyAuth = "jicuoneg:6hkd2vk078ix";
                                        $curl = curl_init();
//                                    curl_setopt($curl, CURLOPT_PROXY, $proxy);
//                                    curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
//                                    curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/update/status',
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($dataWorkflow),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json',
                                                'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        Log::info("col1", ["col1" => $response]);

                                    }

                                }
                            } elseif ($listAfter == "Done") {
                                $allCustomFields = json_decode(
                                    file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                                );

                                foreach ($allCustomFields as $customField) {
                                    if ($customField->name == "ID")
                                        $customFieldId = $customField->id;
                                }
                                if (isset($customFieldId)) {
                                    $customFieldValueUrl = "https://api.trello.com/1/cards/{$cardId}/customFieldItems?" . http_build_query($options);
                                    $result = file_get_contents($customFieldValueUrl);
                                    $customFieldValue = json_decode($result, true);
                                    foreach ($customFieldValue as $item) {
                                        if (isset($item['value']['text']))
                                            $value = $item['value']['text'];
                                    }
                                    if (isset($value)) {
                                        $dataWorkflow = array(
                                            "card_name" => $card,
                                            'trello_card_id' => $cardId,
                                            'board_name' => $boardName,
                                            'status' => $listAfter
                                        );
//                                    $proxy = "161.123.93.35:5765";
//                                    $proxyAuth = "jicuoneg:6hkd2vk078ix";
                                        $curl = curl_init();
//                                    curl_setopt($curl, CURLOPT_PROXY, $proxy);
//                                    curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
//                                    curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/update/status',
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($dataWorkflow),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json',
                                                'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                    }
                                }
                            } elseif ($listAfter == "Project info") {
                                $allCustomFields = json_decode(
                                    file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                                );

                                foreach ($allCustomFields as $customField) {
                                    if ($customField->name == "ID")
                                        $customFieldId = $customField->id;
                                }
                                if (isset($customFieldId)) {
                                    $customFieldValueUrl = "https://api.trello.com/1/cards/{$cardId}/customFieldItems?" . http_build_query($options);
                                    $result = file_get_contents($customFieldValueUrl);
                                    $customFieldValue = json_decode($result, true);
                                    foreach ($customFieldValue as $item) {
                                        if (isset($item['value']['text']))
                                            $value = $item['value']['text'];
                                    }
                                    if (isset($value)) {
                                        $dataWorkflow = array(
                                            "card_name" => $card,
                                            'trello_card_id' => $cardId,
                                            'board_name' => $boardName,
                                            'status' => $listAfter
                                        );
//                                    $proxy = "161.123.93.35:5765";
//                                    $proxyAuth = "jicuoneg:6hkd2vk078ix";
                                        $curl = curl_init();
//                                    curl_setopt($curl, CURLOPT_PROXY, $proxy);
//                                    curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
//                                    curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/update/status',
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($dataWorkflow),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json',
                                                'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                    }
                                }
                            }
                        }
                    }
                    break;
                }
                case
                "workflow":
                {
                    $id = $this->dataTrello["trello_id"];
                    if (!isset($id) || !$id)
                        return response()->json(['message' => 'Trello ID not found'], 400);
                    $boardName = $this->dataTrello["board_name"];
                    $BOARD = null;
                    $COLUMN = null;
                    $CARD = null;
                    $allBoards = json_decode(
                        file_get_contents("https://api.trello.com/1/organizations/61a62d1ab530a327f2e18ce5/boards" . "?" . http_build_query($options))
                    );
                    foreach ($allBoards as $item) {
                        if ($item->name == $boardName) {
//                            $ch = curl_init();
//                            curl_setopt($ch, CURLOPT_URL, "https://webhook.site/ed78c015-5e94-474f-b89f-6f196caa57d9");
//                            curl_setopt($ch, CURLOPT_POST, 1);
//                            curl_setopt($ch, CURLOPT_POSTFIELDS,
//                                json_encode([$item->name, $boardName, str($item->name) == str($boardName)]));
//                            curl_exec($ch);
//                            curl_close($ch);
//                            echo $boardName;
                            $BOARD = $item;
                            $CARD = json_decode(
                                file_get_contents("https://api.trello.com/1/cards/$id" . "?" . http_build_query($options))
                            );
//                            foreach ($allCards as $card) {
//                                $data = json_decode(
//                                    file_get_contents("https://api.trello.com/1/cards/$card->id/customFieldItems" . "?" . http_build_query($options))
//                                );
//                                if (!count($data))
//                                    continue;
//                                foreach ($data as $item1) {
//                                    if (isset($item1->value->text) && $item1->value->text == strval($id)) {
//                                        $CARD = $card;
//                                        break;
//                                    }
//                                }
//                            }
                            break;
                        }
                    }
                    if ($BOARD && $CARD) {
                        $columns = json_decode(
                            file_get_contents("https://api.trello.com/1/boards/$BOARD->id/lists" . "?" . http_build_query($options))
                        );

                        foreach ($columns as $column) {
                            if ($column->name == $this->dataTrello["type"]) {
                                $COLUMN = $column;
                                break;
                            }
                        }
                        if (!$COLUMN) {
                            $curl = curl_init();
                            $params = array(
                                'name' => $this->dataTrello["type"],
                                'idBoard' => $BOARD->id,
                                'key' => $this->ApiKey,
                                'token' => $this->ApiToken,
                            );
                            curl_setopt_array($curl, array(
                                CURLOPT_URL => "https://api.trello.com/1/lists/",
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_POSTFIELDS => http_build_query($params),
                                CURLOPT_HTTPHEADER => array(
                                    "Content-Type: application/x-www-form-urlencoded",
                                ),
                            ));

                            $response = curl_exec($curl);

                            curl_getinfo($curl, CURLINFO_HTTP_CODE);
                            curl_close($curl);

                            if (isset(json_decode($response)->id))
                                $COLUMN = json_decode($response);
                            else
                                return response()->json(['message' => "Something went wrong"], 400);
                        }
                        if ($COLUMN) {
                            $curl = curl_init();
                            $obj = [
                                "value" => $COLUMN->id
                            ];
                            curl_setopt_array($curl, array(
                                CURLOPT_URL => "https://api.trello.com/1/cards/$CARD->id/idList?" . http_build_query($options),
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'PUT',
                                CURLOPT_POSTFIELDS => json_encode($obj),
                                CURLOPT_HTTPHEADER => array(
                                    'Content-Type: application/json'
                                )
                            ));

                            $response = curl_exec($curl);

                            curl_close($curl);
                            return response()->json(['message' => $BOARD->id], 200);
                        }
                        return response()->json(['message' => 'Column Not Found'], 400);
                    }
                    return response()->json(['message' => 'Board or Card Not Found'], 400);
                }
                case "card_trello":
                {
//                    return response()->json(['message' => 'Too many request'], 429);
                    Log::info("card-insert", ["card" => $this->dataTrello]);
                    $arrErrorsTg = ['Igor Dzhenkov', 'Valerii Nuzhnyi', 'Pavlo Melnyk'];
                    $arrErrorsTrello = ['pm', 'vn'];
                    $log = 0;
                    $id = $this->dataTrello["card"]["id"] ?? 0;
                    $subFlag = false;
                    if ($id && str_starts_with($id, 'SUB')) {
                        $subFlag = true;
                    }
                    if (isset($this->dataTrello["card"]["estimation"])) {
                        $estimation = $this->dataTrello["card"]["estimation"] ?: null;
                        if ($estimation) {
                            $nameEst = lcfirst(explode(" ", $estimation)[0]);
                            $valueEst = (int)explode(" ", $estimation)[1] / 60;
                            $est = $nameEst == "estim" ? $valueEst : null;
                            $estIP = $nameEst == "estimIP" ? $valueEst : null;
                            $extra = $nameEst == "extra" ? $valueEst : null;
                            $estQA = $nameEst == "estimQA" ? $valueEst : null;
                        }
                    }
                    if (isset($this->dataTrello["type"]) && $this->dataTrello["type"] != "Just comments") {
                        if (!isset($this->dataTrello["card"]["project"]) || !$this->dataTrello["card"]["project"] || $this->dataTrello["card"]["project"] == "null" || $this->dataTrello["card"]["project"] == null) {
                            if ($subFlag) {
                                $this->notifyMemberAboutTaskError($id, $arrErrorsTg, 'Project Name missing', $this->dataTrello["html_link"]);
                                $this->createTaskForError("SubTask", 'Project Name missing', $arrErrorsTrello);
                            }
                            return response()->json(['message' => 'Project Name missing'], 400);
                        }
                        if (!isset($this->dataTrello["card"]["milestone"]) || !$this->dataTrello["card"]["milestone"]) {
                            if ($subFlag) {
                                $this->notifyMemberAboutTaskError($id, $arrErrorsTg, 'Milestone Name missing', $this->dataTrello["html_link"]);
                                $this->createTaskForError("SubTask", 'Milestone Name missing', $arrErrorsTrello);
                            }
                            return response()->json(['message' => 'Milestone Name missing'], 400);
                        }
                        if (!isset($this->dataTrello["card"]["release"]) || !$this->dataTrello["card"]["release"]) {
                            if ($subFlag) {
                                $this->notifyMemberAboutTaskError($id, $arrErrorsTg, 'Release Name missing', $this->dataTrello["html_link"]);
                                $this->createTaskForError("SubTask", 'Release Name missing', $arrErrorsTrello);
                            }
                            return response()->json(['message' => 'Release Name missing'], 400);
                        }
                    }
                    $project = $this->dataTrello["card"]["project"] ?: null;
                    $milestone = $this->dataTrello["card"]["milestone"] ?: null;
                    $release = $this->dataTrello["card"]["release"] ?: null;
                    $part = $this->dataTrello["card"]["part"] ?? null;
                    if (isset($this->dataTrello["board_name"]))
                        $boardName = $this->dataTrello["board_name"];
                    else {
//                        $this->notifyValera('Board Name missing on card with id' . $id);
                        return response()->json(['message' => 'Board Name missing'], 400);
                    }
                    $BOARD = null;
                    $COLUMN = null;
                    $allBoards = json_decode(
                        file_get_contents("https://api.trello.com/1/organizations/61a62d1ab530a327f2e18ce5/boards" . "?" . http_build_query($options))
                    );
                    $log++;
                    foreach ($allBoards as $item) {
                        if ($item->name == $boardName) {
                            $BOARD = $item;
                        }
                    }
                    if ($BOARD) {
                        $columns = json_decode(
                            file_get_contents("https://api.trello.com/1/boards/$BOARD->id/lists" . "?" . http_build_query($options))
                        );
                        $log++;
                        if (!isset($this->dataTrello["type"])) {
//                            $this->notifyValera('Column Name missing on card with id' . $id);
                            return response()->json(['message' => 'Column Name missing'], 400);
                        }
                        $move = false;
                        if ($this->dataTrello["type"] == "In progress" || $this->dataTrello["type"] == "In progress for QA") {
                            $move = $this->dataTrello["type"];
                        }
                        foreach ($columns as $column) {
                            if ($move) {
                                if ($column->name == "Backlog") {
                                    $COLUMN = $column;
                                }
                                if ($column->name == $move) {
                                    $COLUMN_MOVE = $column;
                                }
                            } else {
                                if ($column->name == $this->dataTrello["type"]) {
                                    $COLUMN = $column;
                                    break;
                                }
                            }
                        }
                        if (!$COLUMN) {
                            $curl = curl_init();
                            $params = array(
                                'name' => !$move ? $this->dataTrello["type"] : "Backlog",
                                'idBoard' => $BOARD->id,
                                'key' => $this->ApiKey,
                                'token' => $this->ApiToken,
                            );
                            curl_setopt_array($curl, array(
                                CURLOPT_URL => "https://api.trello.com/1/lists/",
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_POSTFIELDS => http_build_query($params),
                                CURLOPT_HTTPHEADER => array(
                                    "Content-Type: application/x-www-form-urlencoded",
                                ),
                            ));
                            $log++;

                            $response = curl_exec($curl);

                            curl_getinfo($curl, CURLINFO_HTTP_CODE);
                            curl_close($curl);

                            if (isset(json_decode($response)->id))
                                $COLUMN = json_decode($response);
                            else
                                return response()->json(['message' => "Something went wrong"], 400);
                        }
                        if ($COLUMN) {
                            $headers = array(
                                'Content-Type: application/json',
                                'Accept: application/json'
                            );
                            if (!isset($this->dataTrello["card"]["name"])) {
//                                $this->notifyValera('Card Name missing on card with id' . $id);
                                return response()->json(['message' => 'Card Name missing'], 400);
                            }
                            $params = array(
                                'key' => $this->ApiKey,
                                'token' => $this->ApiToken,
                                'name' => $this->dataTrello["card"]["name"],
                                'idList' => $COLUMN->id
                            );
                            $cards = json_decode(
                                file_get_contents("https://api.trello.com/1/lists/$COLUMN->id/cards" . "?" . http_build_query($options))
                            );
                            $tmp = true;
//                            if ($cards) {
//                                foreach ($cards as $item) {
//                                    $cf = json_decode(
//                                        file_get_contents("https://api.trello.com/1/cards/$item->id/customFieldItems" . "?" . http_build_query($options))
//                                    );
//                                    if (!count($cf))
//                                        continue;
//                                    foreach ($cf as $item1) {
//                                        if (isset($item1->value->text) && $item1->value->text == strval($id)) {
//                                            $tmp = false;
//                                            break;
//                                        }
//                                    }
//                                }
//                            }
//                            if (!$tmp)
//                                return response()->json(['message' => 'Card already created'], 400);
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, 'https://api.trello.com/1/cards');
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);
                            curl_close($ch);
                            $log++;
                            $card = json_decode($response);
                            if ($card) {
                                if ($move && isset($COLUMN_MOVE)) {
                                    $ch = curl_init();
                                    $url = "https://api.trello.com/1/cards/{$card->id}?key={$this->ApiKey}&token={$this->ApiToken}";
                                    curl_setopt($ch, CURLOPT_URL, $url);
                                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['idList' => $COLUMN_MOVE->id]));
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    $response = curl_exec($ch);
                                }
                                if (isset($this->dataTrello["card"]["label"])) {
                                    $labelName = strval($this->dataTrello["card"]["label"]);
                                    $labelColor = $this->setColorLabel($labelName);
                                    $allLabels = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/labels" . "?" . http_build_query($options)), true
                                    );
                                    $labelId = false;
                                    foreach ($allLabels as $l) {
                                        if ($l['name'] == $labelName) {
                                            $labelId = $l['id'];
                                            break;
                                        }
                                    }
                                    if ($labelColor !== false && $labelId === false) {
                                        $url = "https://api.trello.com/1/labels?name=$labelName&color=$labelColor&idBoard=$BOARD->id&key=$this->ApiKey&token=$this->ApiToken";
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                        $response = curl_exec($ch);
                                        curl_close($ch);
                                        $labelId = json_decode($response)->id;
                                    }
                                    if ($labelColor !== false && $labelId) {
                                        $url = "https://api.trello.com/1/cards/$card->id/idLabels?" . http_build_query($options);
                                        $postFields = array(
                                            'value' => $labelId
                                        );
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_POST, 1);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                        $response = curl_exec($ch);
                                        curl_close($ch);
                                    }
                                }
                                if ($part) {
                                    switch ($part) {
                                        case "F":
                                        {
                                            $part = "Frontend";
                                            $labelColor = "green";
                                            break;
                                        }
                                        case "T":
                                        {
                                            $part = "Testing";
                                            $labelColor = "yellow";
                                            break;
                                        }
                                        case "E":
                                        {
                                            $part = "Estimation";
                                            $labelColor = "purple";
                                            break;
                                        }
                                        case "M":
                                        {
                                            $part = "Meeting";
                                            $labelColor = "red";
                                            break;
                                        }
                                        case "R":
                                        {
                                            $part = "Research";
                                            $labelColor = "black";
                                            break;
                                        }
                                        case "B":
                                        {
                                            $part = "Backend";
                                            $labelColor = "blue";
                                            break;

                                        }
                                        case "D":
                                        {
                                            $part = "Design";
                                            $labelColor = "yellow";
                                            break;

                                        }
                                    }
                                    $allLabels = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/labels" . "?" . http_build_query($options)), true
                                    );
                                    $labelId = false;
                                    foreach ($allLabels as $l) {
                                        if ($l['name'] == $part) {
                                            $labelId = $l['id'];
                                            break;
                                        }
                                    }
                                    if ($labelColor !== false && $labelId === false) {
                                        $url = "https://api.trello.com/1/labels?name=$part&color=$labelColor&idBoard=$BOARD->id&key=$this->ApiKey&token=$this->ApiToken";
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                        $response = curl_exec($ch);
                                        curl_close($ch);
                                        $labelId = json_decode($response)->id;
                                    }
                                    if ($labelColor !== false && $labelId) {
                                        $url = "https://api.trello.com/1/cards/$card->id/idLabels?" . http_build_query($options);
                                        $postFields = array(
                                            'value' => $labelId
                                        );
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_POST, 1);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                        $response = curl_exec($ch);
                                        curl_close($ch);
                                    }
                                }
                                if ($id) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $log++;

                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == "ID")
                                            $customFieldId = $customField->id;
                                    }
                                    if (isset($customFieldId)) {
                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldId/item";
                                        $data = array(
                                            'value' => array(
                                                'text' => $id
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);

                                        $log++;
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => "ID",
                                            "type" => "text"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );
                                            $log++;

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == "ID")
                                                    $customFieldId = $customField->id;
                                            }
                                            if (isset($customFieldId)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldId/item";
                                                $data = array(
                                                    'value' => array(
                                                        'text' => strval($id)
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                                $log++;
                                            }
                                        }
                                    }
                                }
                                if (isset($est)) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $log++;
                                    $nameEst = "Estim";
                                    $valueEst = $est;
                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == $nameEst)
                                            $customFieldEst = $customField->id;
                                    }
                                    if (isset($customFieldEst)) {
                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEst/item";
                                        $data = array(
                                            'value' => array(
                                                'number' => $valueEst
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);
                                        $log++;
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => $nameEst,
                                            "type" => "number"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );
                                            $log++;

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == $nameEst)
                                                    $customFieldEst = $customField->id;
                                            }
                                            if (isset($customFieldEst)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEst/item";
                                                $data = array(
                                                    'value' => array(
                                                        'number' => $valueEst
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                                $log++;
                                            }
                                        }
                                    }
                                }
                                if (isset($estIP)) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $log++;
                                    $nameEst = "EstimIP";
                                    $valueEst = $estIP;
                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == $nameEst)
                                            $customFieldEstIP = $customField->id;
                                    }
                                    if (isset($customFieldEstIP)) {
                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEstIP/item";
                                        $data = array(
                                            'value' => array(
                                                'number' => $valueEst
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);
                                        $log++;
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => $nameEst,
                                            "type" => "number"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );
                                            $log++;

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == $nameEst)
                                                    $customFieldEstIP = $customField->id;
                                            }
                                            if (isset($customFieldEstIP)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEstIP/item";
                                                $data = array(
                                                    'value' => array(
                                                        'number' => $valueEst
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                                $log++;
                                            }
                                        }
                                    }
                                }
                                if (isset($extra)) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $log++;
                                    $nameEst = "Extra";
                                    $valueEst = $extra;
                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == $nameEst)
                                            $customFieldExtra = $customField->id;
                                    }
                                    if (isset($customFieldExtra)) {
                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldExtra/item";
                                        $data = array(
                                            'value' => array(
                                                'number' => $valueEst
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);
                                        $log++;
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => $nameEst,
                                            "type" => "number"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );
                                            $log++;

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == $nameEst)
                                                    $customFieldExtra = $customField->id;
                                            }
                                            if (isset($customFieldExtra)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldExtra/item";
                                                $data = array(
                                                    'value' => array(
                                                        'number' => $valueEst
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                                $log++;
                                            }
                                        }
                                    }
                                }
                                if (isset($estQA)) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $log++;
                                    $nameEst = "EstimQA";
                                    $valueEst = $estQA;
                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == $nameEst)
                                            $customFieldEstQA = $customField->id;
                                    }
                                    if (isset($customFieldEstQA)) {
                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEstQA/item";
                                        $data = array(
                                            'value' => array(
                                                'number' => $valueEst
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);
                                        $log++;
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => $nameEst,
                                            "type" => "number"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );
                                            $log++;

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == $nameEst)
                                                    $customFieldEstQA = $customField->id;
                                            }
                                            if (isset($customFieldEstQA)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEstQA/item";
                                                $data = array(
                                                    'value' => array(
                                                        'number' => $valueEst
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                                $log++;
                                            }
                                        }
                                    }
                                }

                                if ($milestone) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $log++;
                                    $nameEst = "Mile";
                                    $valueEst = $milestone;
                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == $nameEst)
                                            $customFieldMile = $customField->id;
                                    }
                                    if (isset($customFieldMile)) {

                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldMile/item";
                                        $data = array(
                                            'value' => array(
                                                'text' => $valueEst
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);
                                        $log++;
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => $nameEst,
                                            "type" => "text"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );
                                            $log++;

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == $nameEst)
                                                    $customFieldMile = $customField->id;
                                            }
                                            if (isset($customFieldMile)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldMile/item";
                                                $data = array(
                                                    'value' => array(
                                                        'text' => $valueEst
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                                $log++;
                                            }
                                        }
                                    }
                                }
                                if ($release) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $log++;
                                    $nameEst = "Deploy";
                                    $valueEst = $release;
                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == $nameEst)
                                            $customFieldDeploy = $customField->id;
                                    }
                                    if (isset($customFieldDeploy)) {
                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldDeploy/item";
                                        $data = array(
                                            'value' => array(
                                                'text' => $valueEst
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);
                                        $log++;
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => $nameEst,
                                            "type" => "text"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );
                                            $log++;

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == $nameEst)
                                                    $customFieldDeploy = $customField->id;
                                            }
                                            if (isset($customFieldDeploy)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldDeploy/item";
                                                $data = array(
                                                    'value' => array(
                                                        'text' => $valueEst
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                                $log++;
                                            }
                                        }
                                    }
                                }
                                if (!isset($this->dataTrello["html_link"])) {
//                                    $this->notifyValera('Html Link to board missing on card with id' . $id);
                                    return response()->json(['message' => 'Html Link to board missing'], 400);
                                }
                                $html = str_replace(" ", "%20", $this->dataTrello["html_link"]);
                                $html .= "\n––––––––––––––––––––––––";
                                if (isset($this->dataTrello["card"]["description"]["links"]) && is_array($this->dataTrello["card"]["description"]["links"]) && count($this->dataTrello["card"]["description"]["links"])) {
                                    $html .= "\nLinks\n\n";
                                    foreach ($this->dataTrello["card"]["description"]["links"] as $item) {
                                        $html .= isset($item["title"]) ? $item["title"] . ": " . $item["url"] . "\n" : $item["url"] . "\n";
                                    }
                                }
                                if (isset($this->dataTrello["card"]["description"]["notes"]) && is_array($this->dataTrello["card"]["description"]["notes"]) && count($this->dataTrello["card"]["description"]["notes"])) {
                                    $html .= "\nNotes\n\n";
                                    foreach ($this->dataTrello["card"]["description"]["notes"] as $item) {
                                        $html .= $item["note"] . "\n";
                                    }
                                }
                                $html .= "––––––––––––––––––––––––";

                                $urlDesc = "https://api.trello.com/1/cards/$card->id?desc=" . urlencode($html) . "&key=$this->ApiKey&token=$this->ApiToken";

                                $ch = curl_init($urlDesc);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                curl_exec($ch);
                                curl_close($ch);
                                $log++;
                                if (isset($this->dataTrello["card"]["description"]["comments"]) && is_array($this->dataTrello["card"]["description"]["comments"]) && count($this->dataTrello["card"]["description"]["comments"])) {
                                    foreach ($this->dataTrello["card"]["description"]["comments"] as $item) {
                                        $user = trello_users::where("trello_id", $item["trello_user_id"])->first();
                                        $urlComment = "https://api.trello.com/1/cards/$card->id/actions/comments?key=$user->key&token=$user->token";
                                        $com = $this->parseComment($item["comment"]);
                                        if (isset($item["status"]) && $item["status"] == "for client") {
                                            $com = "@client_ydc " . $com;
                                        }
                                        $fields = array(
                                            'text' => isset($item["status"]) && $item["status"] == "client" ? "[" . $item["user_id"] . "][" . $item["user_name"] . "] " . $com : $com,
                                        );
                                        $ch = curl_init($urlComment);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_POST, true);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
                                        curl_exec($ch);
                                        curl_close($ch);
                                        $log++;

                                    }
                                }
//                                $url = 'https://api.trello.com/1/boards/' . $BOARD->id . '/members?key=' . $this->ApiKey . '&token=' . $this->ApiToken;
//
//                                $options = array(
//                                    CURLOPT_RETURNTRANSFER => true,
//                                    CURLOPT_URL => $url
//                                );
//
//                                $curl = curl_init();
//
//                                curl_setopt_array($curl, $options);
//
//                                $result = curl_exec($curl);
//
//                                curl_close($curl);
//
//                                if ($result !== false) {
//                                    $members = json_decode($result);
//                                    foreach ($members as $item) {
//                                        $url = "https://api.trello.com/1/cards/$card->id/idMembers";
//
//                                        $data = array(
//                                            'value' => $item->id,
//                                            'key' => $this->ApiKey,
//                                            'token' => $this->ApiToken
//                                        );
//
//                                        $ch = curl_init($url);
//
//                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
//                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
//                                        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
//
//                                        curl_exec($ch);
//                                        curl_close($ch);
//                                        $log++;
//                                    }
//                                }
                                if (isset($this->dataTrello["members"])) {
                                    foreach ($this->dataTrello["members"] as $member) {
                                        switch ($member["name"]) {
                                            case "vn":
                                            {
                                                $id_trello = "6426d8fe1187511d5b7b889e";
                                                break;
                                            }
                                            case "id":
                                            {
                                                $id_trello = "630e0412ffc3b900d905f65a";
                                                break;
                                            }
                                            case "rp":
                                            {
                                                $id_trello = "64ad2ba0aff100a95ea967ac";
                                                break;
                                            }
                                            case "np":
                                            {
                                                $id_trello = "5a0dcfb2239a4e028a186b4b";
                                                break;
                                            }
                                            case "vd":
                                            {
                                                $id_trello = "5d6fc27133aed9512fa8eef8";
                                                break;
                                            }
                                            case "pm":
                                            {
                                                $id_trello = "63533314084f3800186552ca";
                                                break;
                                            }
                                            case "rl":
                                            {
                                                $id_trello = "64abb23020b36875a4b2864c";
                                                break;
                                            }
                                            case "vs":
                                            {
                                                $id_trello = "6274da3db0e87f22e70771a9";
                                                break;
                                            }
                                            case "vp":
                                            {
                                                $id_trello = "5f5ce621b9ba0e5857bf47d0";
                                                break;
                                            }
                                            case "av":
                                            {
                                                $id_trello = "6356379622408601989151ed";
                                                break;
                                            }
                                            case "ip":
                                            {
                                                $id_trello = "6350edb15c48ed00526f410f";
                                                break;
                                            }
                                            case "vk":
                                            {
                                                $id_trello = "5d0bc87f619ba61448bbaf28";
                                                break;
                                            }
                                            case "jl":
                                            {
                                                $id_trello = "56a0f4739331c3eb5474b2d2";
                                                break;
                                            }
                                            case "ks":
                                            {
                                                $id_trello = "64b63ecf34af50068b5ef921";
                                                break;
                                            }
//                                            case "sk":
//                                            {
//                                                $id_trello = "5f43e38515fbf783c82dfb19";
//                                                break;
//                                            }
                                        }
                                        if (isset($id_trello)) {
                                            $url = "https://api.trello.com/1/cards/$card->id/idMembers";
                                            $data = array(
                                                'value' => $id_trello,
                                                'key' => $this->ApiKey,
                                                'token' => $this->ApiToken
                                            );

                                            $ch = curl_init($url);

                                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

                                            curl_exec($ch);
                                            curl_close($ch);
                                        }
                                    }
                                }
                                if ($this->dataTrello["card"]["start_date"] && $this->dataTrello["card"]["due_date"]) {
                                    $start_date = $this->dataTrello["card"]["start_date"];
                                    $due_date = $this->dataTrello["card"]["due_date"];

                                    $url = "https://api.trello.com/1/cards/{$card->id}?due={$due_date}&start={$start_date}&key={$this->ApiKey}&token={$this->ApiToken}";

                                    $ch = curl_init();

                                    curl_setopt($ch, CURLOPT_URL, $url);
                                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                                    $response = curl_exec($ch);

                                    curl_close($ch);
                                }
                                $url = "https://api.trello.com/1/cards/{$card->id}?key={$this->ApiKey}&token={$this->ApiToken}";

                                $response = file_get_contents($url);
                                $tmpCard = json_decode($response);
                                $res = $this->setDbCard($tmpCard, $BOARD, $this->dataTrello["html_link"], $part ?? null, $labelName ?? null);
                                if ($res) {
                                    $res->update([
                                        "desc" => $html
                                    ]);
                                    $this->setColumn($res);
                                    $dbBoard = Board::where("board_id", $BOARD->id)->first();
                                    $this->setWayCard($dbBoard->id, $project, $milestone, $release, $res);
                                    if (!$subFlag) {
                                        $currentNote = SyncCards::where("board_id", $dbBoard->id)->first();
                                        if (!$currentNote || now()->greaterThan(Carbon::parse($currentNote->date)->addMinutes(10))) {
                                            SyncCards::updateOrCreate(["board_id" => $dbBoard->id], ["date" => now()]);
                                            $params = [
                                                'text' => "<b>CARD INITIALIZATION STARTED</b>
<b>Your recent card initialization</b>
<b>for board</b> {$boardName}
<b>was started</b>",
                                                'chat_id' => "5548342573",
                                                'parse_mode' => 'HTML'
                                            ];
                                            json_decode(
                                                file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                                JSON_OBJECT_AS_ARRAY
                                            );
                                        }
                                    }
                                }
                                if ($subFlag && isset($id_trello)) {
                                    $user = trello_users::where("trello_id", $id_trello)->get();
                                    $chat = tg_users::where("name", $user[0]->tg_username)->get();
                                    $cardLink = "https://trello.com/c/" . $card->shortLink;
                                    $params = [
                                        'text' => "<b>ON BOARD </b> {$boardName}
<b>INIT CARD</b> {$this->dataTrello["card"]["name"]}
<b>BY Igor Dzhenkov</b>
<b>IN {$this->dataTrello["type"]}</b>

Trello Link: <a href='{$cardLink}'>{$cardLink}</a>,
Card Id: {$card->id}
PMD Link: <a href='{$this->dataTrello["html_link"]}'>{$this->dataTrello["html_link"]}</a>
PMD Id: {$id}",
                                        'chat_id' => $chat[0]->chat_id,
                                        'parse_mode' => 'HTML'
                                    ];
                                    json_decode(
                                        file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                        JSON_OBJECT_AS_ARRAY
                                    );
                                }
                                return response()->json(['board_id' => $BOARD->id, 'trello_card_id' => $card->id], 200);
                            }
                            return response()->json(['message' => 'Card Not Found'], 400);
//                            return response()->json(['message' => 'Card Already Exists'], 400);
                        }
                        if ($subFlag) {
                            $this->notifyMemberAboutTaskError($id, $arrErrorsTg, 'Column Not Found', $this->dataTrello["html_link"]);
                            $this->createTaskForError("SubTask", 'Column Not Found', $arrErrorsTrello);
                        }
                        return response()->json(['message' => 'Column Not Found'], 400);
                    }
                    if ($subFlag) {
                        $this->notifyMemberAboutTaskError($id, $arrErrorsTg, 'Board Not Found', $this->dataTrello["html_link"]);
                        $this->createTaskForError("SubTask", 'Board Not Found', $arrErrorsTrello);
                    }
                    return response()->json(['message' => 'Board Not Found'], 400);
                }
                case
                "add_comment_workflow":
                {
                    Log::info("com", ["com" => $this->dataTrello]);
//                    return response()->json(['message' => 'Too many request'], 429);
                    $arrErrorsTg = ['Igor Dzhenkov', 'Valerii Nuzhnyi', 'Pavlo Melnyk'];
                    $arrErrorsTrello = ['pm', 'vn'];
                    $log = 0;
                    if (isset($this->dataTrello["card"]["description"]["comments"]) && is_array($this->dataTrello["card"]["description"]["comments"]) && count($this->dataTrello["card"]["description"]["comments"])) {
                        $BOARD = null;
                        $id = $this->dataTrello["trello_id"];
                        foreach ($this->dataTrello["card"]["description"]["comments"] as $item) {
                            $item = $this->parseComment($item);
                            if (isset($this->dataTrello["board_name"]))
                                $boardName = $this->dataTrello["board_name"];
                            else {
                                $this->notifyMemberAboutError($item, $arrErrorsTg, 'Board Name missing');
                                $this->createTaskForError("Comment", 'Board Name missing', $arrErrorsTrello);
                                return response()->json(['message' => 'Board Name missing'], 400);
                            }
                            $allBoards = json_decode(
                                file_get_contents("https://api.trello.com/1/organizations/61a62d1ab530a327f2e18ce5/boards" . "?" . http_build_query($options))
                            );
                            foreach ($allBoards as $item1) {
                                if ($item1->name == $boardName) {
                                    $BOARD = $item1;
                                }
                            }
                            if (!isset($id)) {
                                $this->notifyMemberAboutError($item, $arrErrorsTg, 'Card trello id not found');
                                $this->createTaskForError("Comment", 'Card trello id not found', $arrErrorsTrello);
                                return response()->json(['message' => 'Card trello id not found'], 400);
                            }
                            if ($BOARD) {
                                $CARD = json_decode(
                                    file_get_contents("https://api.trello.com/1/cards/$id" . "?" . http_build_query($options))
                                );
                                if ($CARD) {
                                    $user = trello_users::where("trello_id", $this->dataTrello["trello_user_id"])->first();
                                    if (isset($this->dataTrello["status"]) && $this->dataTrello["status"] == "for client") {
                                        $item = "@client_ydc " . $item;
                                    }
                                    if (isset($this->dataTrello["is_bug"]) && $this->dataTrello["is_bug"]) {
                                        $item = "[" . $this->dataTrello["is_bug"] . "] " . $item;
                                    }
                                    $fields = array(
                                        'text' => isset($this->dataTrello["status"]) && $this->dataTrello["status"] == "client" ? "[" . $this->dataTrello["user_id"] . "] [" . $this->dataTrello["user_name"] . "] " . $item : $item,
                                    );
                                    $urlComment = "https://api.trello.com/1/cards/$CARD->id/actions/comments?key=$user->key&token=$user->token";
                                    $ch = curl_init($urlComment);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_POST, true);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
                                    $res = curl_exec($ch);
                                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);
                                    $decodedResponse = json_decode($res, true);
                                    if ($httpCode != 200) {
                                        $errorMessage = $decodedResponse['message'] ?? "Unknown error";

                                        $this->notifyMemberAboutError($item, $arrErrorsTg, $errorMessage);
                                        $this->createTaskForError("Comment", $errorMessage, $arrErrorsTrello);
                                        return response()->json(['message' => $errorMessage], 400);
                                    } elseif (isset($decodedResponse['error'])) {
                                        $this->notifyMemberAboutError($item, $arrErrorsTg, $decodedResponse['message']);
                                        $this->createTaskForError("Comment", $decodedResponse['message'], $arrErrorsTrello);
                                        return response()->json(['message' => $decodedResponse['message']], 400);
                                    }
                                } else {
                                    $this->notifyMemberAboutError($item, $arrErrorsTg, 'Card Not Found');
                                    $this->createTaskForError("Comment", 'Card Not Found', $arrErrorsTrello);
                                    return response()->json(['message' => 'Card Not Found'], 400);
                                }
                            } else {
                                $this->notifyMemberAboutError($item, $arrErrorsTg, 'Board Not Found');
                                $this->createTaskForError("Comment", 'Board Not Found', $arrErrorsTrello);
                                return response()->json(['message' => 'Board Not Found'], 400);
                            }
                        }
                        return response()->json(['board_id' => $BOARD->id], 200);
                    } else {
                        $this->notifyMemberAboutError("", $arrErrorsTg, 'Comment Not Found');
                        $this->createTaskForError("Comment", 'Comment Not Found', $arrErrorsTrello);
                        return response()->json(['message' => 'Comment Not Found'], 400);
                    }
                }
//                case "archive_card":
//                {
//                    if (isset($this->dataTrello["board_name"])) {
//                        $allBoards = json_decode(
//                            file_get_contents("https://api.trello.com/1/organizations/61a62d1ab530a327f2e18ce5/boards" . "?" . http_build_query($options))
//                        );
//                        foreach ($allBoards as $item) {
//                            if ($item->name == $this->dataTrello["board_name"]) {
//                                $BOARD = $item;
//                                break;
//                            }
//                        }
//                        if (isset($BOARD)) {
//                            $cards = json_decode(
//                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/cards" . "?" . http_build_query($options))
//                            );
//                            $CARD = null;
//                            foreach ($cards as $card) {
//                                if ($card->name == $this->dataTrello["card"]["name"]) {
//                                    $data = json_decode(
//                                        file_get_contents("https://api.trello.com/1/cards/$card->id/customFieldItems" . "?" . http_build_query($options))
//                                    );
//                                    if (!count($data))
//                                        continue;
//                                    foreach ($data as $item1) {
//                                        if (isset($item1->value->text) && $item1->value->text == strval($this->dataTrello["card"]["id"])) {
//                                            $CARD = $card;
//                                            break;
//                                        }
//                                    }
//                                }
//                            }
//                            if ($CARD) {
//                                $url = "https://api.trello.com/1/cards/{$CARD->id}?closed=true&key=$this->ApiKey&token=$this->ApiToken";
//                                $ch = curl_init($url);
//                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
//                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//                                curl_setopt($ch, CURLOPT_HEADER, false);
//
//                                curl_exec($ch);
//
//                                curl_close($ch);
//                                return response()->json(['message' => 'Card has been archived'], 200);
//                            } else
//                                return response()->json(['message' => 'Card not found or archived earlier'], 200);
//                        }
//                        return response()->json(['message' => 'Board Not Found'], 400);
//                    }
//                    return response()->json(['message' => 'Board Not Found'], 400);
//                }
                case "update_card_trello":
                {
                    if (!isset($this->dataTrello["card"]["trello_id"])) {
//                        $this->notifyValera('Card trello id not found');
                        return response()->json(['message' => 'Card trello id not found'], 400);
                    }
                    if (isset($this->dataTrello["board_name"])) {
                        $allBoards = json_decode(
                            file_get_contents("https://api.trello.com/1/organizations/61a62d1ab530a327f2e18ce5/boards" . "?" . http_build_query($options))
                        );
                        foreach ($allBoards as $item) {
                            if ($item->name == $this->dataTrello["board_name"]) {
                                $BOARD = $item;
                                break;
                            }
                        }
                        if (isset($BOARD)) {
                            $cards = json_decode(
                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/cards" . "?" . http_build_query($options))
                            );
                            $CARD = null;
                            foreach ($cards as $card) {
                                if ($card->id == $this->dataTrello["card"]["trello_id"]) {
                                    $CARD = $card;
                                }
                            }
                            if ($CARD) {
                                if (isset($this->dataTrello["card"]["label"]) || (array_key_exists("label", $this->dataTrello["card"]) && empty($this->dataTrello["card"]["label"]))) {
                                    $labelName = strval($this->dataTrello["card"]["label"]);
                                    $labelColor = $this->setColorLabel($labelName);
                                    $allLabelsCards = json_decode(
                                        file_get_contents("https://api.trello.com/1/cards/$CARD->id/labels" . "?" . http_build_query($options))
                                    );
                                    foreach ($allLabelsCards as $label) {
                                        $url = "https://api.trello.com/1/cards/$CARD->id/idLabels/$label->id?" . http_build_query($options);
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                        $response = curl_exec($ch);
                                        curl_close($ch);
                                    }
                                    $allLabels = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/labels" . "?" . http_build_query($options)), true
                                    );
                                    $labelId = false;
                                    foreach ($allLabels as $l) {
                                        if ($l['name'] == $labelName) {
                                            $labelId = $l['id'];
                                            break;
                                        }
                                    }
                                    if ($labelColor !== false && $labelId === false) {
                                        $url = "https://api.trello.com/1/labels?name=$labelName&color=$labelColor&idBoard=$BOARD->id&key=$this->ApiKey&token=$this->ApiToken";
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                        $response = curl_exec($ch);
                                        curl_close($ch);
                                        $labelId = json_decode($response)->id;
                                    }
                                    if ($labelColor !== false && $labelId) {
                                        $url = "https://api.trello.com/1/cards/$CARD->id/idLabels?" . http_build_query($options);
                                        $postFields = array(
                                            'value' => $labelId
                                        );
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_POST, 1);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                        $response = curl_exec($ch);
                                        curl_close($ch);
                                    }

                                }

                                if (isset($this->dataTrello["card"]["part"]) && $this->dataTrello["card"]["part"]) {
                                    $part = $this->dataTrello["card"]["part"];
                                    switch ($part) {
                                        case "F":
                                        {
                                            $part = "Frontend";
                                            $labelColor = "green";
                                            break;
                                        }
                                        case "E":
                                        {
                                            $part = "Estimation";
                                            $labelColor = "purple";
                                            break;
                                        }
                                        case "T":
                                        {
                                            $part = "Testing";
                                            $labelColor = "yellow";
                                            break;
                                        }
                                        case "M":
                                        {
                                            $part = "Meeting";
                                            $labelColor = "red";
                                            break;
                                        }
                                        case "R":
                                        {
                                            $part = "Research";
                                            $labelColor = "black";
                                            break;
                                        }
                                        case "B":
                                        {
                                            $part = "Backend";
                                            $labelColor = "blue";
                                            break;

                                        }
                                        case "D":
                                        {
                                            $part = "Design";
                                            $labelColor = "yellow";
                                            break;

                                        }
                                    }
                                    $allLabels = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/labels" . "?" . http_build_query($options)), true
                                    );
                                    $labelId = false;
                                    foreach ($allLabels as $l) {
                                        if ($l['name'] == $part) {
                                            $labelId = $l['id'];
                                            break;
                                        }
                                    }
                                    if ($labelColor !== false && $labelId === false) {
                                        $url = "https://api.trello.com/1/labels?name=$part&color=$labelColor&idBoard=$BOARD->id&key=$this->ApiKey&token=$this->ApiToken";
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                        $response = curl_exec($ch);
                                        curl_close($ch);
                                        $labelId = json_decode($response)->id;
                                    }
                                    if ($labelColor !== false && $labelId) {
                                        $url = "https://api.trello.com/1/cards/$card->id/idLabels?" . http_build_query($options);
                                        $postFields = array(
                                            'value' => $labelId
                                        );
                                        $ch = curl_init();
                                        curl_setopt($ch, CURLOPT_URL, $url);
                                        curl_setopt($ch, CURLOPT_POST, 1);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                        $response = curl_exec($ch);
                                        curl_close($ch);
                                    }
                                }

                                if (isset($this->dataTrello["card"]["new_name"])) {
                                    Card::where("card_id", $CARD->id)->update([
                                        "name" => $this->dataTrello["card"]["new_name"]
                                    ]);
                                    $cardDB = Card::where("card_id", $CARD->id)->first();
                                    if ($cardDB) {
                                        Task::where("id", $cardDB->task_id)->update([
                                            "text" => $this->dataTrello["card"]["new_name"]
                                        ]);
                                    }
                                    $newName = $this->dataTrello["card"]["new_name"];
                                    $url = "https://api.trello.com/1/cards/$CARD->id?name=" . urlencode($newName) . "&key=$this->ApiKey&token=$this->ApiToken";

                                    $ch = curl_init();

                                    curl_setopt($ch, CURLOPT_URL, $url);
                                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    $r = curl_exec($ch);

                                    curl_close($ch);
                                }
                                if (isset($this->dataTrello["card"]["description"]) && (isset($this->dataTrello["card"]["description"]["links"]) || isset($this->dataTrello["card"]["description"]["notes"]))) {
                                    $html = "––––––––––––––––––––––––";
                                    $content = '';
                                    if (isset($this->dataTrello["card"]["description"]["links"]) && is_array($this->dataTrello["card"]["description"]["links"]) && count($this->dataTrello["card"]["description"]["links"])) {
                                        $content .= "\nLinks\n\n";
                                        foreach ($this->dataTrello["card"]["description"]["links"] as $item) {
                                            $content .= isset($item["title"]) ? $item["title"] . ": " . $item["url"] . "\n" : $item["url"] . "\n";
                                        }
                                    }
                                    if (isset($this->dataTrello["card"]["description"]["notes"]) && is_array($this->dataTrello["card"]["description"]["notes"]) && count($this->dataTrello["card"]["description"]["notes"])) {
                                        $content .= "\nNotes\n\n";
                                        foreach ($this->dataTrello["card"]["description"]["notes"] as $item) {
                                            $content .= $item["note"] . "\n";
                                        }
                                    }
                                    if ($content) {
                                        $html .= $content;
                                        $url = "https://api.trello.com/1/cards/{$CARD->id}?fields=desc&key={$this->ApiKey}&token={$this->ApiToken}";
                                        $result = file_get_contents($url);
                                        $response = json_decode($result, true);
                                        $description = $response['desc'];
                                        $newText = preg_replace('/––––––––––––––––––––––––(.*)/s', $html, $description);

                                        $urlDesc = "https://api.trello.com/1/cards/$CARD->id?desc=" . urlencode($newText) . "&key=$this->ApiKey&token=$this->ApiToken";
                                        $ch = curl_init($urlDesc);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_exec($ch);
                                        curl_close($ch);
                                    }
                                }
                                if (isset($this->dataTrello["card"]["due_date"]) && isset($this->dataTrello["card"]["start_date"])) {
                                    $start_date = $this->dataTrello["card"]["start_date"] ?: null;
                                    $due_date = $this->dataTrello["card"]["due_date"];
                                    if ($start_date)
                                        $url = "https://api.trello.com/1/cards/{$CARD->id}?due={$due_date}&start={$start_date}&key={$this->ApiKey}&token={$this->ApiToken}";
                                    else
                                        $url = "https://api.trello.com/1/cards/{$CARD->id}?due={$due_date}&key={$this->ApiKey}&token={$this->ApiToken}";
                                    $ch = curl_init();

                                    curl_setopt($ch, CURLOPT_URL, $url);
                                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                                    $response = curl_exec($ch);

                                    curl_close($ch);
                                }
                                if (isset($this->dataTrello["card"]["estimation"])) {
                                    $estimation = $this->dataTrello["card"]["estimation"] ?: null;
                                    if ($estimation) {
                                        $nameEst = lcfirst(explode(" ", $estimation)[0]);
                                        $valueEst = (int)explode(" ", $estimation)[1] / 60;
                                        $est = $nameEst == "estim" ? $valueEst : null;
                                        $estIP = $nameEst == "estimIP" ? $valueEst : null;
                                        $extra = $nameEst == "extra" ? $valueEst : null;
                                        $estQA = $nameEst == "estimQA" ? $valueEst : null;
                                    }
                                }
                                if (isset($est)) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $nameEst = "Estim";
                                    $valueEst = $est;
                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == $nameEst)
                                            $customFieldEst = $customField->id;
                                    }
                                    if (isset($customFieldEst)) {
                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEst/item";
                                        $data = array(
                                            'value' => array(
                                                'number' => $valueEst
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => $nameEst,
                                            "type" => "number"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == $nameEst)
                                                    $customFieldEst = $customField->id;
                                            }
                                            if (isset($customFieldEst)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEst/item";
                                                $data = array(
                                                    'value' => array(
                                                        'number' => $valueEst
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                            }
                                        }
                                    }
                                }
                                if (isset($estIP)) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $nameEst = "EstimIP";
                                    $valueEst = $estIP;
                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == $nameEst)
                                            $customFieldEst = $customField->id;
                                    }
                                    if (isset($customFieldEst)) {
                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEst/item";
                                        $data = array(
                                            'value' => array(
                                                'number' => $valueEst
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => $nameEst,
                                            "type" => "number"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == $nameEst)
                                                    $customFieldEst = $customField->id;
                                            }
                                            if (isset($customFieldEst)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEst/item";
                                                $data = array(
                                                    'value' => array(
                                                        'number' => $valueEst
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                            }
                                        }
                                    }
                                }
                                if (isset($extra)) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $nameEst = "Extra";
                                    $valueEst = $extra;
                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == $nameEst)
                                            $customFieldEst = $customField->id;
                                    }
                                    if (isset($customFieldEst)) {
                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEst/item";
                                        $data = array(
                                            'value' => array(
                                                'number' => $valueEst
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => $nameEst,
                                            "type" => "number"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == $nameEst)
                                                    $customFieldEst = $customField->id;
                                            }
                                            if (isset($customFieldEst)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEst/item";
                                                $data = array(
                                                    'value' => array(
                                                        'number' => $valueEst
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                            }
                                        }
                                    }
                                }
                                if (isset($estQA)) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                    );
                                    $nameEst = "EstimQA";
                                    $valueEst = $estQA;
                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == $nameEst)
                                            $customFieldEst = $customField->id;
                                    }
                                    if (isset($customFieldEst)) {
                                        $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEst/item";
                                        $data = array(
                                            'value' => array(
                                                'number' => $valueEst
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        $r = curl_exec($ch);
                                        curl_close($ch);
                                    } else {
                                        $curl = curl_init();

                                        $obj = [
                                            "idModel" => $BOARD->id,
                                            "modelType" => "board",
                                            "name" => $nameEst,
                                            "type" => "number"
                                        ];

                                        curl_setopt_array($curl, array(
                                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                            CURLOPT_RETURNTRANSFER => true,
                                            CURLOPT_ENCODING => '',
                                            CURLOPT_MAXREDIRS => 10,
                                            CURLOPT_TIMEOUT => 0,
                                            CURLOPT_FOLLOWLOCATION => true,
                                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                            CURLOPT_CUSTOMREQUEST => 'POST',
                                            CURLOPT_POSTFIELDS => json_encode($obj),
                                            CURLOPT_HTTPHEADER => array(
                                                'Content-Type: application/json'
                                            ),
                                        ));

                                        $response = curl_exec($curl);
                                        curl_close($curl);

                                        if ($response) {
                                            $allCustomFields = json_decode(
                                                file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                            );

                                            foreach ($allCustomFields as $customField) {
                                                if ($customField->name == $nameEst)
                                                    $customFieldEst = $customField->id;
                                            }
                                            if (isset($customFieldEst)) {
                                                $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldEst/item";
                                                $data = array(
                                                    'value' => array(
                                                        'number' => $valueEst
                                                    ),
                                                    'key' => $this->ApiKey,
                                                    'token' => $this->ApiToken
                                                );

                                                $ch = curl_init($urlField);
                                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                                curl_exec($ch);
                                                curl_close($ch);
                                            }
                                        }
                                    }
                                }
                                if (isset($this->dataTrello["card"]["members"]) && is_array($this->dataTrello["card"]["members"]) && count($this->dataTrello["card"]["members"])) {
                                    $members = json_decode(
                                        file_get_contents("https://api.trello.com/1/cards/{$CARD->id}/members" . "?" . http_build_query($options)),
                                    );
                                    foreach ($members as $member) {
                                        $url = "https://api.trello.com/1/cards/{$CARD->id}/idMembers/{$member->id}?" . http_build_query($options);
                                        $ch = curl_init($url);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        $response = curl_exec($ch);
                                        curl_close($ch);
                                    }
                                    foreach ($this->dataTrello["card"]["members"] as $member) {
                                        switch ($member["name"]) {
                                            case "vn":
                                            {
                                                $id_trello = "6426d8fe1187511d5b7b889e";
                                                break;
                                            }
                                            case "id":
                                            {
                                                $id_trello = "630e0412ffc3b900d905f65a";
                                                break;
                                            }
                                            case "rp":
                                            {
                                                $id_trello = "64ad2ba0aff100a95ea967ac";
                                                break;
                                            }
                                            case "np":
                                            {
                                                $id_trello = "5a0dcfb2239a4e028a186b4b";
                                                break;
                                            }
                                            case "pm":
                                            {
                                                $id_trello = "63533314084f3800186552ca";
                                                break;
                                            }
                                            case "rl":
                                            {
                                                $id_trello = "64abb23020b36875a4b2864c";
                                                break;
                                            }
                                            case "vd":
                                            {
                                                $id_trello = "5d6fc27133aed9512fa8eef8";
                                                break;
                                            }
                                            case "vp":
                                            {
                                                $id_trello = "5f5ce621b9ba0e5857bf47d0";
                                                break;
                                            }
                                            case "av":
                                            {
                                                $id_trello = "6356379622408601989151ed";
                                                break;
                                            }
                                            case "vs":
                                            {
                                                $id_trello = "6274da3db0e87f22e70771a9";
                                                break;
                                            }
                                            case "ip":
                                            {
                                                $id_trello = "6350edb15c48ed00526f410f";
                                                break;
                                            }
                                            case "vk":
                                            {
                                                $id_trello = "5d0bc87f619ba61448bbaf28";
                                                break;
                                            }
                                            case "jl":
                                            {
                                                $id_trello = "56a0f4739331c3eb5474b2d2";
                                                break;
                                            }
                                            case "ks":
                                            {
                                                $id_trello = "64b63ecf34af50068b5ef921";
                                                break;
                                            }
//                                            case "sk":
//                                            {
//                                                $id_trello = "5f43e38515fbf783c82dfb19";
//                                                break;
//                                            }
                                        }
                                        if (isset($id_trello)) {
                                            $url = "https://api.trello.com/1/cards/$CARD->id/idMembers";
                                            $data = array(
                                                'value' => $id_trello,
                                                'key' => $this->ApiKey,
                                                'token' => $this->ApiToken
                                            );

                                            $ch = curl_init($url);

                                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                                            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));

                                            curl_exec($ch);
                                            curl_close($ch);
                                        }
                                    }
                                }
                                $res = $this->setDbCard($CARD, $BOARD, $this->dataTrello["html_link"], $part ?? null, $labelName ?? null);
                                if ($res && isset($newText) && $newText) {
                                    $res->update([
                                        "desc" => $newText
                                    ]);
                                }
                                return response()->json(['message' => 'Card has been update'], 200);
                            } else
                                return response()->json(['message' => 'Card not found'], 400);
                        }
                        return response()->json(['message' => 'Board Not Found'], 400);
                    }
                    return response()->json(['message' => 'Board Name missing'], 400);
                }
                case "check_user":
                {
                    $email = $this->dataTrello["email"];
                    foreach (trello_users::all() as $user) {
                        $key = $user->key;
                        $token = $user->token;
                        if ($key && $token) {
                            $url = "https://api.trello.com/1/members/me?key=$key&token=$token";
                            $response = file_get_contents($url);
                            $response = json_decode($response);
                            if (isset($response->email) && $response->email == $email) {
                                return response()->json(['user' => $response], 200);
                            }
                        }
                    }
                    $user = trello_users::where("trello_id", "6478a0d5097e78452f6bafc6")->first();
                    $url = "https://api.trello.com/1/members/me?key=$user->key&token=$user->token";
                    $response = file_get_contents($url);
                    $response = json_decode($response);
                    return response()->json(['user' => $response], 200);
                }
                case "checkCard":
                {
                    if (isset($this->dataTrello["board_name"]) && isset($this->dataTrello["card_name"])) {
                        $params = [
                            'text' => "<b>ATTENTION</b>
<b>ON BOARD</b> {$this->dataTrello["board_name"]}
<b>ON CARD</b> {$this->dataTrello["card_name"]}

<b>NO ID FOUND</b>

PMD Link: <a href='{$this->dataTrello["html_link"]}'>{$this->dataTrello["html_link"]}</a>",
                            'chat_id' => "5548342573",
                            'parse_mode' => 'HTML'
                        ];
                        $url = $this->base_url . $method . "?" . http_build_query($params);
                        $r = file_get_contents($url);
                        return $r;
                    }
                }
                case "action_create_card":
                {
                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                    $boardId = $this->dataTrello["model"]["id"];
                    $board = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    if ($board["name"] == "LEADS-PROJECTS") {
                        $data = $this->dataTrello;
//                        $proxy = "161.123.93.35:5765";
//                        $proxyAuth = "jicuoneg:6hkd2vk078ix";

                        $urlWorkflow = env("WORKFLOW_URL") . 'cards/from/trello';

                        $curl = curl_init($urlWorkflow);
                        curl_setopt($curl, CURLOPT_POST, true);
//                        curl_setopt($curl, CURLOPT_PROXY, $proxy);
//                        curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
//                        curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        $r = curl_exec($curl);
                        curl_close($curl);
                        $r = json_decode($r);
                        if (isset($r->card->card_id)) {
                            $id = $r->card->card_id;
                            $allCustomFields = json_decode(
                                file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                            );
                            foreach ($allCustomFields as $customField) {
                                if ($customField->name == "ID")
                                    $customFieldId = $customField->id;
                            }
                            if (isset($customFieldId)) {
                                $urlField = "https://api.trello.com/1/card/$cardId/customField/$customFieldId/item";
                                $data = array(
                                    'value' => array(
                                        'text' => $id
                                    ),
                                    'key' => $this->ApiKey,
                                    'token' => $this->ApiToken
                                );

                                $ch = curl_init($urlField);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                $r = curl_exec($ch);
                                curl_close($ch);
                            } else {
                                $curl = curl_init();

                                $obj = [
                                    "idModel" => $boardId,
                                    "modelType" => "board",
                                    "name" => "ID",
                                    "type" => "text"
                                ];

                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',
                                    CURLOPT_POSTFIELDS => json_encode($obj),
                                    CURLOPT_HTTPHEADER => array(
                                        'Content-Type: application/json'
                                    ),
                                ));

                                $response = curl_exec($curl);
                                curl_close($curl);

                                if ($response) {
                                    $allCustomFields = json_decode(
                                        file_get_contents("https://api.trello.com/1/boards/$boardId/customFields" . "?" . http_build_query($options))
                                    );

                                    foreach ($allCustomFields as $customField) {
                                        if ($customField->name == "ID")
                                            $customFieldId = $customField->id;
                                    }
                                    if (isset($customFieldId)) {
                                        $urlField = "https://api.trello.com/1/card/$cardId/customField/$customFieldId/item";
                                        $data = array(
                                            'value' => array(
                                                'text' => $id
                                            ),
                                            'key' => $this->ApiKey,
                                            'token' => $this->ApiToken
                                        );

                                        $ch = curl_init($urlField);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                        curl_exec($ch);
                                        curl_close($ch);
                                    }
                                }
                            }
                        }
                    }
                    break;
                }
                case "action_changed_description_of_card":
                {
                    $board = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                    $members = json_decode(
                        file_get_contents("https://api.trello.com/1/cards/{$cardId}/members" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $check = false;
                    if (isset($members)) {
                        foreach ($members as $member) {
                            $tmp = trello_users::where(["trello_id" => $member["id"]]);
                            if ($tmp) {
                                $check = true;
                                break;
                            }
                        }
                        if ($check) {
                            $card = $this->dataTrello["action"]["display"]["entities"]["card"]["text"];
                            $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
                            $descNew = $this->dataTrello["action"]["display"]["entities"]["card"]["desc"];
                            $descOld = $this->dataTrello["action"]["data"]["old"]["desc"];
                            if (!isset($descOld) || $descOld == "") {
                                $params = [
                                    'text' => "<b>ON BOARD</b> {$board["name"]}
<b>BY</b> {$creator}
<b>ON CARD</b>  {$card}
<b>ADDED DESCRIPTION</b>  <em>{$descNew}</em>

Card Link: <a href='{$cardLink}'>{$cardLink}</a>",
                                    'chat_id' => $this->chat_id,
                                    'parse_mode' => 'HTML'
                                ];
                            } else {
                                $params = [
                                    'text' => "<b>ON BOARD</b> {$board["name"]}
<b>BY</b> {$creator}
<b>ON CARD</b> {$card}
<b>CHANGED DESCRIPTION</b>

<b>BEFORE</b> <em>{$descOld}</em>

<b>NOW</b>  <em>{$descNew}</em>

Card Link: <a href='{$cardLink}'>{$cardLink}</a>",
                                    'chat_id' => $this->chat_id,
                                    'parse_mode' => 'HTML'
                                ];
                            }
                            $url = $this->base_url . $method . "?" . http_build_query($params);
                        }
                    }
                    break;
                }
//                case
//                    "action_completed_checkitem" || "action_marked_checkitem_incomplete":
//                {
//                    $board = json_decode(
//                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
//                        JSON_OBJECT_AS_ARRAY
//                    );
//                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
//                    $boardLink = "https://trello.com/b/" . $this->dataTrello["action"]["data"]["board"]["shortLink"];
//                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
//                    $members = json_decode(
//                        file_get_contents("https://api.trello.com/1/cards/{$cardId}/members" . "?" . http_build_query($options)),
//                        JSON_OBJECT_AS_ARRAY
//                    );
//                    foreach ($members as $member) {
//                        if (trello_users::where(["trello_id" => $member["id"]])->exists()) {
//                            $card = $this->dataTrello["action"]["display"]["entities"]["card"]["text"];
//                            $task = $this->dataTrello["action"]["data"]["checkItem"]["name"];
//                            $checklist = $this->dataTrello["action"]["data"]["checklist"]["name"];
//                            $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
//                            $creatorId = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["id"];
//                            $user = trello_users::where(["trello_id" => $member["id"]])->get();
//                            if ($member["id"] == $creatorId)
//                                continue;
//                            $chat = tg_users::where(["name" => $user[0]->tg_username])->get();
//                            if ($action === "action_completed_checkitem")
//                                $params = [
//                                    'text' => "<b>ON BOARD</b> {$board["name"]}
//<b>BY</b> {$creator}
//<b>ON CARD</b> {$card}
//<b>IN CHECKLIST</b> {$checklist}
//
//<b>COMPLETE TASK</b> <em>{$task}</em>
//
//Card Link: <a href='{$cardLink}'>{$cardLink}</a>
//Card Id: {$this->dataTrello["action"]["display"]["entities"]["card"]["id"]}
//Board Link: <a href='{$boardLink}'>{$boardLink}</a>",
//                                    'chat_id' => $chat[0]->chat_id,
//                                    'parse_mode' => 'HTML'
//                                ];
//                            if ($action === "action_marked_checkitem_incomplete")
//                                $params = [
//                                    'text' => "<b>ON BOARD</b> {$board["name"]}
//<b>BY</b> {$creator}
//<b>ON CARD</b> {$card}
//<b>IN CHECKLIST</b> {$checklist}
//
//<b>REMOVE MARKER COMPLETE FROM TASK</b> <em>{$task}</em>
//
//Card Link: <a href='{$cardLink}'>{$cardLink}</a>
//Card Id: {$this->dataTrello["action"]["display"]["entities"]["card"]["id"]}
//Board Link: <a href='{$boardLink}'>{$boardLink}</a>",
//                                    'chat_id' => $chat[0]->chat_id,
//                                    'parse_mode' => 'HTML'
//                                ];
//                            json_decode(
//                                file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
//                                JSON_OBJECT_AS_ARRAY
//                            );
//                        }
//                    }
//                    break;
//                }
                default:
                    break;
            }
//            if (isset($url))
//                return json_decode(
//                    file_get_contents($url),
//                    JSON_OBJECT_AS_ARRAY
//                );
        }
    }

    function setColorLabel($name)
    {
        return match ($name) {
            "1" => "red",
            "2" => "blue",
            "3" => "green",
            "4" => "yellow",
            "5" => "purple",
            default => false,
        };
    }

    function checkCards(Request $request)
    {
        $url = "https://api.trello.com/1/organizations/61a62d1ab530a327f2e18ce5/boards?key={$this->ApiKey}&token={$this->ApiToken}";
        $response = file_get_contents($url);
        $boards = json_decode($response);
        $arr = [];
        foreach ($boards as $board) {
            if ($request->board == $board->name) {
                $columns = json_decode(
                    file_get_contents("https://api.trello.com/1/boards/$board->id/lists?key={$this->ApiKey}&token={$this->ApiToken}")
                );
                foreach ($columns as $column) {
                    $arr[$column->name] = [];
                    $url = "https://api.trello.com/1/lists/{$column->id}/cards?key={$this->ApiKey}&token={$this->ApiToken}";
                    $response = file_get_contents($url);
                    $cards = json_decode($response, true);
                    foreach ($cards as $card) {
                        array_push($arr[$column->name], $card);
                    }
                }
            }
        }
        return $arr;
    }

    function syncTrello(Request $request)
    {
        $board = Board::where("name", $request->input("board"))->first();
        $cards = Card::where("board_id", $board->id)->get();
        foreach ($cards as $card)
            $this->setDbCard($card, $board);

    }

    function setDbCard($card, $board, $pmdLink, $part = null, $priority = null, $flag = null)
    {
        $board = Board::where("board_id", $flag ? $board->board_id : $board->id)->first();
        if ($board) {
            $cardId = $flag ? $card->card_id : $card->id;
            $customFields = json_decode(
                file_get_contents("https://api.trello.com/1/cards/{$cardId}/customFieldItems?key={$this->ApiKey}&token={$this->ApiToken}"),
                JSON_OBJECT_AS_ARRAY
            );
            foreach ($customFields as $customField) {
                $items = json_decode(
                    file_get_contents("https://api.trello.com/1/customFields/{$customField["idCustomField"]}?key={$this->ApiKey}&token={$this->ApiToken}"),
                    JSON_OBJECT_AS_ARRAY
                );
                if ($items["name"] == "ID") {
                    $ID = $customField['value']['text'];
                }
                if ($items["name"] == "Estim") {
                    $nameField = lcfirst($items["name"]);
                    $estimation = $customField['value']['number'];
                }
                if ($items["name"] == "EstimIP") {
                    $nameField = lcfirst($items["name"]);
                    $estimation = $customField['value']['number'];
                }
                if ($items["name"] == "Extra") {
                    $nameField = lcfirst($items["name"]);
                    $estimation = $customField['value']['number'];
                }
                if ($items["name"] == "EstimQA") {
                    $nameField = lcfirst($items["name"]);
                    $estimation = $customField['value']['number'];
                }
            }
//            if (isset($estimation)) {
            $members = json_decode(
                file_get_contents("https://api.trello.com/1/cards/{$cardId}/members?key={$this->ApiKey}&token={$this->ApiToken}"),
                JSON_OBJECT_AS_ARRAY
            );
            $member = $members[0]["fullName"] ?? null;
            if ($member) {
                if ($board->name != "LEADS-PROJECTS") {
                    $pm = $board->pm;
                } else {
                    switch ($members[0]["id"]) {
                        case "630e0412ffc3b900d905f65a":
                        {
                            $pm = "Igor Dzhenkov";
                            break;
                        }
                        case "5a0dcfb2239a4e028a186b4b":
                        case "5d6fc27133aed9512fa8eef8":
                        {
                            $pm = "Nazar Platonov";
                            break;
                        }
                        default:
                            break;
                    }
                }
                if (!$flag) {
                    $shortUrl = $card->shortUrl;
                    if ($card->start) {
                        $start = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $card->start);
                        $cardStart = $start->format('Y-m-d');
                    }
                    if ($card->due) {
                        $end = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $card->due);
                        $cardEnd = $end->format('Y-m-d');
                    }
                } else {
                    $cardStart = $card->start_date ?? null;
                    $cardEnd = $card->end_date ?? null;
                    $shortUrl = $card->link;
                }
                if (isset($nameField)) {
                    $check = Card::updateOrCreate(["card_id" => $cardId], [
                        "custom_id" => $ID ?? null,
                        "name" => $card->name,
                        lcfirst($nameField) => $estimation ? (double)$estimation : null,
                        "board_id" => $board->id,
                        "member" => $member,
                        "part" => $part,
                        "pm" => $pm ?? null,
                        "start_date" => $cardStart ?? null,
                        "end_date" => $cardEnd ?? null,
                        "link" => $shortUrl ?? null,
                        "pmd_link" => $pmdLink,
                    ]);
                } else {
                    $check = Card::updateOrCreate(["card_id" => $cardId], [
                        "custom_id" => $ID ?? null,
                        "name" => $card->name,
                        "estimation" => null,
                        "board_id" => $board->id,
                        "member" => $member,
                        "pm" => $pm ?? null,
                        "part" => $part,
                        "start_date" => $cardStart ?? null,
                        "end_date" => $cardEnd ?? null,
                        "link" => $shortUrl ?? null,
                        "pmd_link" => $pmdLink,
                    ]);
                }
                if ($priority) {
                    Card::where("card_id", $cardId)->update([
                        'priority' => $priority
                    ]);
                }
            }
        }
        return $check ?? null;
    }


    public function splitNameCard($card)
    {
        if (str_contains($card->name, "  |  ")) {
            $parts = explode("  |  ", $card->name);
        } else
            $parts = explode("/", $card->name);
        if (isset($parts[3]) && !empty($parts[3])) {
            $card->task = trim(ltrim(ltrim(str_replace([$parts[0], $parts[1], $parts[2]], "", $card->name), "/"), "| "));
            $card->release = $parts[2];
            $card->milestone = $parts[1];
            $card->project = $parts[0];
        } elseif (isset($parts[2]) && !empty($parts[2])) {
            $card->task = $parts[2];
            $card->release = "";
            $card->milestone = $parts[1];
            $card->project = $parts[0];
        } elseif (isset($parts[1]) && !empty($parts[1])) {
            $card->task = $parts[0];
            $card->project = $parts[1];
            $card->release = "";
            $card->milestone = "";
        } elseif (isset($parts[0]) && !empty($parts[0])) {
            $card->task = $parts[0];
            $card->project = "";
            $card->release = "";
            $card->milestone = "";
        }
        $project = Project::updateOrCreate([
            "board_id" => $card->board_id,
            "text" => $card->project
        ]);
        $milestone = Milestone::updateOrCreate([
            "project_id" => $project->id,
            "text" => $card->milestone
        ]);
        $release = Release::updateOrCreate([
            "milestone_id" => $milestone->id,
            "text" => $card->release
        ]);
        $task = Task::updateOrCreate([
            "release_id" => $release->id,
            "text" => $card->task
        ]);
        Card::where("id", $card->id)->update([
            "project_id" => $project->id,
            "milestone_id" => $milestone->id,
            "task_id" => $task->id,
            "release_id" => $release->id,
        ]);
    }

//    public function setBoardCategory(Request $request)
//    {
//        if ($request->input("board")) {
//            $board = Board::where("name", $request->input("board"))->first();
//            if ($board) {
//                $cards = Card::where("board_id", $board->id)->get();
//                foreach ($cards as $card) {
//                    $this->splitNameCard($card);
//                }
//            }
//        }
//    }

    public function setCardColumn(Request $request)
    {
        if ($request->input("board")) {
            $board = Board::where("name", $request->input("board"))->first();
            if ($board) {
                $cards = Card::where("board_id", $board->id)->get();
                foreach ($cards as $card) {
                    if (!$card->column)
                        $this->setColumn($card);
                }
            }
        }
    }


    public function setColumn($card)
    {
        $url = "https://api.trello.com/1/cards/{$card->card_id}?fields=idList&key={$this->ApiKey}&token={$this->ApiToken}";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        $columnId = $data['idList'];
        if ($columnId) {
            $columnUrl = "https://api.trello.com/1/lists/{$columnId}?fields=name&key={$this->ApiKey}&token={$this->ApiToken}";
            $columnResponse = file_get_contents($columnUrl);
            $columnData = json_decode($columnResponse, true);
            $columnName = $columnData['name'];
            if ($columnName) {
                $card->column = $columnName;
                if ($columnName == "Ready for QA" || $columnName == "Done" || $columnName == "Ready for Deploy") {
                    $card->ready_date = Carbon::now()->format('Y-m-d');
                    $BOARD = Board::where("id", $card->board_id)->first();
                    $fact = $card->fact ?: CardDate::where("card_id", $card->id)->sum('hours');
                    if (!$fact) {
                        if ($card->fact) {
                            $fact = $card->fact;
                        } else {
                            $fact = 0.01;
                            CardDate::create([
                                "date" => now()->format('Y-m-d'),
                                'hours' => $fact,
                                "card_id" => $card->id
                            ]);
                        }
                    }
                    if ($BOARD) {
                        $card->fact = $fact;
                        $options = [
                            "key" => $this->ApiKey,
                            "token" => $this->ApiToken
                        ];
                        $allCustomFields = json_decode(
                            file_get_contents("https://api.trello.com/1/boards/$BOARD->board_id/customFields" . "?" . http_build_query($options))
                        );
                        foreach ($allCustomFields as $customField) {
                            if ($customField->name == "Fact")
                                $customFieldEst = $customField->id;
                        }

                        if (isset($customFieldEst)) {
                            $urlField = "https://api.trello.com/1/card/$card->card_id/customField/$customFieldEst/item";
                            $data = array(
                                'value' => array(
                                    'number' => $fact
                                ),
                                'key' => $this->ApiKey,
                                'token' => $this->ApiToken
                            );

                            $ch = curl_init($urlField);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                            $r = curl_exec($ch);
                            curl_close($ch);
                        } else {
                            $curl = curl_init();

                            $obj = [
                                "idModel" => $BOARD->board_id,
                                "modelType" => "board",
                                "name" => "Fact",
                                "type" => "number"
                            ];

                            curl_setopt_array($curl, array(
                                CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => '',
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => 'POST',
                                CURLOPT_POSTFIELDS => json_encode($obj),
                                CURLOPT_HTTPHEADER => array(
                                    'Content-Type: application/json'
                                ),
                            ));

                            $response = curl_exec($curl);
                            curl_close($curl);

                            if ($response) {
                                $allCustomFields = json_decode(
                                    file_get_contents("https://api.trello.com/1/boards/$BOARD->board_id/customFields" . "?" . http_build_query($options))
                                );

                                foreach ($allCustomFields as $customField) {
                                    if ($customField->name == "Fact")
                                        $customFieldEst = $customField->id;
                                }
                                if (isset($customFieldEst)) {
                                    $urlField = "https://api.trello.com/1/card/$card->card_id/customField/$customFieldEst/item";
                                    $data = array(
                                        'value' => array(
                                            'number' => $fact
                                        ),
                                        'key' => $this->ApiKey,
                                        'token' => $this->ApiToken
                                    );

                                    $ch = curl_init($urlField);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                    $res = curl_exec($ch);
                                    curl_close($ch);
                                }
                            }
                        }
                    }
                }
                $card->save();
            }
        }
    }

    function setLink()
    {
        $cards = Card::all();
        foreach ($cards as $card) {
            if (!$card->link) {
                $url = "https://api.trello.com/1/cards/{$card->card_id}/shortUrl?key={$this->ApiKey}&token={$this->ApiToken}";

                $shortUrlJson = @file_get_contents($url);
                if ($shortUrlJson === false) {
                    $card->delete();
                } else {
                    $shortUrlData = json_decode($shortUrlJson, true);
                    if (isset($shortUrlData['_value'])) {
                        $card->update([
                            "link" => $shortUrlData['_value']
                        ]);
                    }
                }
            }
        }
    }

    function setStatisticForCharts()
    {
        $tasks = $this->taskModel->getTaskInfo();
        $projects = [];
        $projects['estPlanSum'] = 0;
        $projects['estFactSum'] = 0;
        $projects['estReady'] = 0;
        $projects['estResult'] = 0;
        foreach ($tasks as $task) {
            if (!isset($projects[$task->board_id])) {
                $projects[$task->board_id] = [];
                $projects[$task->board_id]['estPlanSum'] = 0;
                $projects[$task->board_id]['estFactSum'] = 0;
                $projects[$task->board_id]['estReady'] = 0;
                $projects[$task->board_id]['estResult'] = 0;
            }
            $projects[$task->board_id]['estPlanSum'] += $task->plan_est;
            if (!$task->qa)
                $projects[$task->board_id]['estFactSum'] += $task->fact_est;
            $projects[$task->board_id]['data'][] = $task;
            if ($task->card_column == "Done" || $task->card_column == "Ready for QA" || $task->card_column == "Ready for Deploy") {
                $projects[$task->board_id]['estReady'] += $task->plan_est;
                $projects[$task->board_id]['estResult'] = $projects[$task->board_id]['estPlanSum'] ? round($projects[$task->board_id]['estReady'] / $projects[$task->board_id]['estPlanSum'] * 100, 2) : 0;
            }
        }
        foreach ($projects as $key => $project) {
            if (isset($project["data"])) {
                $projects['estPlanSum'] += $project['estPlanSum'];
                $projects['estFactSum'] += $project['estFactSum'];
                $projects['estReady'] += $project['estReady'];
                $projects['estResult'] = $projects['estPlanSum'] ? round($projects['estReady'] / $projects['estPlanSum'] * 100, 2) : 0;

                foreach ($project["data"] as $item) {
                    if (!isset($project['projects'][$item->project_id])) {
                        $project['projects'][$item->project_id] = [];
                        $project['projects'][$item->project_id]['estPlanSum'] = 0;
                        $project['projects'][$item->project_id]['estFactSum'] = 0;
                        $project['projects'][$item->project_id]['estReady'] = 0;
                        $project['projects'][$item->project_id]['estResult'] = 0;
                        $project['projects'][$item->project_id]['project_id'] = $item->project_id;
                    }
                    $project['projects'][$item->project_id]['estPlanSum'] += $item->plan_est;
                    $project['projects'][$item->project_id]['estFactSum'] += $item->fact_est;
                    $project['projects'][$item->project_id]['data'][] = $item;
                    if ($item->card_column == "Done" || $item->card_column == "Ready for QA" || $item->card_column == "Ready for Deploy") {
                        $project['projects'][$item->project_id]['estReady'] += $item->plan_est;
                        $project['projects'][$item->project_id]['estResult'] = $project['projects'][$item->project_id]['estPlanSum'] ? round($project['projects'][$item->project_id]['estReady'] / $project['projects'][$item->project_id]['estPlanSum'] * 100, 2) : 0;
                    }
                    $projects[$key] = $project;
                }
            }
        }
        foreach ($projects as $key => $project) {
            if (isset($project["projects"])) {
                foreach ($project["projects"] as $key1 => $pr) {
                    foreach ($pr['data'] as $item) {
                        if (!isset($pr['milestones'][$item->milestone_id])) {
                            $pr['milestones'][$item->milestone_id] = [];
                            $pr['milestones'][$item->milestone_id]['estPlanSum'] = 0;
                            $pr['milestones'][$item->milestone_id]['estReady'] = 0;
                            $pr['milestones'][$item->milestone_id]['estFactSum'] = 0;
                            $pr['milestones'][$item->milestone_id]['estResult'] = 0;
                            $pr['milestones'][$item->milestone_id]['milestone_id'] = $item->milestone_id;
                        }
                        $pr['milestones'][$item->milestone_id]['estPlanSum'] += $item->plan_est;
                        $pr['milestones'][$item->milestone_id]['estFactSum'] += $item->fact_est;
                        $pr['milestones'][$item->milestone_id]['data'][] = $item;
                        if ($item->card_column == "Done" || $item->card_column == "Ready for QA" || $item->card_column == "Ready for Deploy") {
                            $pr['milestones'][$item->milestone_id]['estReady'] += $item->plan_est;
                            $pr['milestones'][$item->milestone_id]['estResult'] = $pr['milestones'][$item->milestone_id]['estPlanSum'] ? round($pr['milestones'][$item->milestone_id]['estReady'] / $pr['milestones'][$item->milestone_id]['estPlanSum'] * 100, 2) : 0;
                        }
                        $project["projects"][$key1] = $pr;
                    }
                }
                $projects[$key] = $project;
            }
        }

        foreach ($projects as $key => $project) {
            if (isset($project["projects"])) {
                foreach ($project["projects"] as $key2 => $pr) {
                    if (isset($pr["milestones"])) {
                        foreach ($pr["milestones"] as $key1 => $milestone) {
                            foreach ($milestone["data"] as $item) {
                                $milestone['client_id'] = $item->project_id;
                                if (!isset($milestone['releases'][$item->release_id])) {
                                    $milestone['releases'][$item->release_id] = [];
                                    $milestone['releases'][$item->release_id]['estPlanSum'] = 0;
                                    $milestone['releases'][$item->release_id]['estFactSum'] = 0;
                                    $milestone['releases'][$item->release_id]['estReady'] = 0;
                                    $milestone['releases'][$item->release_id]['estResult'] = 0;
                                    $milestone['releases'][$item->release_id]['release_id'] = $item->release_id;
                                }
                                $milestone['releases'][$item->release_id]['estPlanSum'] += $item->plan_est;
                                $milestone['releases'][$item->release_id]['estFactSum'] += $item->fact_est;
                                $milestone['releases'][$item->release_id]['data'][] = $item;
                                if ($item->card_column == "Done" || $item->card_column == "Ready for QA" || $item->card_column == "Ready for Deploy") {
                                    $milestone['releases'][$item->release_id]['estReady'] += $item->plan_est;
                                    $milestone['releases'][$item->release_id]['estResult'] = $milestone['releases'][$item->release_id]['estPlanSum'] ? round($milestone['releases'][$item->release_id]['estReady'] / $milestone['releases'][$item->release_id]['estPlanSum'] * 100, 2) : 0;
                                }
                            }
                            $pr["milestones"][$key1] = $milestone;
                        }
                        $project["projects"][$key2] = $pr;
                    }
                }
                $projects[$key] = $project;
            }
        }
        AllClientsStatistic::updateOrCreate([
            "date" => Carbon::now()->format('Y-m-d')
        ], [
            "res" => $projects["estResult"],
            "est_plan" => $projects["estPlanSum"],
            "est_fact" => $projects["estFactSum"],
            "est_ready" => $projects["estReady"]
        ]);
        foreach ($projects as $key => $project) {
            if (isset($project["projects"])) {
                ClientStatistic::updateOrCreate([
                    "client_id" => $key,
                    "date" => Carbon::now()->format('Y-m-d')
                ], [
                    "res" => $project["estResult"],
                    "est_plan" => $project["estPlanSum"],
                    "est_fact" => $project["estFactSum"],
                    "est_ready" => $project["estReady"]
                ]);
                foreach ($project["projects"] as $key2 => $pr) {
                    if (isset($pr["milestones"])) {
                        ProjectStatistic::updateOrCreate([
                            "project_id" => $key2,
                            "date" => Carbon::now()->format('Y-m-d')
                        ], [
                            "res" => $pr["estResult"],
                            "est_plan" => $pr["estPlanSum"],
                            "est_fact" => $pr["estFactSum"],
                            "est_ready" => $pr["estReady"]
                        ]);
                        foreach ($pr["milestones"] as $key1 => $milestone) {
                            if (isset($milestone["releases"])) {
                                MilestoneStatistic::updateOrCreate([
                                    "milestone_id" => $key1,
                                    "date" => Carbon::now()->format('Y-m-d')
                                ], [
                                    "res" => $milestone["estResult"],
                                    "est_plan" => $milestone["estPlanSum"],
                                    "est_fact" => $milestone["estFactSum"],
                                    "est_ready" => $milestone["estReady"]
                                ]);
                                foreach ($milestone["releases"] as $key3 => $release) {
                                    if (isset($release["data"])) {
                                        ReleaseStatistic::updateOrCreate([
                                            "release_id" => $key3,
                                            "date" => Carbon::now()->format('Y-m-d')
                                        ], [
                                            "res" => $release["estResult"],
                                            "est_plan" => $release["estPlanSum"],
                                            "est_fact" => $release["estFactSum"],
                                            "est_ready" => $release["estReady"]
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function notification()
    {
        foreach ($this->devs as $dev) {
            $cards = Card::where("member", $dev)->where("column", "In progress")->get();
            if (!count($cards)) {
                $user = trello_users::where("name", $dev)->first();
                if ($user) {
                    $tgUser = tg_users::where("name", $user->tg_username)->first();
                    if ($tgUser) {
                        $params = [
                            'text' => "<b>ATTENTION!</b>
You have no any tasks in progress
for last 30 minutes
Please go to dashboard to take task for
developing",
                            'chat_id' => $tgUser->chat_id,
                            'parse_mode' => 'HTML'
                        ];
                        $url = $this->base_url . "sendMessage" . "?" . http_build_query($params);
                        file_get_contents($url);
                    }
                }
            }
        }
    }

    public function setEstReady()
    {
        foreach (AllClientsStatistic::all() as $item) {
            if (!$item->est_ready) {
                $item->est_ready = round($item->est_plan * $item->res / 100, 2);
                $item->save();
            }
        }
        foreach (ClientStatistic::all() as $item) {
            if (!$item->est_ready) {
                $item->est_ready = round($item->est_plan * $item->res / 100, 2);
                $item->save();
            }
        }
        foreach (ProjectStatistic::all() as $item) {
            if (!$item->est_ready) {
                $item->est_ready = round($item->est_plan * $item->res / 100, 2);
                $item->save();
            }
        }
        foreach (MilestoneStatistic::all() as $item) {
            if (!$item->est_ready) {
                $item->est_ready = round($item->est_plan * $item->res / 100, 2);
                $item->save();
            }
        }
        foreach (ReleaseStatistic::all() as $item) {
            if (!$item->est_ready) {
                $item->est_ready = round($item->est_plan * $item->res / 100, 2);
                $item->save();
            }
        }
    }

    public function changeDueDates()
    {
        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');
        $startWeek = now()->subDays(7)->format('Y-m-d');
        $cards = Card::where("end_date", "<=", $today)->where("end_date", ">=", $startWeek)->whereNotIn("column", ["Ready for QA", "Done", "Ready for Deploy"])->where("pm", 'Igor Dzhenkov')->get();
        foreach ($cards as $card) {
            $url = "https://api.trello.com/1/cards/{$card->card_id}?due={$tomorrow}&key={$this->ApiKey}&token={$this->ApiToken}";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 429) {
                $release = Release::where("id", $card->release_id)->first();
                if ($release && str_starts_with($release->text, "Week - ")) {
                    $newRelease = Release::firstOrCreate(["text" => "Week - " . now()->addDay()->weekOfYear, "milestone_id" => $release->milestone_id]);
                    $card->release_id = $newRelease->id;
                    $BOARD = Board::where("id", $card->board_id)->first();
                    $options = [
                        "key" => $this->ApiKey,
                        "token" => $this->ApiToken
                    ];
                    $allCustomFields = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/$BOARD->board_id/customFields" . "?" . http_build_query($options))
                    );
                    $nameEst = "Deploy";
                    $valueEst = $newRelease->text;
                    foreach ($allCustomFields as $customField) {
                        if ($customField->name == $nameEst)
                            $customFieldDeploy = $customField->id;
                    }
                    if (isset($customFieldDeploy)) {
                        $urlField = "https://api.trello.com/1/card/$card->card_id/customField/$customFieldDeploy/item";
                        $data = array(
                            'value' => array(
                                'text' => $valueEst
                            ),
                            'key' => $this->ApiKey,
                            'token' => $this->ApiToken
                        );

                        $ch = curl_init($urlField);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                        $r = curl_exec($ch);
                        curl_close($ch);
                    } else {
                        $curl = curl_init();

                        $obj = [
                            "idModel" => $BOARD->board_id,
                            "modelType" => "board",
                            "name" => $nameEst,
                            "type" => "text"
                        ];

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => "https://api.trello.com/1/customFields?key=$this->ApiKey&token=$this->ApiToken",
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_POSTFIELDS => json_encode($obj),
                            CURLOPT_HTTPHEADER => array(
                                'Content-Type: application/json'
                            ),
                        ));

                        $response = curl_exec($curl);
                        curl_close($curl);

                        if ($response) {
                            $allCustomFields = json_decode(
                                file_get_contents("https://api.trello.com/1/boards/$BOARD->board_id/customFields" . "?" . http_build_query($options))
                            );

                            foreach ($allCustomFields as $customField) {
                                if ($customField->name == $nameEst)
                                    $customFieldDeploy = $customField->id;
                            }
                            if (isset($customFieldDeploy)) {
                                $urlField = "https://api.trello.com/1/card/$card->card_id/customField/$customFieldDeploy/item";
                                $data = array(
                                    'value' => array(
                                        'text' => $valueEst
                                    ),
                                    'key' => $this->ApiKey,
                                    'token' => $this->ApiToken
                                );

                                $ch = curl_init($urlField);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                                curl_exec($ch);
                                curl_close($ch);
                            }
                        }
                    }
                }
                $card->end_date = $tomorrow;
                $card->save();
            }
            curl_close($ch);
        }
    }

    public function setDevStatistic()
    {
        $data = $this->taskModel->getDevInfo();
        $res = $data->map(function ($card) {
            $card->cof = round($card->fact_est / $card->plan_est, 2);
            return $card;
        });
        $arr = [];
        foreach ($res as $item) {
            if (!isset($arr[$item->member])) {
                $arr[$item->member] = [];
                $arr[$item->member]["back_cof_fact"] = 0;
                $arr[$item->member]["front_cof_fact"] = 0;
            }
            $arr[$item->member]["fact_est"] = $item->fact_est;
            $arr[$item->member]["plan_est"] = $item->plan_est;
            $arr[$item->member]["fact_est"] = $item->fact_est;
            $arr[$item->member]["member"] = $item->member;
            $arr[$item->member]["role"] = $item->role;
            $arr[$item->member]["front_cof"] = $item->front_cof;
            $arr[$item->member]["back_cof"] = $item->back_cof;
            if ($item->part == "Backend")
                $arr[$item->member]["back_cof_fact"] = $item->cof;
            if ($item->part == "Frontend")
                $arr[$item->member]["front_cof_fact"] = $item->cof;
        }
        foreach ($arr as $item) {
            DevStatistic::updateOrCreate(["member" => $item["member"], "date" => now()->format('Y-m-d')], [
                "plan_back" => $item["back_cof"],
                "plan_front" => $item["front_cof"],
                "fact_back" => $item["front_cof_fact"],
                "fact_front" => $item["back_cof_fact"]
            ]);
        }
    }

    public function setPriority()
    {
        $cards = Card::where("priority", null)->orderBy("updated_at")->get();
        $arrPriorities = ["1", "2", "3", "4", "5"];
        foreach ($cards as $card) {
            $url = "https://api.trello.com/1/cards/{$card->card_id}?key={$this->ApiKey}&token={$this->ApiToken}";
            $response = @file_get_contents($url);
            if ($response === FALSE) {
                foreach ($http_response_header as $header) {
                    if (strpos($header, '404 Not Found') !== false) {
                        $card->delete();
                        return;
                    }
                }
                continue;
            }
            $data = json_decode($response, true);
            $labelNames = [];
            if (isset($data['labels']) && is_array($data['labels'])) {
                foreach ($data['labels'] as $label) {
                    $labelNames[] = $label['name'];
                }
                foreach ($labelNames as $name) {
                    if (in_array($name, $arrPriorities)) {
                        $card->priority = $name;
                        $card->save();
                    }
                }
            }
        }
    }

    public function setWayCard($client, $project, $milestone, $release, $card)
    {
        $CARD = Card::where("card_id", $card->card_id)->first();
        if ($project) {
            $projectRes = Project::updateOrCreate([
                "board_id" => $client,
                "text" => $project
            ]);

            if ($projectRes && $milestone) {
                $CARD->project_id = $projectRes->id;
                $milestoneRes = Milestone::updateOrCreate([
                    "project_id" => $projectRes->id,
                    "text" => $milestone
                ]);
                if ($milestoneRes && $release) {
                    $CARD->milestone_id = $milestoneRes->id;
                    $releaseRes = Release::updateOrCreate([
                        "milestone_id" => $milestoneRes->id,
                        "text" => $release
                    ]);
                    if ($releaseRes && $card->name) {
                        $CARD->release_id = $releaseRes->id;
                        $taskRes = Task::create([
                            "release_id" => $releaseRes->id,
                            "text" => $card->name
                        ]);
                        if ($taskRes)
                            $CARD->task_id = $taskRes->id;
                    }
                }
            }
        }
        $CARD->save();
        return $CARD;
    }

    function sendCurlRequest($url, $method = 'GET', $data = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = [
            "Accept: application/json",
            "Content-Type: application/json",
        ];

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $query = http_build_query([
            'key' => $this->ApiKey,
            'token' => $this->ApiToken,
        ]);

        curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function changeCustomField()
    {
        $boards = Board::all();
        foreach ($boards as $board) {
            $customFields = $this->sendCurlRequest("https://api.trello.com/1/boards/{$board->board_id}/customFields");
            if (!empty($customFields)) {
                foreach ($customFields as $field) {
                    if (isset($field['name'])) {
                        if ($field['name'] == 'EstimationDS') {
                            $fieldId = $field['id'];
                            $this->sendCurlRequest("https://api.trello.com/1/customFields/{$fieldId}", 'DELETE');
                        }
                        if ($field['name'] == 'EstimationPM') {
                            $fieldId = $field['id'];
                            $this->sendCurlRequest("https://api.trello.com/1/customFields/{$fieldId}", 'DELETE');
                        }
//                        if ($field['name'] == 'Estim') {
//                            $fieldId = $field['id'];
//                            $this->sendCurlRequest("https://api.trello.com/1/customFields/{$fieldId}", 'PUT', [
//                                'name' => 'EstimIP'
//                            ]);
//                        }
                        if ($field['name'] == 'Estimation') {
                            $fieldId = $field['id'];
                            $this->sendCurlRequest("https://api.trello.com/1/customFields/{$fieldId}", 'PUT', [
                                'name' => 'Estim'
                            ]);
                        }
                        if ($field['name'] == 'EstimationQA') {
                            $fieldId = $field['id'];
                            $this->sendCurlRequest("https://api.trello.com/1/customFields/{$fieldId}", 'PUT', [
                                'name' => 'EstimQA'
                            ]);
                        }
                    }
                }
            }
        }
    }

    public function setEstim()
    {
        $cards = Card::where("estim", null)->where("estimIP", null)->where("estimQA", null)->where("extra", null)->get();
        foreach ($cards as $card) {
            $customFields = $this->sendCurlRequest("https://api.trello.com/1/cards/{$card->card_id}/customFieldItems");
            if (!empty($customFields)) {
                foreach ($customFields as $field) {
                    $fieldId = $field["idCustomField"];
                    $data = $this->sendCurlRequest("https://api.trello.com/1/customFields/{$fieldId}");
//                dd($data);
                    if (isset($data["name"])) {
                        switch ($data["name"]) {
                            case 'Estim':
                            {
                                $card->estim = $field['value']['number'];
                                break;
                            }
                            case 'EstimQA':
                            {
                                $card->estimQA = $field['value']['number'];
                                break;
                            }
                            case 'Extra':
                            {
                                $card->extra = $field['value']['number'];
                                break;
                            }
                            case 'EstimIP':
                            {
                                $card->estimIP = $field['value']['number'];
                                break;
                            }
                            default:
                                break;
                        }
                        $card->save();
                    }
                }
            }
        }
    }

    public function removeCardsFromHotFixColumn()
    {
        $boards = Board::all();
        foreach ($boards as $board) {
            $lists = $this->sendCurlRequest("https://api.trello.com/1/boards/{$board->board_id}/lists");
            $hotFixListId = null;
            $bugFixListId = null;
            if (!empty($lists)) {
                foreach ($lists as $list) {
                    if ($list['name'] == 'Hot Fix') {
                        $hotFixListId = $list['id'];
                    } elseif ($list['name'] == 'Bug Fix') {
                        $bugFixListId = $list['id'];
                    }
                }
                if (!$hotFixListId || !$bugFixListId) {
                    continue;
                }
                $cards = $this->sendCurlRequest("https://api.trello.com/1/lists/{$hotFixListId}/cards");
                foreach ($cards as $card) {
                    $res = $this->sendCurlRequest("https://api.trello.com/1/cards/{$card['id']}", 'PUT', ["idList" => $bugFixListId]);
                }
            }
        }
    }

    public function renameBugFixColumn()
    {
        $boards = Board::all();
        foreach ($boards as $board) {
            $lists = $this->sendCurlRequest("https://api.trello.com/1/boards/{$board->board_id}/lists");
            $bugFixListId = null;
            $hotFixListId = null;
            if (!empty($lists)) {
                foreach ($lists as $list) {
                    if ($list['name'] == 'Bug Fix') {
                        $bugFixListId = $list['id'];
                    }
                    if ($list['name'] == 'Hot Fix') {
                        $hotFixListId = $list['id'];
                    }
                }
                $response = $this->sendCurlRequest("https://api.trello.com/1/lists/{$bugFixListId}", 'PUT', ["name" => "Bugs"]);
                $response = $this->sendCurlRequest("https://api.trello.com/1/lists/{$hotFixListId}/closed", 'PUT', ["value" => true]);
            }
        }
    }

    public function notifyValera($message)
    {
        $params = [
            'text' => $message,
            'chat_id' => "552688206",
            'parse_mode' => 'HTML'
        ];
        json_decode(
            file_get_contents($this->base_url . "sendMessage?" . http_build_query($params)),
            JSON_OBJECT_AS_ARRAY
        );
    }

    public function notifyMemberAboutError($message, $userNames, $error)
    {
        foreach ($userNames as $userName) {
            $userTrello = trello_users::where('name', $userName)->first();
            if ($userTrello) {
                $userTg = tg_users::where("name", $userTrello->tg_username)->first();
                if ($userTg) {
                    $params = [
                        'text' => "<b>ATTENTION!</b>
Comment was INCORRECTLY made
Comment text following: $message
ERROR: $error",
                        'chat_id' => $userTg->chat_id,
                        'parse_mode' => 'HTML'
                    ];
                    json_decode(
                        file_get_contents($this->base_url . "sendMessage?" . http_build_query($params)),
                        JSON_OBJECT_AS_ARRAY
                    );
                }
            }
        }
    }

    public function notifyMemberAboutTaskError($id, $userNames, $error, $link = null)
    {
        foreach ($userNames as $userName) {

            $userTrello = trello_users::where('name', $userName)->first();
            if ($userTrello) {
                $userTg = tg_users::where("name", $userTrello->tg_username)->first();
                if ($userTg) {
                    $params = [
                        'text' => "<b>ATTENTION!</b>
SubTask was INCORRECTLY made
SubTask id: $id
ERROR: $error
Link: $link",
                        'chat_id' => $userTg->chat_id,
                        'parse_mode' => 'HTML'
                    ];
                    json_decode(
                        file_get_contents($this->base_url . "sendMessage?" . http_build_query($params)),
                        JSON_OBJECT_AS_ARRAY
                    );
                }
            }
        }
    }

    function createTaskForError($type, $error, $members)
    {
        foreach ($members as $member) {
            $hash = hash('sha256', Carbon::now()->toDateTimeString());
            $start = rand(0, strlen($hash) - 10);
            $random_characters = substr($hash, $start, 10);
            $customId = 'SUB' . $random_characters;
            $weekNumber = Carbon::now()->weekOfYear;
            $today = now()->format('Y-m-d');
            $obj = [
                "board_name" => "IP | CRM",
                "html_link" => "https://projects.dev.yeducoders.com/IP.html",
                "card" => [
                    "id" => $customId,
                    "name" => $type . " was INCORRECTLY made. Error text: " . $error,
                    "estimation" => "EstimIP 30",
                    "label" => $type == "Comment" ? "1" : "2",
                    "part" => "B",
                    "milestone" => "PMD extension",
                    "release" => "Week - " . $weekNumber,
                    "project" => "CRM",
                    "start_date" => $today,
                    "due_date" => $today,
                    "description" => [
                        "links" => [],
                        "notes" => []
                    ]
                ],
                "members" => [
                    ["name" => $member]
                ],
                "action" => [
                    "display" => [
                        "translationKey" => "card_trello"
                    ]
                ],
                "type" => "Bugs"
            ];
            $this->sendMessage("sendMessage", true, $obj);
        }
    }

    public function setDesc()
    {
        $cards = Card::where("desc", null)->where('column', "!=", null)->where('member', "!=", null)->get();
        foreach ($cards as $card) {
            $url = "https://api.trello.com/1/cards/{$card->card_id}";
            $res = $this->sendCurlRequest($url);
            if (isset($res["desc"])) {
                $desc = $res["desc"];
                $card->update([
                    "desc" => $desc
                ]);
            }
        }
    }

    public function getShortNameMember($name)
    {
        $shortName = "";
        switch ($name) {
            case "Kostyuk Vitaly":
            {
                $shortName = "vk";
                break;
            }
            case "Igor Dzhenkov":
            {
                $shortName = "id";
                break;
            }
            case "Inna Pogrebna":
            {
                $shortName = "ip";
                break;
            }
            case "Pavlo Melnyk":
            {
                $shortName = "pm";
                break;
            }
            case "Valerii Nuzhnyi":
            {
                $shortName = "vn";
                break;
            }
            case "Alex Verbovskiy":
            {
                $shortName = "av";
                break;
            }
        }
        return $shortName;
    }

    public function createMeetTasks()
    {
        $members = trello_users::where("pm", "Igor Dzhenkov")->get();
//        $members = trello_users::where("name", "Pavlo Melnyk")->get();
        foreach ($members as $member) {
            $today = now()->format('Y-m-d');
            $customId = 'MEET' . $today;
            $obj = [
                "board_name" => "IP | ID Tasks",
                "html_link" => "https://projects.dev.yeducoders.com/IP.html",
                "card" => [
                    "id" => $customId,
                    "name" => "Daily stand up",
                    "estimation" => "EstimIP 30",
                    "label" => "1",
                    "part" => "M",
                    "milestone" => "Meeting",
                    "release" => "As usual",
                    "project" => "ID Tasks",
                    "start_date" => $today,
                    "due_date" => $today,
                    "description" => [
                        "links" => [],
                        "notes" => []
                    ]
                ],
                "members" => [
                    ["name" => $this->getShortNameMember($member->name)]
                ],
                "action" => [
                    "display" => [
                        "translationKey" => "card_trello"
                    ]
                ],
                "type" => "In progress"
            ];
            $this->sendMessage("sendMessage", true, $obj);
        }
    }

    public function moveToDoneMeet()
    {
        $today = now()->format('Y-m-d');
        $cards = Card::where("custom_id", "MEET" . $today)->get();
        foreach ($cards as $card) {
            $BOARD = Board::where("id", $card->board_id)->first();
            $lists = json_decode(file_get_contents("https://api.trello.com/1/boards/$BOARD->board_id/lists?key={$this->ApiKey}&token={$this->ApiToken}"));
            foreach ($lists as $list) {
                if ($list->name == "Done") {
                    $listId = $list->id;
                    break;
                }
            }
            if (isset($listId)) {
                $res = $this->sendCurlRequest("https://api.trello.com/1/cards/$card->card_id", 'PUT', [
                    'idList' => $listId
                ]);
            } else {
                $response = $this->sendCurlRequest('https://api.trello.com/1/lists', 'POST', [
                    'name' => "Done",
                    'idBoard' => $BOARD->board_id
                ]);
                if (isset($response['id'])) {
                    $listId = $response['id'];
                    $this->sendCurlRequest("https://api.trello.com/1/cards/$card->card_id", 'PUT', [
                        'idList' => $listId
                    ]);
                }
            }
        }
    }

}
