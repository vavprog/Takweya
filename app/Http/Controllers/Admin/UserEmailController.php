<?php

namespace App\Http\Controllers\Admin;

use App\UserEmail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserEmailController extends Controller
{
    public function index()
    {
        $userEmails = UserEmail::get();

    }
}
