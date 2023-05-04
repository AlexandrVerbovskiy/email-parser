<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
            crossorigin="anonymous"></script>
</head>
<body>
<h3 class="m-4">Users</h3>
<a href="{{route("addUser")}}" class="btn btn-info position-absolute mt-4 top-0" style="right: 3%">Add</a>
<div class="row">
    <div class="container col-11">
        <table class="table">
            <tr>
                <th scope="col">Id Trello</th>
                <th scope="col">Name</th>
                <th scope="col">Tag</th>
                <th scope="col">Key</th>
                <th scope="col">Tg Username</th>
            </tr>
            @foreach($users as $user)
                <tr>
                    <td class="col-2">
                        {{$user->trello_id}}
                    </td>
                    <td class="col-1">
                        {{$user->name}}
                    </td>
                    <td class="col-1">
                        {{$user->tag}}
                    </td>
                    <td class="col-2">
                        {{$user->key}}
                    </td>
                    <td class="col-5">
                        {{$user->tg_username}}
                        <a href="{{route("form_edit", ["id" => $user->trello_id])}}"
                           class="btn btn-warning mx-4">Edit</a>
                        <a href="{{route("deleteUser", ["id" => $user->trello_id])}}" class="btn btn-danger">Delete</a>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
</div>

</body>
</html>
