<?php

namespace Andyabih\LaravelFakeApi\Http\Controllers;

use Andyabih\LaravelFakeApi\Facades\LaravelFakeApi;
use Andyabih\LaravelFakeApi\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LaravelFakeApiController extends Controller {
    public function get(Request $request, $endPoint, $key = NULL) {
        $response = LaravelFakeApi::generate($endPoint, $key, $request);
        return response()->json($response[0], $response[1]);
    }
}