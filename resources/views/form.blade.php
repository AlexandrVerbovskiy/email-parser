<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{$title}}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
            crossorigin="anonymous"></script>
</head>
<body>
<h3 class="m-4">{{$title}}</h3>
<a href="{{route("users")}}" class="btn btn-danger position-absolute mt-4 top-0" style="right: 3%">Cancel</a>
<div class="container col-6">
    <form method="post" enctype="multipart/form-data" action="{{route($action, isset($params) ?? $params)}}">
        @csrf
        <div class="mb-3">
            <label for="trello_id" class="form-label">Trello Id</label>
            <input type="text" class="form-control" id="trello_id" name="trello_id"
                   value="{{ isset($user) ? $user->trello_id : old("trello_id") }}" required>
        </div>
        @error("trello_id")
        <p class="text-red">{{$message}}</p>
        @enderror
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ isset($user) ? $user->name : old("name") }}" required>
        </div>
        @error("name")
        <p class="text-red">{{$message}}</p>
        @enderror
        <div class="mb-3">
            <label for="tag" class="form-label">Tag</label>
            <input type="text" class="form-control" id="tag" name="tag" value="{{ isset($user) ? $user->tag : old("tag") }}" required>
        </div>
        @error("tag")
        <p class="text-red">{{$message}}</p>
        @enderror
        <div class="mb-3">
            <label for="tg_username" class="form-label">Tg Username</label>
            <input type="text" class="form-control" id="tg_username" name="tg_username" value="{{ isset($user) ? $user->tg_username : old("tg_username") }}" required>
        </div>
        @error("tg_username")
        <p class="text-red">{{$message}}</p>
        @enderror
        <div class="mb-3">
            <label for="key" class="form-label">Key</label>
            <input type="text" class="form-control" id="key" name="key" value="{{ isset($user) ? $user->key : old("key") }}" required>
        </div>
        @error("key")
        <p class="text-red">{{$message}}</p>
        @enderror
        <div class="mb-3">
            <label for="token" class="form-label">Token</label>
            <input type="text" class="form-control" id="token" name="token" value="{{ isset($user) ? $user->token : old("token") }}" required>
        </div>
        @error("token")
        <p class="text-red">{{$message}}</p>
        @enderror
        <button type="submit" class="btn btn-success">Submit</button>
    </form>
</div>
</body>
</html>
