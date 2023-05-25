<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WebHookController;
use App\Models\trello_users;
use App\Models\tg_users;
use App\Models\Boards;
use Illuminate\Support\Facades\Log;
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
                            $workflowLink = "https://projects.dev.yeducoders.com/" . str_replace(" ", "%20", $this->dataTrello["action"]["data"]["board"]["name"]) . ".html";
                            $urlWorkflow = env("WORKFLOW_URL") . 'comments/trello';
                            $card_id = $this->dataTrello["action"]["display"]["entities"]["card"]["id"];
                            if ($this->dataTrello["action"]["data"]["list"]["name"] != "ARCHIVED COMMENTS") {
                                $cf = json_decode(
                                    file_get_contents("https://api.trello.com/1/cards/$card_id/customFieldItems" . "?" . http_build_query($options))
                                );
                                if (isset($cf[0])) {
                                    $data = array(
                                        'user_id' => 'Ukraine',
                                        'task_id' => strval($cf[0]->value->text),
                                        'comment' => $comment,
                                        'board_name' => $board["name"]
                                    );

                                    $proxy = "86.38.177.114:50100";
                                    $proxyAuth = "Selkostyukvitaly98:Z0d4CsI";

                                    $curl = curl_init($urlWorkflow);
                                    curl_setopt($curl, CURLOPT_POST, true);
                                    curl_setopt($curl, CURLOPT_PROXY, $proxy);
                                    curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
                                    curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                                    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                                    $r = curl_exec($curl);
                                    Log::info('Data', ["data" => $r]);

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
Workflow Link: <a href='{$workflowLink}'>{$workflowLink}</a>
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
Workflow Link: <a href='{$workflowLink}'>{$workflowLink}</a>
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
                        $workflowLink = "https://projects.dev.yeducoders.com/" . str_replace(" ", "%20", $this->dataTrello["action"]["data"]["board"]["name"]) . ".html";
                        $boardId = $board["id"];
                        $boardName = $board["name"];
                        if ($listAfter == "Ready for QA") {
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
                                $value = $customFieldValue[0]['value']['text'];

                                $proxy = "86.38.177.114:50100";
                                $proxyAuth = "Selkostyukvitaly98:Z0d4CsI";
                                $curl = curl_init();
                                curl_setopt($curl, CURLOPT_PROXY, $proxy);
                                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
                                curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/change/ready',
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',
                                    CURLOPT_POSTFIELDS => json_encode(array("task_id" => $value, "board_name" => $boardName)),
                                    CURLOPT_HTTPHEADER => array(
                                        'Content-Type: application/json',
                                        'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                    ),
                                ));

                                $response = curl_exec($curl);
                                Log::info('pos', ["pos" => $response]);

                            }
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
Board Link: <a href='{$boardLink}'>{$boardLink}</a>
Workflow Link: <a href='{$workflowLink}'>{$workflowLink}</a>",
                                    'chat_id' => $chat[0]->chat_id,
                                    'parse_mode' => 'HTML'
                                ];
                                json_decode(
                                    file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                    JSON_OBJECT_AS_ARRAY
                                );
                            }
                        } elseif ($listAfter == "Blocked by QA") {
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
                                $value = $customFieldValue[0]['value']['text'];

                                $proxy = "86.38.177.114:50100";
                                $proxyAuth = "Selkostyukvitaly98:Z0d4CsI";
                                $curl = curl_init();
                                curl_setopt($curl, CURLOPT_PROXY, $proxy);
                                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
                                curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/change/blocked',
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',
                                    CURLOPT_POSTFIELDS => json_encode(array("task_id" => $value, "board_name" => $boardName)),
                                    CURLOPT_HTTPHEADER => array(
                                        'Content-Type: application/json',
                                        'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                    ),
                                ));

                                $response = curl_exec($curl);
                                Log::info('pos', ["pos" => $response]);

                            }
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
Board Link: <a href='{$boardLink}'>{$boardLink}</a>
Workflow Link: <a href='{$workflowLink}'>{$workflowLink}</a>",
                                        'chat_id' => $chat[0]->chat_id,
                                        'parse_mode' => 'HTML'
                                    ];
                                    json_decode(
                                        file_get_contents($this->base_url . $method . "?" . http_build_query($params)),
                                        JSON_OBJECT_AS_ARRAY
                                    );
                                }
                            }
                        } elseif ($listAfter == "In Progress") {
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
                                $value = $customFieldValue[0]['value']['text'];

                                $proxy = "86.38.177.114:50100";
                                $proxyAuth = "Selkostyukvitaly98:Z0d4CsI";
                                $curl = curl_init();
                                curl_setopt($curl, CURLOPT_PROXY, $proxy);
                                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
                                curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/change/inprogress',
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',
                                    CURLOPT_POSTFIELDS => json_encode(array("task_id" => $value, "board_name" => $boardName)),
                                    CURLOPT_HTTPHEADER => array(
                                        'Content-Type: application/json',
                                        'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                    ),
                                ));

                                $response = curl_exec($curl);
                                Log::info('pos', ["pos" => $response]);

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
                                $value = $customFieldValue[0]['value']['text'];

                                $proxy = "86.38.177.114:50100";
                                $proxyAuth = "Selkostyukvitaly98:Z0d4CsI";
                                $curl = curl_init();
                                curl_setopt($curl, CURLOPT_PROXY, $proxy);
                                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
                                curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/change/backlog',
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',
                                    CURLOPT_POSTFIELDS => json_encode(array("task_id" => $value, "board_name" => $boardName)),
                                    CURLOPT_HTTPHEADER => array(
                                        'Content-Type: application/json',
                                        'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                    ),
                                ));

                                $response = curl_exec($curl);
                                Log::info('pos', ["pos" => $response]);

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
                                $value = $customFieldValue[0]['value']['text'];

                                $proxy = "86.38.177.114:50100";
                                $proxyAuth = "Selkostyukvitaly98:Z0d4CsI";
                                $curl = curl_init();
                                curl_setopt($curl, CURLOPT_PROXY, $proxy);
                                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
                                curl_setopt($curl, CURLOPT_PROXYTYPE, 'CURLPROXY_HTTP');
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => env("WORKFLOW_URL") . 'tasks/change/done',
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',
                                    CURLOPT_POSTFIELDS => json_encode(array("task_id" => $value, "board_name" => $boardName)),
                                    CURLOPT_HTTPHEADER => array(
                                        'Content-Type: application/json',
                                        'Cookie: XSRF-TOKEN=eyJpdiI6Ijd6cnBIMzFPY3h4N0trY2ozWXNsWnc9PSIsInZhbHVlIjoiOGI1TktuR3FwcnJETzVHbE8xTzJvdTlOalZuM3pRd041eGNEU2pXemJ5c3QwbVptRWp1TUFuaUNYaXM0MTFSWWhGaVdDaDdIaDZ2a1VUSEE3NUI2T2g5encvWHkzVmlXWVhHYzBWc09IRDc5L2FYMGZucUVDUkowUXdvRlJJc0IiLCJtYWMiOiI3NjQ3YTg4YmU3MDE5MTZhNmZjMDVmY2Q2NDM2YzI4NTZjMjgxYmNiMTdlNTM3ZWMxNTJlZjhhNDE2YTgxYTU0IiwidGFnIjoiIn0%3D; laravel_session=eyJpdiI6Ik5EYngxM01BWkI4YTFHdFF3RFczQnc9PSIsInZhbHVlIjoiM3VodkFDME5SUUJmNGlYMnRwaUI5anZGZ2gyYTVWa05mZjBjMGtsRUx6Vml6M3Y5aVhvcVE4WWtiZHVvUDU4dC92OXVHQmN1azFvUDNOR0NabHJRSE1YcFBHbHNvcVZJOTRJQ3N3SFV5L0hKcVhXMDkwdkQ4ZUtjeE9zL3dncVEiLCJtYWMiOiJkYzgzYThkMTdlOTRhMWJiNDMyY2UxNWMwMTZmMWIzNjJjZGExNzBjYTM3ZjQ3NzRjMzVjYzdiMWY0YWU0MGMxIiwidGFnIjoiIn0%3D'
                                    ),
                                ));

                                $response = curl_exec($curl);
                                Log::info('pos', ["pos" => $response]);

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
//                            echo $boardName;
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
                                if ($data && $data[0]->value->text == strval($id)) {
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
                    $log = 0;
                    $id = $this->dataTrello["id"] ?? 0;
                    if (isset($this->dataTrello["board_name"]))
                        $boardName = $this->dataTrello["board_name"];
                    else return response()->json(['message' => 'Board Name missing'], 400);
                    $BOARD = null;
                    $COLUMN = null;
                    $allBoards = json_decode(
                        file_get_contents("https://api.trello.com/1/organizations/61a62d1ab530a327f2e18ce5/boards" . "?" . http_build_query($options))
                    );
                    $log++;
                    foreach ($allBoards as $item) {
                        if ($item->name == $boardName) {
//                            $ch = curl_init();
//                            curl_setopt($ch, CURLOPT_URL, "https://webhook.site/ed78c015-5e94-474f-b89f-6f196caa57d9");
//                            curl_setopt($ch, CURLOPT_POST, 1);
//                            curl_setopt($ch, CURLOP T_POSTFIELDS,
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
                        $log++;
                        if (!isset($this->dataTrello["type"]))
                            return response()->json(['message' => 'Column Name missing'], 400);
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
                            if (!isset($this->dataTrello["card"]["name"]))
                                return response()->json(['message' => 'Card Name missing'], 400);
                            $params = array(
                                'key' => $this->ApiKey,
                                'token' => $this->ApiToken,
                                'name' => $this->dataTrello["card"]["name"],
                                'idList' => $COLUMN->id
                            );
//                            $cards = json_decode(
//                                file_get_contents("https://api.trello.com/1/lists/$COLUMN->id/cards" . "?" . http_build_query($options))
//                            );
                            $tmp = true;
//                            if ($cards) {
//                                foreach ($cards as $item) {
//                                    $cf = json_decode(
//                                        file_get_contents("https://api.trello.com/1/cards/$item->id/customFieldItems" . "?" . http_build_query($options))
//                                    );
//                                    if (!count($cf))
//                                        continue;
//                                    if ($cf && $cf[0]->value->number == $id) {
//                                        $tmp = false;
//                                        break;
//                                    }
//
//                                }
//                            }
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
                                if (!isset($this->dataTrello["html_link"]))
                                    return response()->json(['message' => 'Html Link to board missing'], 400);
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
                                        $urlComment = "https://api.trello.com/1/cards/$card->id/actions/comments?key=$this->ApiKey&token=$this->ApiToken";
                                        $fields = array(
                                            'text' => isset($item["status"]) ? "[" . $item["user_id"] . "][" . $item["status"] . "] " . $item["comment"] : "[" . $item["user_id"] . "] " . $item["comment"],
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
                                $url = 'https://api.trello.com/1/boards/' . $BOARD->id . '/members?key=' . $this->ApiKey . '&token=' . $this->ApiToken;

                                $options = array(
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_URL => $url
                                );

                                $curl = curl_init();

                                curl_setopt_array($curl, $options);

                                $result = curl_exec($curl);

                                curl_close($curl);

                                if ($result !== false) {
                                    $members = json_decode($result);
                                    foreach ($members as $item) {
                                        $url = "https://api.trello.com/1/cards/$card->id/idMembers";

                                        $data = array(
                                            'value' => $item->id,
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
                                        $log++;
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
                                    Log::info('time', ["pos" => $response]);
                                }

                                return response()->json(['board_id' => $BOARD->id, 'log' => $log], 200);
                            }
                            return response()->json(['message' => 'Card Not Found'], 400);
//                            return response()->json(['message' => 'Card Already Exists'], 400);
                        }
                        return response()->json(['message' => 'Column Not Found'], 400);
                    }
                    return response()->json(['message' => 'Board Not Found'], 400);
                }
                case
                "add_comment_workflow":
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
                            if ($data && $data[0]->value->text == strval($id)) {
                                $CARD = $card;
                                break;
                            }
                        }
                        if ($CARD) {
                            if (isset($this->dataTrello["card"]["description"]["comments"]) && is_array($this->dataTrello["card"]["description"]["comments"]) && count($this->dataTrello["card"]["description"]["comments"])) {
                                foreach ($this->dataTrello["card"]["description"]["comments"] as $item) {
                                    $urlComment = "https://api.trello.com/1/cards/$CARD->id/actions/comments?key=$this->ApiKey&token=$this->ApiToken";
                                    $fields = array(
                                        'text' => isset($this->dataTrello["status"]) ? "[" . $this->dataTrello["user_id"] . "][" . $this->dataTrello["status"] . "] " . $item : "[" . $this->dataTrello["user_id"] . "] " . $item,
                                    );
                                    $ch = curl_init($urlComment);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_POST, true);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
                                    curl_exec($ch);
                                    curl_close($ch);
                                }
                                return response()->json(['board_id' => $BOARD->id], 200);
                            }
                            return response()->json(['message' => 'Comment Not Found'], 400);
                        }
                        return response()->json(['message' => 'Card Not Found'], 400);
                    }
                    return response()->json(['message' => 'Board Not Found'], 400);
                }
                case "archive_card":
                {
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
                                if ($card->name == $this->dataTrello["card"]["name"]) {
                                    $data = json_decode(
                                        file_get_contents("https://api.trello.com/1/cards/$card->id/customFieldItems" . "?" . http_build_query($options))
                                    );
                                    if (!count($data))
                                        continue;
                                    if ($data && $data[0]->value->text == strval($this->dataTrello["card"]["id"])) {
                                        $CARD = $card;
                                        break;
                                    }
                                }
                            }
                            if ($CARD) {
                                $url = "https://api.trello.com/1/cards/{$CARD->id}?closed=true&key=$this->ApiKey&token=$this->ApiToken";
                                $ch = curl_init($url);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_HEADER, false);

                                curl_exec($ch);

                                curl_close($ch);
                                return response()->json(['message' => 'Card has been archived'], 200);
                            } else
                                return response()->json(['message' => 'Card not found or archived earlier'], 200);
                        }
                        return response()->json(['message' => 'Board Not Found'], 400);
                    }
                    return response()->json(['message' => 'Board Not Found'], 400);
                }
                case "update_card_trello":
                {
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
                                if ($card->name == $this->dataTrello["card"]["name"]) {
                                    $data = json_decode(
                                        file_get_contents("https://api.trello.com/1/cards/$card->id/customFieldItems" . "?" . http_build_query($options))
                                    );
                                    if (!count($data))
                                        continue;
                                    if ($data && $data[0]->value->text == strval($this->dataTrello["card"]["id"])) {
                                        $CARD = $card;
                                        break;
                                    }
                                }
                            }
                            if ($CARD) {
                                if (isset($this->dataTrello["card"]["new_name"])) {
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
                                        $html .= "––––––––––––––––––––––––";
                                        $url = "https://api.trello.com/1/cards/{$CARD->id}?fields=desc&key={$this->ApiKey}&token={$this->ApiToken}";
                                        $result = file_get_contents($url);
                                        $response = json_decode($result, true);
                                        $description = $response['desc'];
                                        $newText = preg_replace('/––––––––––––––––––––––––\n(.*)\n––––––––––––––––––––––––/s', $html, $description);
                                        preg_match('/––––––––––––––––––––––––\n(.*)\n––––––––––––––––––––––––/s', $description, $matches);
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
                                    Log::info('time', ["pos" => $response]);
                                }
                                return response()->json(['message' => 'Card has been update'], 200);
                            } else
                                return response()->json(['message' => 'Card not found'], 400);
                        }
                        return response()->json(['message' => 'Board Not Found'], 400);
                    }
                    return response()->json(['message' => 'Board Not Found'], 400);
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
