<?php

namespace App\Http\Controllers\User;

use App\Board;
use App\Http\Controllers\Controller;
use App\Lesson;
use App\ReferralTelegramUser;
use App\User;
use Sentinel;
use Illuminate\Http\Request;
use Twilio\Rest\Client;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VideoGrant;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->sid = config('services.twilio.sid');
        $this->token = config('services.twilio.token');
        $this->key = config('services.twilio.key');
        $this->secret = config('services.twilio.secret');
    }

    public function index()
    {
        $currentUser = User::where('id', Sentinel::getUser()->id)->firstOrFail();

        $user = null;

        if ($currentUser->inRole('teacher')) {
            $user = $currentUser->teacher;
        } else {
            $user = $currentUser->student;
        }

        $historyLessons = $user->lessons()->active()
            ->orderByDesc('created_at')->limit(3)->get();

        return view('user.home.index', compact('historyLessons'));
    }

    public function board(Request $request)
    {
        $link = $request->input('awwBoard');

        if (!$link) {
            abort(404);
        }

        \Log::debug("GET link: ");

        $board = Board::where('link', $request->input('awwBoard'))->firstOrFail();

        \Log::debug("GET board: " . $board->id);

        $lesson = $board->lesson()->where('is_completed', 0)->firstOrFail();

        \Log::debug("GET lesson: " . $lesson->id);

        $currentUser = \App\User::where('id', Sentinel::getUser()->id)->firstOrFail();

        \Log::debug("GET currentUser: ");

        if ($currentUser->inRole('student')) {
            \Log::debug("GET current student number: " . $currentUser->student->id);
            \Log::debug("GET lesson number student: " . $lesson->student_id);
            if ($currentUser->student->id != $lesson->student_id) {
                \Log::debug("GET error student: ");
                abort(404);
            }
        } else {
            if ($currentUser->teacher->id != $lesson->teacher_id) {
                \Log::debug("GET error teacher: ");
                abort(404);
            }
        }

        \Log::debug("GET current user: ");

//        $client = new Client($this->sid, $this->token);

//        $exists = $client->video->rooms->read([ 'uniqueName' => 'test1']);

//        if (empty($exists)) {
//            $client->video->rooms->create([
//                'uniqueName' => 'test1',
//                'type' => 'group-small',
//                'recordParticipantsOnConnect' => false
//            ]);
//
//
//            \Log::debug("created new room: ". 'test');
//        }

//        $room = $lesson->room->firstOrFail();
//
//        $identity = $currentUser->first_name;
//
//        \Log::debug("joined with identity: $identity");
//
//        $token = new AccessToken($this->sid, $this->key, $this->secret, 3600, $identity);
//
//        $videoGrant = new VideoGrant();
//        $videoGrant->setRoom($room->room_id);
//
//        $token->addGrant($videoGrant);

        $token = $currentUser->roomTokens->where('lesson_id', $lesson->id)->first();

        \Log::debug("GET token: ");

        return view('user.home.board', [
            'board' => $board,
            'accessToken' => $token->token,
            'roomName' => $lesson->room->link,
            'userName' => $currentUser->email,
            'lesson' => $lesson
        ]);
    }

    public function finishLesson(Request $request)
    {
        $lesson = Lesson::where('id', $request->input('lesson_id'))
                            ->where('is_completed', 0)
                            ->firstOrFail();

        $currentUser = \App\User::where('id', Sentinel::getUser()->id)->firstOrFail();

        if ($currentUser->inRole('student')) {
            if ($currentUser->student->id != $lesson->student_id) {
                abort(404);
            }
        } else {
            abort(404);
        }

        $lesson->is_completed = 1;
        $lesson->save();

        // Delete whiteboard
        $client = new \GuzzleHttp\Client();
        $res = $client->request('DELETE', "https://awwapp.com/api/v2/admin/boards/{$lesson->board->board_id}/delete", [
            'form_params' => [
                'secret' => '9c2a03a7-b001-4337-b8de-5787058a290e',
                'domain' => 'https://develop.takweya.com/'
            ]
        ]);

//        $lesson->board->delete();

        $client = new Client($this->sid, $this->token);

        $exists = $client->video->rooms->read(['uniqueName' => $lesson->room->link]);

        if ($exists) {

            $client->video->v1->rooms($lesson->room->room_id)->update('completed');

        }

        return redirect('/edit-profile');

    }
}
