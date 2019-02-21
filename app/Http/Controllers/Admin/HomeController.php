<?php

namespace App\Http\Controllers\Admin;

use App\Job;
use App\User;
use App\UserRequest;
use DateTime;
use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    const NUMBER_OF_MONTHS = 6;

    public function index()
    {
        $usersCount = 0;
        $jobsCount = 0;
        $requestsCount = 0;
        $recentlyUsers = [];
        $chartMessages = [];

        return view('admin.home.index', compact('usersCount', 'jobsCount', 'recentlyUsers', 'requestsCount', 'chartMessages'));
    }

    /**
     * Generate chart statistics
     * @return array
     */
    private function getChartMessages()
    {
        $chartMessages = [];
        for ($i = 0; $i <= self::NUMBER_OF_MONTHS; $i++) {
            $chartMessages[$i]['month'] = date("m", mktime(0, 0, 0, date("m")-$i, 1, date("Y")));
            $dateObj = DateTime::createFromFormat('!m', $chartMessages[$i]['month']);
            $chartMessages[$i]['monthName'] = $dateObj->format('F');
            $chartMessages[$i]['year'] = date('Y', strtotime(date('Y-m-d') . " -$i month"));
            $chartMessages[$i]['count'] = UserRequest::whereMonth('created_at', $chartMessages[$i]['month'])
                ->whereYear('created_at', $chartMessages[$i]['year'])->count();
        }
        $chartMessages = array_reverse($chartMessages);

        return $chartMessages;
    }
}
