<?php

namespace App\Http\Controllers\Server;

use App\Http\Controllers\Controller;
use Response;

class IndexController extends Controller
{
    public function index()
    {
        return Response::json(['code' => 0, 'message' => 'hello, stone server!']);
    }
}
