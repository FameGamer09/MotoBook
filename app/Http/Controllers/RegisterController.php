<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RegisterController extends Controller
{
    public function sendData(){
        $data = [
            'first_name' => 'Ian Christopher',
            'last_name' => 'V. OLiva',
        ];
        
        $request = Http::withHeaders([
            'content-type' => 'application/json',
        ])->post(config('services.url.api_provider') . '/students', $data);

        return response()->json([
            'message' => 'Data sent successfully to the API provider.',
            'data' => $data
        ], 200);

    }
}
