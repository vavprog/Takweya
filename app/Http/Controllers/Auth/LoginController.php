<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Sentinel;
use Cartalyst\Sentinel\Checkpoints\ThrottlingException;
use Cartalyst\Sentinel\Checkpoints\NotActivatedException;

class LoginController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function postLogin(Request $request)
    {
        $this->validate($request, [
            'email' => 'required',
            'password' => 'required',
        ]);

        try {
            $remember = false;

            if (isset($request->remember)) {
                $remember = true;
            }

            if (Sentinel::authenticate($request->all(), $remember)) {
                return redirect('/');
            } else {
                return redirect()->back()->with(['error' => 'Wrong credentials.']);
            }
        } catch (ThrottlingException $e) {
            $delay = $e->getDelay();
            return redirect()->back()->with(['error' => "You are banned for $delay seconds"]);
        } catch (NotActivatedException $e) {
            return redirect()->back()->with(['error' => "Your account is not activated!"]);
        }
    }

    public function logout()
    {
        Sentinel::logout();

        return redirect('/login');
    }
}
