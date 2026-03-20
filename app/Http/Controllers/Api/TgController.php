<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\tg_users;
use App\Models\Card;
use App\Models\TgSubtask;
use App\Models\Board;
use App\Models\Project;
use App\Models\Milestone;
use App\Models\Release;
use App\Models\Vote;
use App\Models\trello_users;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;

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

    function parseComment($text)
    {
        $replace_callback = function ($matches) {
            switch ($matches[0]) {
                case "@vn":
                {
                    $replace = "@educationphp7";
                    break;
                }
                case "@pm":
                {
                    $replace = "@user12779792";
                    break;
                }
                case "@rp":
                {
                    $replace = "@romanpaz1uk";
                    break;
                }
                case "@vs":
                {
                    $replace = "@user13409428";
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
                case "@jl":
                {
                    $replace = "@julia75498585";
                    break;
                }
                case "@rl":
                {
                    $replace = "@litvinroman2009";
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

    public function setEstByFibonachi($num)
    {
        $est = 0;
        switch ($num) {
            case 1:
            {
                $est = 0.5;
                break;
            }
            case 3:
            {
                $est = 1;
                break;
            }
            case 5:
            {
                $est = 1.5;
                break;
            }
            case 8:
            {
                $est = 2;
                break;
            }
            case 13:
            {
                $est = 2.5;
                break;
            }
            case 21:
            {
                $est = 3;
                break;
            }
            case 34:
            {
                $est = 5;
                break;
            }
            case 89:
            {
                $est = 8;
                break;
            }
        }
        return $est;
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
        if (isset($this->data["callback_query"])) {
            $callbackData = $this->data["callback_query"]["data"];
            $this->chat_id = $this->data["callback_query"]["from"]["id"];
            Log::info("data", ["data" => $callbackData]);
            if (str_starts_with($callbackData, "subtask_")) {
                $idSubtask = explode("_", $callbackData)[1];
                $subtask = TgSubtask::where("id", $idSubtask)->first();
                if ($subtask) {
                    if (isset(explode("_", $callbackData)[2])) {
                        $str = explode("_", $callbackData)[2];
                        $field = explode("=", $str)[0];
                        $value = explode("=", $str)[1];
                        $subtask[$field] = $value;
                        $subtask->save();
                    }
                    if (!$subtask->chat_id) {
                        $subtask->chat_id = $this->chat_id;
                        $subtask->save();
                    }
                    if (!$subtask->board) {
                        $boards = Board::where("name", "LIKE", $subtask->client . "%")->get();
                        if (count($boards) == 1) {
                            $subtask->board = $boards[0]->name;
                            $subtask->save();
                        } else {
                            $keyboard = [];
                            foreach ($boards as $board) {
                                $keyboard [] = [
                                    ["text" => $board->name, "callback_data" => "subtask_" . $idSubtask . "_board=" . $board->name]
                                ];
                            }
                            $text = "Choose board for clients " . $subtask->client . ":";
                            $this->sendTaskMessage($this->chat_id, $text, $keyboard);
                            return true;
                        }
                    }
                    if (!$subtask->project) {
                        $board = Board::where("name", $subtask->board)->first();
                        $projects = Project::has('cards')->where("board_id", $board->id)->get();
                        if (count($projects) == 1) {
                            $subtask->project = $projects[0]->text;
                            $subtask->save();
                        } else {
                            $keyboard = [];
                            foreach ($projects as $project) {
                                $keyboard [] = [
                                    ["text" => $project->text, "callback_data" => "subtask_" . $idSubtask . "_project=" . $project->text]
                                ];
                            }
                            $text = "Choose project for board " . $board->name . ":";
                            $this->sendTaskMessage($this->chat_id, $text, $keyboard);
                            return true;
                        }
                    }
                    if (!$subtask->milestone) {
                        $project = Project::where("text", $subtask->project)->first();
                        $milestones = Milestone::has('cards')->where("project_id", $project->id)->get();
                        if (count($milestones) == 1) {
                            $subtask->milestone = $milestones[0]->text;
                            $subtask->save();
                        } else {
                            $keyboard = [];
                            foreach ($milestones as $milestone) {
                                $keyboard [] = [
                                    ["text" => $milestone->text, "callback_data" => "subtask_" . $idSubtask . "_milestone=" . $milestone->text]
                                ];
                            }
                            $text = "Choose milestone for project " . $project->text . ":";
                            $this->sendTaskMessage($this->chat_id, $text, $keyboard);
                            return true;
                        }
                    }
                    if (!$subtask->release) {
                        $subtask->release = "Week - " . Carbon::now()->weekOfYear;
                        $subtask->save();
                    }
                    if (!$subtask->column) {
                        $keyboard = [[
                            ["text" => "Wait list", "callback_data" => "subtask_" . $idSubtask . "_column=Wait list"],
                            ["text" => "Bugs", "callback_data" => "subtask_" . $idSubtask . "_column=Bugs"],
                            ["text" => "In progress", "callback_data" => "subtask_" . $idSubtask . "_column=In progress"]
                        ]];
                        $text = "Choose column:";
                        $this->sendTaskMessage($this->chat_id, $text, $keyboard);
                        return true;
                    }
                    if (!$subtask->priority) {
                        $keyboard = [[
                            ["text" => "1", "callback_data" => "subtask_" . $idSubtask . "_priority=1"],
                            ["text" => "2", "callback_data" => "subtask_" . $idSubtask . "_priority=2"],
                            ["text" => "3", "callback_data" => "subtask_" . $idSubtask . "_priority=3"],
                            ["text" => "4", "callback_data" => "subtask_" . $idSubtask . "_priority=4"],
                            ["text" => "5", "callback_data" => "subtask_" . $idSubtask . "_priority=5"]
                        ]];
                        $text = "Choose priority:";
                        $this->sendTaskMessage($this->chat_id, $text, $keyboard);
                        return true;
                    }
                    if (!$subtask->part) {
                        $keyboard = [
                            [
                                ["text" => "Backend", "callback_data" => "subtask_" . $idSubtask . "_part=B"],
                                ["text" => "Frontend", "callback_data" => "subtask_" . $idSubtask . "_part=F"],
                                ["text" => "Design", "callback_data" => "subtask_" . $idSubtask . "_part=D"]
                            ],
                            [
                                ["text" => "Testing", "callback_data" => "subtask_" . $idSubtask . "_part=T"],
                                ["text" => "Estimation", "callback_data" => "subtask_" . $idSubtask . "_part=E"],
                                ["text" => "Meeting", "callback_data" => "subtask_" . $idSubtask . "_part=M"],
                                ["text" => "Research", "callback_data" => "subtask_" . $idSubtask . "_part=R"]
                            ]
                        ];
                        $text = "Choose part:";
                        $this->sendTaskMessage($this->chat_id, $text, $keyboard);
                        return true;
                    }
                    if (!$subtask->typeestim) {
                        $keyboard = [
                            [["text" => "Payed by client", "callback_data" => "subtask_" . $idSubtask . "_typeestim=Estim"]],
                            [["text" => "Internal Projects", "callback_data" => "subtask_" . $idSubtask . "_typeestim=EstimIP"]],
                            [["text" => "Client is not paid", "callback_data" => "subtask_" . $idSubtask . "_typeestim=Extra"]],
                        ];
                        $text = "Choose type estimation:";
                        $this->sendTaskMessage($this->chat_id, $text, $keyboard);
                        return true;
                    }
                    if (!$subtask->estim) {
                        $keyboard = [
                            [
                                ["text" => "0.5", "callback_data" => "subtask_" . $idSubtask . "_estim=30"],
                                ["text" => "1", "callback_data" => "subtask_" . $idSubtask . "_estim=60"],
                                ["text" => "1.5", "callback_data" => "subtask_" . $idSubtask . "_estim=90"]
                            ],
                            [
                                ["text" => "2", "callback_data" => "subtask_" . $idSubtask . "_estim=120"],
                                ["text" => "2.5", "callback_data" => "subtask_" . $idSubtask . "_estim=180"],
                                ["text" => "3", "callback_data" => "subtask_" . $idSubtask . "_estim=240"],
                            ]
                        ];
                        $text = "Choose hours estimation:";
                        $this->sendTaskMessage($this->chat_id, $text, $keyboard);
                        return true;
                    }
                    if (!$subtask->member) {
                        $keyboard = [
                            [
                                ["text" => "Pavlo Melnyk", "callback_data" => "subtask_" . $idSubtask . "_member=pm"],
                                ["text" => "Alex Verbovskiy", "callback_data" => "subtask_" . $idSubtask . "_member=av"],
                                ["text" => "Inna Pogrebna", "callback_data" => "subtask_" . $idSubtask . "_member=ip"]
                            ],
                            [
                                ["text" => "Valerii Nuzhnyi", "callback_data" => "subtask_" . $idSubtask . "_member=vn"],
                                ["text" => "Kostyuk Vitaly", "callback_data" => "subtask_" . $idSubtask . "_member=vk"],
                                ["text" => "Igor Dzhenkov", "callback_data" => "subtask_" . $idSubtask . "_member=id"],
                            ]
                        ];
                        $text = "Choose member:";
                        $this->sendTaskMessage($this->chat_id, $text, $keyboard);
                        return true;
                    }
                    $nameArrLink = explode(" | ", $subtask->board);
                    $nameLink = $nameArrLink[0] == "IP" || !isset($nameArrLink[2]) ? "IP" : $nameArrLink[0] . " - " . $nameArrLink[2];
                    $workflowLink = "https://projects.dev.yeducoders.com/" . $nameLink . ".html";
                    $hash = hash('sha256', Carbon::now()->toDateTimeString());
                    $start = rand(0, strlen($hash) - 10);
                    $random_characters = substr($hash, $start, 10);
                    $customId = 'SUB' . $random_characters;
                    $obj = [
                        "board_name" => $subtask->board,
                        "html_link" => $workflowLink,
                        "card" => [
                            "id" => $customId,
                            "name" => $subtask->name,
                            "estimation" => $subtask->typeestim . " " . $subtask->estim,
                            "label" => $subtask->priority,
                            "part" => $subtask->part,
                            "milestone" => $subtask->milestone,
                            "release" => $subtask->release,
                            "project" => $subtask->project,
                            "start_date" => $subtask->start_date,
                            "due_date" => $subtask->due_date,
                            "description" => [
                                "links" => [],
                                "notes" => []
                            ]
                        ],
                        "members" => [
                            ["name" => $subtask->member]
                        ],
                        "action" => [
                            "display" => [
                                "translationKey" => "card_trello"
                            ]
                        ],
                        "type" => $subtask->column
                    ];
                    $controller = new TrelloController();
                    $response = $controller->sendMessage("sendMessage", true, $obj);
                    if ($response->getStatusCode() == 200) {
                        $this->sendTaskMessage($this->chat_id, "Task has been created success");
                        $subtask->delete();
                        return true;
                    }
                }
            } else {
                list($vote, $choice) = explode("_", $callbackData);
                $vote = Vote::where("id", $vote)->first();
                $user = tg_users::where("chat_id", $this->chat_id)->first();
                $trelloUser = trello_users::where('tg_username', $user->name)->first();
                $data = json_decode($vote->data, true);
                $data[$trelloUser->name] = (double)$choice;
                $vote->data = $data;
                $sum = 0;
                $count = 0;
                foreach ($data as $key => $value) {
                    $tmp = $this->setEstByFibonachi((double)$value);
                    if ($tmp)
                        $count++;
                    $sum += $tmp;
                }
                $middle = round($sum / $count, 2);
                $vote->value = $middle;
                $vote->save();
                $params = [
                    'text' => "<b>Thanks</b>
<b>your choice for vote #{$vote->id} has been counted</b>
",
                    'chat_id' => $this->chat_id,
                    'parse_mode' => 'HTML'
                ];
                $res = json_decode(
                    file_get_contents($this->base_url . "sendMessage?" . http_build_query($params)),
                    JSON_OBJECT_AS_ARRAY
                );
            }
        } elseif (isset($this->data["message"]["text"])) {
            $mes = $this->data["message"]["text"];
            if (isset($this->data["message"]["reply_to_message"]) && $this->data["message"]["reply_to_message"]["text"] != "" && $mes != "/start") {
                $this->chat_id = $this->data["message"]["chat"]["id"];
                if (str_contains($this->data["message"]["reply_to_message"]["text"], 'Vote #')) {
                    preg_match('/Vote #(\d+)/', $this->data["message"]["reply_to_message"]["text"], $matches);
                    if (isset($matches[1])) {
                        $vote = Vote::where("id", $matches[1])->first();
                        $user = tg_users::where("chat_id", $this->chat_id)->first();
                        $trelloUser = trello_users::where('tg_username', $user->name)->first();
                        $data = json_decode($vote->data, true);
                        $data[$trelloUser->name] = (double)$mes;
//                        $flag = true;
//                        foreach ($data as $key => $value){
//                            if ($value == null)
//                                $flag = false;
//                        }
                        $vote->data = $data;
//                        if ($flag){
                        $sum = 0;
                        $count = 0;
                        foreach ($data as $key => $value) {
                            $tmp = $this->setEstByFibonachi((double)$value);
                            if ($tmp)
                                $count++;
                            $sum += $tmp;
                        }
                        $middle = round($sum / $count, 2);
                        $vote->value = $middle;
//                        }
                        $vote->save();
                    }
                } else {
                    preg_match('/(Card Id: \w+)/', $this->data["message"]["reply_to_message"]["text"], $matches);
                    preg_match('/(BY \w+ \w+)/', $this->data["message"]["reply_to_message"]["text"], $userTrello);
                    $userTrello = str_replace(["BY ", "(", ")"], "", $userTrello);
                    $userTrello = trello_users::where("name", $userTrello)->first();
                    $pattern_name = '/@\w+/';
                    $isMatched = preg_match_all($pattern_name, $mes, $username);
                    if ($userTrello && $userTrello->name == "Client YDC") {
                        if ($this->chat_id == "5548342573") {
                            preg_match('/ADDED COMMENT(?: @\w+)?\s+\[(.*?)\]/', $this->data["message"]["reply_to_message"]["text"], $user_id);
                            if (isset($user_id[1])) {
                                $mes = "[" . $user_id[1] . "] " . $mes;
                            }
                        } else {
                            $url = $this->base_url . $method . "?" . http_build_query(["chat_id" => $this->chat_id, "text" => "To make answer to client - use workflow tree"]);
                            return json_decode(
                                file_get_contents($url),
                                JSON_OBJECT_AS_ARRAY
                            );
                        }
                    }
                    if ($isMatched) {
                        $mes = $this->parseComment($mes);
                    }
                    if (!str_contains($mes, "***"))
                        $mes = $userTrello->tag . " " . $mes;
                    else
                        $mes = str_replace("***", "", $mes);
                    $user = trello_users::where("tg_username", $this->data["message"]["from"]["username"])->first();
                    if (isset($matches[0])) {
                        $cardId = $matches[0];
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
                    if (isset($this->data["message"]["from"]["username"])) {
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
                }
            }
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


    function vote(Request $request)
    {
        $id = $request->input("id");
        $card = Card::where("task_id", $id)->first();
        $project = Project::where("id", $card->project_id)->first();
        $part = $request->input("type");
        if ($part == "Frontend") {
            $members = trello_users::where("pm", "Igor Dzhenkov")->whereIn("role", ['Front Dev', 'Full Stack Dev'])->get();
        } elseif ($part == "Backend") {
            $members = trello_users::where("pm", "Igor Dzhenkov")->where("role", 'Full Stack Dev')->get();
        } else {
            $members = trello_users::where("pm", "Igor Dzhenkov")->get();
        }
        $members = trello_users::where("name", "Pavlo Melnyk")->get();
        $vote = Vote::updateOrCreate(["card_id" => $card->id], ['data' => null, 'value' => null]);
        $data = [];
        foreach ($members as $member) {
            $keyboard = [
                [
                    ["text" => "1 (0.5h)", "callback_data" => $vote->id . "_1"]
                ],
                [
                    ["text" => "3 (1h)", "callback_data" => $vote->id . "_3"]
                ],
                [
                    ["text" => "5 (1.5h)", "callback_data" => $vote->id . "_5"]
                ],
                [
                    ["text" => "8 (2h)", "callback_data" => $vote->id . "_8"]
                ],
                [
                    ["text" => "13 (2.5h)", "callback_data" => $vote->id . "_13"]
                ],
                [
                    ["text" => "21 (3h)", "callback_data" => $vote->id . "_21"]
                ],
                [
                    ["text" => "34 (5h)", "callback_data" => $vote->id . "_34"]
                ]
            ];
            $encodedKeyboard = json_encode([
                "inline_keyboard" => $keyboard,
                "resize_keyboard" => true,
                "one_time_keyboard" => true
            ]);

            $data[$member->name] = null;
            $user = tg_users::where('name', $member->tg_username)->first();
            $params = [
                'text' => "<b>Vote #{$vote->id}</b>
<b>For task name: </b> {$card->name}
<b>Of the project: </b> {$project->text}

<b>Please give Your estimation in Fibonacci numbers at the bottom of this message</b>
<b>PMD link: </b> {$card->pmd_link}
<b>Trello link: </b> {$card->link}
",
                'chat_id' => $user->chat_id,
                'reply_markup' => $encodedKeyboard,
                'parse_mode' => 'HTML'
            ];
            json_decode(
                file_get_contents($this->base_url . "sendMessage?" . http_build_query($params)),
                JSON_OBJECT_AS_ARRAY
            );
        }
        $vote->data = $data;
        $vote->save();
    }

    public function sendTaskMessage($chat_id, $text, $keyboard = null)
    {

        $encodedKeyboard = [
            "inline_keyboard" => $keyboard
        ];
        if ($keyboard) {
            $params = [
                'text' => $text,
                'chat_id' => $chat_id,
                'reply_markup' => json_encode($encodedKeyboard),
            ];
        }else{
            $params = [
                'text' => $text,
                'chat_id' => $chat_id,
            ];
        }
        $ch = curl_init($this->base_url . "sendMessage");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $response = curl_exec($ch);
        Log::info("res", ["res" => $response]);

        curl_close($ch);

    }
}
