<?php

namespace App\Http\Controllers;

class AuthController
{
    public function login($email, $password)
    {
        $email = request('email');
        $password = request('password');
        if ($email == 'admin@gmail.com' && $password == '123456') {
            return redirect()->route('dashboard');
        } else {
            return redirect()->route('login');
        }
    }
}
