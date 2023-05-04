<?php

namespace App\Http\Controllers;

use App\Models\Boards;
use App\Models\trello_users;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function users()
    {
        $users = trello_users::get();
        return view("users", ["users" => $users]);
    }

    public function add(Request $request)
    {
        trello_users::create(
            [
                "trello_id" => $request->trello_id,
                "name" => $request->name,
                "tag" => $request->tag,
                "tg_username" => $request->tg_username,
                "key" => $request->key,
                "token" => $request->token,
            ]
        );

        return redirect()->route("users");
    }

    public function delete($id)
    {
        $user = trello_users::where(["trello_id" => $id]);
        $user->delete();
        return redirect()->route("users");
    }

    public function formEdit($id)
    {
        $user = trello_users::where(["trello_id" => $id])->first();
        return view("form", ["user" => $user, "title" => "Edit user " . $user->name, "action" => "edit"]);
    }


    public function edit(Request $request)
    {
        $user = trello_users::where(["trello_id" => $request->trello_id]);
        if (isset($user)) {
            $user->update(
                [
                    "trello_id" => $request->trello_id,
                    "name" => $request->name,
                    "tag" => $request->tag,
                    "tg_username" => $request->tg_username,
                    "key" => $request->key,
                    "token" => $request->token,
                ]
            );
            return redirect()->route("users");
        } else
            abort(404);
    }

    public function boards()
    {
        $boards = Boards::get();
        return view("boards", ["boards" => $boards]);
    }

    public function addBoard(Request $request)
    {
        Boards::create(
            [
                "board_id" => $request->board_id,
                "name" => $request->name,
            ]
        );

        return redirect()->route("boards");
    }

    public function deleteBoard($id)
    {
        $board = Boards::where(["board_id" => $id]);
        $board->delete();
        return redirect()->route("boards");
    }

}
