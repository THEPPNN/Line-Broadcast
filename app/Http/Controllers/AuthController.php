<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;

class AuthController
{
    public function login($email, $password)
    {
        $email = request('email');
        $password = request('password');

        $admin_password = config('services.line.admin_password');

        if ($email == 'admin@gmail.com' && $password == $admin_password) {
            // set session
            session(['user' => [
                'email' => $email,
                'id' => 1,
                'name' => 'Admin'
            ]]);
            return redirect()->route('dashboard');
        } else {
            return redirect()->route('login');
        }
    }

    public function logout()
    {
        session()->forget('user');
        return redirect()->route('login')->with('success', 'ออกจากระบบสำเร็จ');
    }
    
}
