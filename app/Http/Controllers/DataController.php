<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DataController extends Controller
{
    public function handleSerialization()
    {
        $php_array = [
            'student' => [
                'name' => 'Ian Christopher V. Oliva',
                'id'   => '23-1-0992',
            ],
            'program' => [
                'course' => 'BSIT',
                'year'   => '4',
            ],
        ];
    
        $jsonString = json_encode($php_array, JSON_PRETTY_PRINT);
        $reconstructedArray = json_decode($jsonString, true);
    
        dd($php_array, $jsonString, $reconstructedArray);
    }
}