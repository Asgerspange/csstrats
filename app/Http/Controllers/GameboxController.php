<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MailListing;
use App\Mail\Usercreate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GameboxController extends Controller
{
    public function show()
    {
        return view('gamebox');
    }

    public function register(Request $request)
    {
        // 1. Validate that we only receive a valid email
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->input('email');
        $password = Str::random(10);
        
        $user = User::where('email', $email)->first();

        // 2. Create the user if they don't exist
        if (!$user) {
            $user = User::create([
                'uuid' => (string) Str::uuid(),
                // Extracting the part before the '@' to serve as a generic name
                'name' => explode('@', $email)[0], 
                'email_verified_at' => now(),
                'email' => $email,
                'password' => Hash::make($password),
                'stripe_id' => null, // No Stripe session available from a simple form
                'auth_token' => bin2hex(openssl_random_pseudo_bytes(30)),
            ]);
            Mail::to($user->email)->send(new Usercreate($user, $password));

            
        }

        // 3. Create the MailListing entry
        MailListing::create([
            'user_id' => $user->id,
            'news' => 1,
        ]);

        // 4. Send the email

        // 5. Redirect back with a success message
        return back()->with('success', 'Successfully registered! Please check your email for your password.');
    }
}