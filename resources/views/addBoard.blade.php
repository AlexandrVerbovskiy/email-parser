<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add board</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
            crossorigin="anonymous"></script>
</head>
<body>
<h3 class="m-4">Add board</h3>
<a href="{{route("boards")}}" class="btn btn-danger position-absolute mt-4 top-0" style="right: 3%">Cancel</a>
<div class="container col-6">
    <form method="post" enctype="multipart/form-data" action="{{route("add_board")}}">
        @csrf
        <div class="mb-3">
            <label for="board_id" class="form-label">Id</label>
            <input type="text" class="form-control" id="board_id" name="board_id"
                   value="{{old("board_id")}}" required>
        </div>
        @error("trello_id")
        <p class="text-red">{{$message}}</p>
        @enderror
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control" id="name" name="name" value="{{old("name")}}" required>
        </div>
        @error("name")
        <p class="text-red">{{$message}}</p>
        @enderror
        <button type="submit" class="btn btn-success">Submit</button>
    </form>
</div>
</body>
</html>

