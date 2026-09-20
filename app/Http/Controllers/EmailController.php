<?php

namespace App\Http\Controllers;

use App\Jobs\EmailSent;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function sendingEmail(Request $request)
    {
        // 1. Validate incoming request
        $validated = $request->validate([
            'email' => 'required|email'
        ]);

        // 2. Dispatch the EmailSent job with validated data
        EmailSent::dispatch($validated);

        // 3. Return success response
        return response()->json([
            'message' => "The email is sent to " . $validated['email'],
            'data' => $validated
        ], 200);
    }
}