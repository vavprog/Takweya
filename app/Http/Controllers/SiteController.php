<?php

namespace App\Http\Controllers;

use App\Board;
use App\UserEmail;
use Sentinel;
use Illuminate\Http\Request;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VideoGrant;
use Twilio\Rest\Client;

class SiteController extends Controller
{
    public function index()
    {
        return view('site.index');
    }

    public function saveEmail(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|string|email|max:255'
        ]);

        UserEmail::create($request->all());

        return redirect('/');
    }
}
