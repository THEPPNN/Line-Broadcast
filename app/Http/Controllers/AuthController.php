<?php

namespace App\Http\Controllers;

class AuthController
{
    public function login($email, $password)
    {
        $email = request('email');
        $password = request('password');
        if ($email == 'admin@gmail.com' && $password == '123456') {
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
