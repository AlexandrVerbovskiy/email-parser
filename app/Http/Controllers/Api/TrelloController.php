<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WebHookController;
use App\Models\trello_users;
use App\Models\tg_users;
use App\Models\Boards;
use function Symfony\Component\VarDumper\Dumper\esc;


class TrelloController extends Controller
{
    protected string $token;

    protected string $base_url;

    protected string $ApiToken;

    protected string $ApiKey;

    protected $dataTrello;

    protected $chat_id;

    function __construct()
    {
        $this->token = "5892002404:AAFVeb0t4OiCMt3jlCZ5b8P6cXIwIjrCQbw";
        $this->base_url = "https://api.telegram.org/bot" . $this->token . "/";
        $this->ApiToken = "ATTA8300ef671f6f1efaad494df68048e3b8e010e8b7d45b2b57dbeaa879a79fdb82461187C5";
        $this->ApiKey = "15b5c7d5ab35953d609e5228792d4758";
    }

    function check()
    {
        var_dump("ok");
    }

    function sendMessage($method = "sendMessage")
    {
        $this->dataTrello = json_decode(file_get_contents('php://input'), true);
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
                    Boards::create([
                        "board_id" => $board,
                        "name" => $name
                    ]);
                    $webhook = new WebHookController($board);
                    $webhook->trello();
                    break;
                }
                case "action_comment_on_card":
                {
                    $board = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
                    $boardLink = "https://trello.com/b/" . $this->dataTrello["action"]["data"]["board"]["shortLink"];
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
                            $comment = $this->dataTrello["action"]["display"]["entities"]["comment"]["text"];
                            $pattern_name = '/@\w+/';
                            $isMatched = preg_match($pattern_name, $comment, $username);
                            $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
                            $creatorId = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["id"];
                            $communication = "";
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
                            if ($isMatched) {
                                $user = trello_users::where(["tag" => $username[0]])->get();
                                $chat = tg_users::where(["name" => $user[0]->tg_username])->get();
                                $params = [
                                    'text' => "<b>ON BOARD</b> {$board["name"]}
<b>BY</b> {$creator}
<b>ON CARD</b> {$card}

<b>ADDED COMMENT</b> <em>{$comment}</em>

Card Link: <a href='{$cardLink}'>{$cardLink}</a>
Card Id: {$this->dataTrello["action"]["display"]["entities"]["card"]["id"]}
Board Link: <a href='{$boardLink}'>{$boardLink}</a>
{$communication}",
                                    'chat_id' => $chat[0]->chat_id,
                                    'parse_mode' => 'HTML'
                                ];
                                $url = $this->base_url . $method . "?" . http_build_query($params);
                            } else {
                                foreach ($members as $member) {
                                    if (trello_users::where(["trello_id" => $member["id"]])->exists()) {
                                        $user = trello_users::where(["trello_id" => $member["id"]])->get();
                                        if ($member["id"] == $creatorId)
                                            continue;
                                        $chat = tg_users::where(["name" => $user[0]->tg_username])->get();
                                        $params = [
                                            'text' => "<b>ON BOARD</b> {$board["name"]}
<b>BY</b> {$creator}
<b>ON CARD</b> {$card}

<b>ADDED COMMENT</b> <em>{$comment}</em>

Card Link: <a href='{$cardLink}'>{$cardLink}</a>
Card Id: {$this->dataTrello["action"]["display"]["entities"]["card"]["id"]}
Board Link: <a href='{$boardLink}'>{$boardLink}</a>
{$communication}",
                                            'chat_id' => $chat[0]->chat_id,
                                            'parse_mode' => 'HTML'
                                        ];
                                        json_decode(
                                            file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                            JSON_OBJECT_AS_ARRAY
                                        );
                                    }
                                }
                            }
                        }
                    }
                    break;
                }
                case "createCheckItem":
                {
                    $board = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
                    $boardLink = "https://trello.com/b/" . $this->dataTrello["action"]["data"]["board"]["shortLink"];
                    $cardId = $this->dataTrello["action"]["data"]["card"]["id"];
                    $members = json_decode(
                        file_get_contents("https://api.trello.com/1/cards/{$cardId}/members" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $creatorId = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["id"];
                    foreach ($members as $member) {
                        if (trello_users::where(["trello_id" => $member["id"]])->exists()) {
                            $card = $this->dataTrello["action"]["data"]["card"]["name"];
                            $name = $this->dataTrello["action"]["data"]["checkItem"]["name"];
                            $checklist = $this->dataTrello["action"]["data"]["checklist"]["name"];
                            $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
                            $user = trello_users::where(["trello_id" => $member["id"]])->get();
                            if ($member["id"] == $creatorId)
                                continue;
                            $chat = tg_users::where(["name" => $user[0]->tg_username])->get();
                            $params = [
                                'text' => "<b>ON BOARD</b> {$board["name"]}
<b>BY</b> {$creator}
<b>ON CARD</b> {$card}
<b>IN CHECKLIST</b> {$checklist}

<b>ADD TASK</b> <em>{$name}</em>

Card Link: <a href='{$cardLink}'>{$cardLink}</a>
Card Id: {$this->dataTrello["action"]["data"]["card"]["id"]}
Board Link: <a href='{$boardLink}'>{$boardLink}</a>",
                                'chat_id' => $chat[0]->chat_id,
                                'parse_mode' => 'HTML'
                            ];
                            json_decode(
                                file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                JSON_OBJECT_AS_ARRAY
                            );
                        }
                    }
                    break;
                }
                case "action_move_card_from_list_to_list":
                {
                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                    $board = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
                    $boardLink = "https://trello.com/b/" . $this->dataTrello["action"]["data"]["board"]["shortLink"];
                    $members = json_decode(
                        file_get_contents("https://api.trello.com/1/cards/{$cardId}/members" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    if (isset($members)) {
                        $card = $this->dataTrello["action"]["display"]["entities"]["card"]["text"];
                        $listBefore = $this->dataTrello["action"]["display"]["entities"]["listBefore"]['text'];
                        $listAfter = $this->dataTrello["action"]["display"]["entities"]["listAfter"]['text'];
                        $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
                        if ($listAfter == "Ready for QA") {
                            $tmp = false;
                            foreach ($members as $member) {
                                if ($member["id"] == "630e0412ffc3b900d905f65a")
                                    $tmp = true;
                            }
                            if ($tmp) {
                                $user = trello_users::where(["trello_id" => "630e0412ffc3b900d905f65a"])->get();
                                $chat = tg_users::where(["name" => $user[0]->tg_username])->get();
                                $params = [
                                    'text' => "<b>ON BOARD </b> {$board["name"]}
<b>BY</b> {$creator}
<b>MOVED CARD</b> {$card}
<b>FROM LIST</b> {$listBefore}
<b>TO LIST</b> {$listAfter}

Card Link: <a href='{$cardLink}'>{$cardLink}</a>,
Card Id: {$cardId}
Board Link: <a href='{$boardLink}'>{$boardLink}</a>",
                                    'chat_id' => $chat[0]->chat_id,
                                    'parse_mode' => 'HTML'
                                ];
                                json_decode(
                                    file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                    JSON_OBJECT_AS_ARRAY
                                );
                            }
                        } elseif ($listAfter == "QA blocked") {
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

Card Link: <a href='{$cardLink}'>{$cardLink}</a>,
Card Id: {$cardId}
Board Link: <a href='{$boardLink}'>{$boardLink}</a>",
                                        'chat_id' => $chat[0]->chat_id,
                                        'parse_mode' => 'HTML'
                                    ];
                                    json_decode(
                                        file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                        JSON_OBJECT_AS_ARRAY
                                    );
                                }
                            }
                        }

                    }
                    break;
                }
                case "workflow":
                {
                    $id = $this->dataTrello["id"];
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
                            echo $boardName;
                            $BOARD = $item;
                            $allCards = json_decode(
                                file_get_contents("https://api.trello.com/1/boards/$item->id/cards" . "?" . http_build_query($options))
                            );
                            foreach ($allCards as $card) {
                                $data = json_decode(
                                    file_get_contents("https://api.trello.com/1/cards/$card->id/customFieldItems" . "?" . http_build_query($options))
                                );
                                if (!count($data))
                                    continue;
                                if ($data && $data[0]->value->number == $id) {
                                    $CARD = $card;
                                    break;
                                }
                            }
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
                        }
                    }
                    break;
                }
                case "card_trello":
                {
                    $id = $this->dataTrello["id"];
                    if (isset($this->dataTrello["board_name"]))
                    $boardName = $this->dataTrello["board_name"];
                    else return response()->json(['message' => 'Board Name missing'], 400);
                    $BOARD = null;
                    $COLUMN = null;
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
                            $BOARD = $item;
                        }
                    }
                    if ($BOARD) {
                        $columns = json_decode(
                            file_get_contents("https://api.trello.com/1/boards/$BOARD->id/lists" . "?" . http_build_query($options))
                        );
                        if (!isset($this->dataTrello["type"]))
                            return response()->json(['message' => 'Column Name missing'], 400);
                        foreach ($columns as $column) {
                            if ($column->name == $this->dataTrello["type"]) {
                                $COLUMN = $column;
                                break;
                            }
                        }
                        if ($COLUMN) {
                            $headers = array(
                                'Content-Type: application/json',
                                'Accept: application/json'
                            );
                            if (!isset($this->dataTrello["card"]["name"]))
                                return response()->json(['message' => 'Card Name missing'], 400);
                            $params = array(
                                'key' => $this->ApiKey,
                                'token' => $this->ApiToken,
                                'name' => $this->dataTrello["card"]["name"],
                                'idList' => $COLUMN->id
                            );

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, 'https://api.trello.com/1/cards');
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $response = curl_exec($ch);
                            curl_close($ch);
                            $card = json_decode($response);
                            if ($card) {
                                $allCustomFields = json_decode(
                                    file_get_contents("https://api.trello.com/1/boards/$BOARD->id/customFields" . "?" . http_build_query($options))
                                );
                                foreach ($allCustomFields as $customField) {
                                    if ($customField->name == "ID")
                                        $customFieldId = $customField->id;
                                }
                                if (isset($customFieldId)) {
                                    $urlField = "https://api.trello.com/1/card/$card->id/customField/$customFieldId/item";
                                    $data = array(
                                        'value' => array(
                                            'number' => $id
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
                                if (!isset($this->dataTrello["html_link"]))
                                    return response()->json(['message' => 'Html Link to board missing'], 400);
                                $html = str_replace(" ", "%20", $this->dataTrello["html_link"]);
                                $urlDesc = "https://api.trello.com/1/cards/$card->id?desc=" . urlencode($html) . "&key=$this->ApiKey&token=$this->ApiToken";

                                $ch = curl_init($urlDesc);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                curl_exec($ch);
                                curl_close($ch);
                                if (isset($this->dataTrello["card"]["description"]["comments"]) && is_array($this->dataTrello["card"]["description"]["comments"]) && count($this->dataTrello["card"]["description"]["comments"])){
                                    foreach ($this->dataTrello["card"]["description"]["comments"] as $item){
                                        $urlComment = "https://api.trello.com/1/cards/$card->id/actions/comments?key=$this->ApiKey&token=$this->ApiToken";
                                        $fields = array(
                                            'text' => $item["comment"],
                                        );
                                        $ch = curl_init($urlComment);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_POST, true);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
                                        curl_exec($ch);
                                        curl_close($ch);
                                    }
                                }
                                return response()->json(['board_id' => $BOARD->id], 200);

                            }
                            return response()->json(['message' => 'Card Not Found'], 400);
                        }
                        return response()->json(['message' => 'Column Not Found'], 400);
                    }
                    return response()->json(['message' => 'Board Not Found'], 400);
                }
                case "add_comment_workflow":
                {
                    $id = $this->dataTrello["id"];
                    if (isset($this->dataTrello["board_name"]))
                        $boardName = $this->dataTrello["board_name"];
                    else return response()->json(['message' => 'Board Name missing'], 400);
                    $BOARD = null;
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
                            $BOARD = $item;
                        }
                    }
                    if ($BOARD) {
                        $cards = json_decode(
                            file_get_contents("https://api.trello.com/1/boards/$BOARD->id/cards" . "?" . http_build_query($options))
                        );
                        foreach ($cards as $card) {
                            $data = json_decode(
                                file_get_contents("https://api.trello.com/1/cards/$card->id/customFieldItems" . "?" . http_build_query($options))
                            );
                            if (!count($data))
                                continue;
                            if ($data && $data[0]->value->number == $id) {
                                $CARD = $card;
                                break;
                            }
                        }
                        if ($CARD){
                            if (isset($this->dataTrello["card"]["description"]["comments"]) && is_array($this->dataTrello["card"]["description"]["comments"]) && count($this->dataTrello["card"]["description"]["comments"])){
                                foreach ($this->dataTrello["card"]["description"]["comments"] as $item){
                                    $urlComment = "https://api.trello.com/1/cards/$CARD->id/actions/comments?key=$this->ApiKey&token=$this->ApiToken";
                                    $fields = array(
                                        'text' => $item,
                                    );
                                    $ch = curl_init($urlComment);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_POST, true);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
                                    curl_exec($ch);
                                    curl_close($ch);
                                    return response()->json(['board_id' => $BOARD->id], 200);
                                }
                            }return response()->json(['message' => 'Comment Not Found'], 400);
                        }
                        return response()->json(['message' => 'Card Not Found'], 400);
                    }
                    return response()->json(['message' => 'Board Not Found'], 400);
                }
                case
                    "action_completed_checkitem" || "action_marked_checkitem_incomplete":
                {
                    $board = json_decode(
                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
                    $boardLink = "https://trello.com/b/" . $this->dataTrello["action"]["data"]["board"]["shortLink"];
                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                    $members = json_decode(
                        file_get_contents("https://api.trello.com/1/cards/{$cardId}/members" . "?" . http_build_query($options)),
                        JSON_OBJECT_AS_ARRAY
                    );
                    foreach ($members as $member) {
                        if (trello_users::where(["trello_id" => $member["id"]])->exists()) {
                            $card = $this->dataTrello["action"]["display"]["entities"]["card"]["text"];
                            $task = $this->dataTrello["action"]["data"]["checkItem"]["name"];
                            $checklist = $this->dataTrello["action"]["data"]["checklist"]["name"];
                            $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
                            $creatorId = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["id"];
                            $user = trello_users::where(["trello_id" => $member["id"]])->get();
                            if ($member["id"] == $creatorId)
                                continue;
                            $chat = tg_users::where(["name" => $user[0]->tg_username])->get();
                            if ($action === "action_completed_checkitem")
                                $params = [
                                    'text' => "<b>ON BOARD</b> {$board["name"]}
<b>BY</b> {$creator}
<b>ON CARD</b> {$card}
<b>IN CHECKLIST</b> {$checklist}

<b>COMPLETE TASK</b> <em>{$task}</em>

Card Link: <a href='{$cardLink}'>{$cardLink}</a>
Card Id: {$this->dataTrello["action"]["display"]["entities"]["card"]["id"]}
Board Link: <a href='{$boardLink}'>{$boardLink}</a>",
                                    'chat_id' => $chat[0]->chat_id,
                                    'parse_mode' => 'HTML'
                                ];
                            if ($action === "action_marked_checkitem_incomplete")
                                $params = [
                                    'text' => "<b>ON BOARD</b> {$board["name"]}
<b>BY</b> {$creator}
<b>ON CARD</b> {$card}
<b>IN CHECKLIST</b> {$checklist}

<b>REMOVE MARKER COMPLETE FROM TASK</b> <em>{$task}</em>

Card Link: <a href='{$cardLink}'>{$cardLink}</a>
Card Id: {$this->dataTrello["action"]["display"]["entities"]["card"]["id"]}
Board Link: <a href='{$boardLink}'>{$boardLink}</a>",
                                    'chat_id' => $chat[0]->chat_id,
                                    'parse_mode' => 'HTML'
                                ];
                            json_decode(
                                file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                JSON_OBJECT_AS_ARRAY
                            );
                        }
                    }
                    break;
                }

//                case "action_create_card":
//                {
//                    $name = $this->dataTrello["action"]["display"]["entities"]["card"]["text"];
//                    $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
//                    $board = json_decode(
//                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
//                        JSON_OBJECT_AS_ARRAY
//                    );
//                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
//                    $params = [
//                        'text' => "<b>ON BOARD </b> {$board["name"]}
//<b>BY</b> {$creator}
//<b>CREATED CARD</b> {$name}
//
//Card Link: <a href='{$cardLink}'>{$cardLink}</a>",
//                        'chat_id' => $this->chat_id,
//                        'parse_mode' => 'HTML'
//                    ];
//                    $members = json_decode(
//                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}/members" . "?" . http_build_query($options)),
//                        JSON_OBJECT_AS_ARRAY
//                    );
//                    $check = false;
//                    if (isset($members)) {
//                        foreach ($members as $member) {
//                            $tmp = trello_users::where(["trello_id" => $member["id"]]);
//                            if ($tmp) {
//                                $check = true;
//                                break;
//                            }
//                        }
//                        if ($check)
//                            $url = $this->base_url . $method . "?" . http_build_query($params);
//                    }
//                    break;
//                }
//                case "action_changed_description_of_card":
//                {
//                    $board = json_decode(
//                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
//                        JSON_OBJECT_AS_ARRAY
//                    );
//                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
//                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
//                    $members = json_decode(
//                        file_get_contents("https://api.trello.com/1/cards/{$cardId}/members" . "?" . http_build_query($options)),
//                        JSON_OBJECT_AS_ARRAY
//                    );
//                    $check = false;
//                    if (isset($members)) {
//                        foreach ($members as $member) {
//                            $tmp = trello_users::where(["trello_id" => $member["id"]]);
//                            if ($tmp) {
//                                $check = true;
//                                break;
//                            }
//                        }
//                        if ($check) {
//                            $card = $this->dataTrello["action"]["display"]["entities"]["card"]["text"];
//                            $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
//                            $descNew = $this->dataTrello["action"]["display"]["entities"]["card"]["desc"];
//                            $descOld = $this->dataTrello["action"]["data"]["old"]["desc"];
//                            if (!isset($descOld) || $descOld == "") {
//                                $params = [
//                                    'text' => "<b>ON BOARD</b> {$board["name"]}
//<b>BY</b> {$creator}
//<b>ON CARD</b>  {$card}
//<b>ADDED DESCRIPTION</b>  <em>{$descNew}</em>
//
//Card Link: <a href='{$cardLink}'>{$cardLink}</a>",
//                                    'chat_id' => $this->chat_id,
//                                    'parse_mode' => 'HTML'
//                                ];
//                            } else {
//                                $params = [
//                                    'text' => "<b>ON BOARD</b> {$board["name"]}
//<b>BY</b> {$creator}
//<b>ON CARD</b> {$card}
//<b>CHANGED DESCRIPTION</b>
//
//<b>BEFORE</b> <em>{$descOld}</em>
//
//<b>NOW</b>  <em>{$descNew}</em>
//
//Card Link: <a href='{$cardLink}'>{$cardLink}</a>",
//                                    'chat_id' => $this->chat_id,
//                                    'parse_mode' => 'HTML'
//                                ];
//                            }
//                            $url = $this->base_url . $method . "?" . http_build_query($params);
//                        }
//                    }
//                    break;
//                }
//                case "action_renamed_card":
//                {
//                    $board = json_decode(
//                        file_get_contents("https://api.trello.com/1/boards/{$this->dataTrello["model"]["id"]}" . "?" . http_build_query($options)),
//                        JSON_OBJECT_AS_ARRAY
//                    );
//                    $cardLink = "https://trello.com/c/" . $this->dataTrello["action"]["data"]["card"]["shortLink"];
//                    $cardId = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
//                    $members = json_decode(
//                        file_get_contents("https://api.trello.com/1/cards/{$cardId}/members" . "?" . http_build_query($options)),
//                        JSON_OBJECT_AS_ARRAY
//                    );
//                    $check = false;
//                    if (isset($members)) {
//                        foreach ($members as $member) {
//                            $tmp = trello_users::where(["trello_id" => $member["id"]]);
//                            if ($tmp) {
//                                $check = true;
//                                break;
//                            }
//                        }
//                        if ($check) {
//                            $nameNew = $this->dataTrello["action"]["display"]["entities"]["card"]["text"];
//                            $nameOld = $this->dataTrello["action"]["data"]["old"]["name"];
//                            $creator = $this->dataTrello["action"]["display"]["entities"]["memberCreator"]["text"];
//                            $params = [
//                                'text' => "<b>ON BOARD</b> {$board["name"]}
//<b>BY</b> {$creator}
//<b>RENAME CARD</b> {$nameOld}
//<b>ON {$nameNew}</b>
//
//Card Link: <a href='{$cardLink}'>{$cardLink}</a>",
//                                'chat_id' => $this->chat_id,
//                                'parse_mode' => 'HTML'
//                            ];
//                            $url = $this->base_url . $method . "?" . http_build_query($params);
//                        }
//                    }
//                    break;
//                }
                default:
                    break;
            }
            if (isset($url))
                return json_decode(
                    file_get_contents($url),
                    JSON_OBJECT_AS_ARRAY
                );
        }
    }

}
