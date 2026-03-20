<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statistics</title>
    <!-- Latest compiled jQuery from Cloudflare's CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
            crossorigin="anonymous"></script>
</head>
<body>
<style>
    table {
        width: 100%;
    }

    th, td {
        text-align: center;
        vertical-align: middle;
    }

    .bold-border {
        border: 2px solid #000;
    }

    .project {
        cursor: pointer;
    }

    .project:hover {
        background-color: black;
        color: white;
        transition: background-color 0.5s ease;
    }
</style>
<div class="container mt-5">
    <h2>Statistics YDC by projects</h2>
    <div style="width: 30%; float: right" class="my-5">
        <form method="get" action="{{route("dashboard")}}">
            <div class="row">
                <div class="form-group col-9">
                    <input type="text" name="key" class="form-control" id="exampleInputEmail1" placeholder="Keyword">
                </div>
                <button type="submit" class="btn btn-primary col-3">Submit</button>
            </div>
        </form></div>

    <table class="table mt-3 table-bordered">
        <tbody>
        <tr class="bold-border">
            <th>Проект</th>
            <th class="m-0 p-0" colspan="3">
                <table class="table m-0">
                    <tr>
                        <th colspan="3">In progress</th>
                    </tr>
                    <tr>
                        <th>Факт.Часов</th>
                        <th>План.Часов</th>
                        <th>Факт/План</th>
                    </tr>
                </table>
            </th>
            <th>Ответсвенный</th>
        </tr>
        @foreach($projects as $item)
            <tr class="bold-border table-active">
                <th class="project" id="{{$item->id}}">{{$item->name}}<span class="point_{{$item->id}}" style="float: right;font-weight: bold;">+</span></th>
                <div class="row" style="max-width: 300px">
                    <th class="col-2">{{$item->date_fact_sum}}</th>
                    <th class="col-2">{{$item->date_plan_sum ? :0}}</th>
                    <th class="col-2"
                        @if($item->date_plan_sum && $item->date_fact_sum > $item->date_plan_sum && ($item->date_fact_sum / $item->date_plan_sum > 1 && $item->date_fact_sum / $item->date_plan_sum <= 1.5)) style="background-color: yellow; font-weight: bold;"
                        @endif
                        @if($item->date_plan_sum && $item->date_fact_sum > $item->date_plan_sum && $item->date_fact_sum / $item->date_plan_sum > 1.5) style="background-color: red; font-weight: bold;" @endif>{{$item->date_plan_sum && round($item->date_fact_sum / $item->date_plan_sum * 100 -100) != -100  ? round($item->date_fact_sum / $item->date_plan_sum * 100 -100) : 0}}
                        %
                    </th>
                </div>
                <th> - </th>
            </tr>
            @foreach($item->cards as $card)
                <tr class="cards_{{$item->id}} d-none bold-border">
                    <td style="word-wrap: break-word; max-width: 130px;">{{$card->name}}</td>
                    <div class="row" style="max-width: 300px">
                        <td class="col-2">{{$card->date_fact_sum}}</td>
                        <td class="col-2">{{$card->estimation ? :0}}</td>
                        <td class="col-2"
                            @if($card->estimation && $card->date_fact_sum > $card->estimation && ($card->date_fact_sum / $card->estimation > 1 && $card->date_fact_sum / $card->estimation <= 1.5)) style="background-color: yellow; font-weight: bold;"
                            @endif
                            @if($card->estimation && $card->date_fact_sum > $card->estimation && $card->date_fact_sum / $card->estimation > 1.5) style="background-color: red; font-weight: bold;" @endif>{{$card->estimation && round($card->date_fact_sum / $card->estimation * 100 -100) != -100  ? round($card->date_fact_sum / $card->estimation * 100 -100) : 0}}
                            %
                        </td>
                    </div>
{{--                    <td>{{$card->member}}</td>--}}
                    <td>+</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
</div>
<script>
    $(document).on("click", ".project", function (){
        let id = $(this).attr("id");
        if($(".cards_" + id).hasClass("d-none")){
            $(".cards_" + id).removeClass("d-none")
            $("point_" + id).text("-")
        }else {
            $(".cards_" + id).addClass("d-none")
            $("point_" + id).text("-")
        }
    })
</script>
</body>
</html>
