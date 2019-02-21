<?php

namespace App\Http\Controllers;

use App\Board;
use App\Lesson;
use App\Profile;
use App\Proposal;
use App\Room;
use App\RoomToken;
use App\Step;
use App\Student;
use App\Teacher;
use App\Telegram\AuthorizeStudentStep;
use App\Telegram\AuthorizeTeacherStep;
use App\Telegram\DefaultStep;
use App\Telegram\MainRegisterStep;
use App\Telegram\StudentRegisterGradeStep;
use App\Telegram\StudentRegisterEmailStep;
use App\Telegram\StudentRegisterPhoneStep;
use App\Telegram\StudentWriteMaterialForTeacherStep;
use App\Telegram\TeacherOfflineStep;
use App\Telegram\TeacherOnlineStep;
use App\Telegram\TeacherRegisterGradeStep;
use App\Telegram\TeacherRegisterPhoneStep;
use App\Telegram\TeacherRegisterSubjectStep;
use App\Telegram\TeacherRegisterEmailStep;
use Illuminate\Http\Request;
use Sentinel;
use Telegram\Bot\Api;
use Telegram;
use Telegram\Bot\Keyboard\Keyboard;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VideoGrant;
use Twilio\Rest\Client;

class TelegramController extends Controller
{

    public function __construct()
    {
        $this->sid = config('services.twilio.sid');
        $this->token = config('services.twilio.token');
        $this->key = config('services.twilio.key');
        $this->secret = config('services.twilio.secret');
    }

    public function me()
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $response = $telegram->getMe();
        return $response;
    }

    public function updates()
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $response = $telegram->getUpdates();
        return $response;
    }

    public function respond()
    {

        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $response = $telegram->getUpdates();
        $request = collect(end($response));

        if (isset($request['message']['text'])) {
            $this->commandBot($request);

        }
    }

    public function webhook(Request $request)
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'), false);

        $updates = $telegram->getWebhookUpdate();

        try {

            if ($updates->isType('callback_query')) {
                $telegram->answerCallbackQuery([
                    'callback_query_id' => $updates->callbackQuery->id
                ]);

                $stepOfUser = Step::firstOrCreate(['telegram_id' => $updates->callbackQuery->from->id,
                    'telegram_id' => $updates->callbackQuery->from->id]);

                if ($stepOfUser->action == "teacherOnline") {

                    $id = $updates->callbackQuery->from->id;
                    $message = $updates->callbackQuery->data;

                    $parameters = explode(' ', $message);

                    if ($parameters[0] == "/accept") {

                        $lesson = Lesson::where('id', $parameters[1])
                            ->where('teacher_id', null)->first();

                        if ($lesson) {

                            $profile = Profile::where("telegram_id", $id)->first();

                            $proposal = Proposal::where('lesson_id', $lesson->id)
                                ->where('teacher_id', $profile->user->teacher->id)->first();

                            if (!$proposal) {

                                $telegram
                                    ->sendMessage([
                                        'chat_id' => $id,
                                        'text' => "Great, we are waiting for student response."
                                    ]);

                                Proposal::create([
                                    'lesson_id' => $lesson->id,
                                    'teacher_id' => $profile->user->teacher->id
                                ]);
                            } else {
                                $telegram
                                    ->sendMessage([
                                        'chat_id' => $id,
                                        'text' => "You have already submit request."
                                    ]);
                            }

                        } else {
                            $telegram
                                ->sendMessage([
                                    'chat_id' => $id,
                                    'text' => "Teacher was selected, or lesson not found"
                                ]);
                        }

                    } else if ($parameters[0] == "/decline") {
                        $telegram
                            ->sendMessage([
                                'chat_id' => $id,
                                'text' => "Ok"
                            ]);
                    }

                    return "Ok";

                } else if ($stepOfUser->action == "studentIsChooseTeacher") {

                    $id = $updates->callbackQuery->from->id;
                    $message = $updates->callbackQuery->data;

                    $parameters = explode(' ', $message);


                    if ($parameters[0] == "/select") {

                        $lesson = Lesson::where('id', $parameters[2])->where('teacher_id', null)->first();

                        if ($lesson) {
                            $lesson->teacher_id = $parameters[1];
                            $lesson->save();

                            $teacher = Teacher::where('id', $parameters[1])->first();

                            //Create lesson with board and video API

                            // Create aww whiteboard
                            $client = new \GuzzleHttp\Client();
                            $res = $client->request('POST', 'https://awwapp.com/api/v2/admin/boards/create', [
                                'form_params' => [
                                    'secret' => '9c2a03a7-b001-4337-b8de-5787058a290e',
                                    'domain' => 'https://dev.takweya.com/'
                                ]
                            ]);

                            $answer = json_decode($res->getBody(), true);

                            $board = Board::create([
                                'lesson_id' => $lesson->id,
                                'board_id' => $answer['board']['_id'],
                                'link' => $answer['board']['boardLink'],
                            ]);

                            //Create twilio API

                            $client = new Client($this->sid, $this->token);

                            $exists = $client->video->rooms->read(['uniqueName' => $board->link]);

                            if (empty($exists)) {
                                $twilioRoom = $client->video->rooms->create([
                                    'uniqueName' => $board->link,
                                    'type' => 'group-small',
                                    'recordParticipantsOnConnect' => false
                                ]);


                                \Log::debug("created new room: " . $twilioRoom->uniqueName);
                            }

                            $room = new Room();
                            $room->lesson_id = $lesson->id;
                            $room->room_id = $twilioRoom->sid;
                            $room->link = $twilioRoom->uniqueName;
                            $room->save();

                            \Log::debug("created eloquent room: " . $twilioRoom->uniqueName);

                            // CREATE TOKENS FOR USERS


                            //Teacher

                            $identity = $teacher->user->email;

                            $token = new AccessToken($this->sid, $this->key, $this->secret, 3600, $identity);

                            \Log::debug("created AccessToken");

                            $videoGrant = new VideoGrant();
                            $videoGrant->setRoom($room->room_id);

                            \Log::debug("created VideoGrant");

                            $token->addGrant($videoGrant);

                            \Log::debug("add Grant");

                            $roomToken = new RoomToken();
                            $roomToken->token = $token->toJWT();
                            $roomToken->user_id = $teacher->user->id;
                            $roomToken->lesson_id = $lesson->id;
                            $roomToken->save();

                            \Log::debug("create RoomToken");

                            //Student
                            $studentProfile = Profile::where('telegram_id', $id)->first();

                            \Log::debug("get student");


                            $identity = $studentProfile->user->email;

                            \Log::debug("get student email");

                            $token = new AccessToken($this->sid, $this->key, $this->secret, 3600, $identity);
                            \Log::debug("access token");

                            $videoGrant = new VideoGrant();
                            $videoGrant->setRoom($room->room_id);

                            \Log::debug("set room");

                            $token->addGrant($videoGrant);

                            \Log::debug("add grant");

                            $roomToken = new RoomToken();
                            $roomToken->token = $token->toJWT();
                            $roomToken->user_id = $studentProfile->user->id;
                            $roomToken->lesson_id = $lesson->id;
                            $roomToken->save();

                            \Log::debug("add room token student");


                            Telegram::sendMessage([
                                'chat_id' => $id,
                                'text' => "Great, here is a link for your online class https://dev.takweya.com/board?awwBoard=$board->link",
                            ]);

                            $teacher = Teacher::where('id', $parameters[1])->first();

                            Telegram::sendMessage([
                                'chat_id' => $teacher->user->profile->telegram_id,
                                'text' => "Here is a link your class is waiting https://dev.takweya.com/board?awwBoard=$board->link",
                            ]);


                            $proposals = $lesson->proposals()->get();

                            foreach ($proposals as $proposal) {

                                $teacher = Teacher::where('id', $proposal->teacher_id)->first();

                                if ($teacher->id != $parameters[1]) {
                                    Telegram::sendMessage([
                                        'chat_id' => $teacher->user->profile->telegram_id,
                                        'text' => "Student was start session with other teacher, sorry.",
                                    ]);
                                }
                            }

                            $stepOfUser->action = null;
                            $stepOfUser->save();
                        }
                    } else {

                        Telegram::sendMessage([
                            'chat_id' => $id,
                            'text' => "Please select teacher",
                        ]);
                    }

                }

                return "Ok";
            }

            if (isset($request['message']['text'])) {
                $this->commandBot($request);
                return "Ok";
            }

        } catch (\Exception $e) {

            if ($updates->isType('callback_query')) {

                $id = $updates->callbackQuery->from->id;

                Telegram::sendMessage([
                    'chat_id' => $id,
                    'text' => "You should answer until 15 seconds.",
                ]);

                return "Ok";
            }

            \Log::error($e->getMessage());
        }
    }

    public function showMessage($chatId, $message)
    {

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $message
        ]);
    }

    public function setWebHook()
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));

        $response = $telegram->setWebhook(['url' => env('APP_URL') . "/" . env('TELEGRAM_BOT_TOKEN') . "/webhook"]);

        return $response == true ? dd($response) : '';
    }

    public function removeWebhook()
    {
        $telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        echo $telegram->removeWebhook();
    }

    public function telegram($code)
    {
        return redirect('https://telegram.me/' . env('TELEGRAM_BOT_NAME') . '?start=' . $code);
    }

    public function getLink($chatId)
    {
        $inlineLayout = [
            [
                Keyboard::inlineButton(['text' => 'Teacher', 'callback_data' => 'teacher']),
                Keyboard::inlineButton(['text' => 'Student', 'callback_data' => 'student'])
            ]
        ];

        $reply_markup = Telegram\Bot\Keyboard\Keyboard::make([
            'keyboard' => $inlineLayout,
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Are you a student or teacher?',
            'reply_markup' => $reply_markup
        ]);

    }

    public function commandBot($request)
    {
        $stepOfUser = Step::firstOrCreate(['telegram_id' => $request['message']['chat']['id'],
            'telegram_id' => $request['message']['chat']['id']]);

        if ($stepOfUser->status != 'locked') {

            $text = $request['message']['text'];

            $parameters = explode(' ', $text);

            if ($parameters[0] == '/start') {
                $this->start($request, $stepOfUser);
            } else {
                $userStepCommand = $this->stepFactory($stepOfUser);
                $userStepCommand->execute($request, $stepOfUser);
            }
        }
        return "Ok";
    }

    public function stepFactory($stepOfUser)
    {
        switch ($stepOfUser->action) {
            case "mainRegisterStep":
                return new MainRegisterStep();
                break;
            case "teacherRegisterGradeStep":
                return new TeacherRegisterGradeStep();
                break;
            case "teacherRegisterSubjectStep":
                return new TeacherRegisterSubjectStep();
                break;
            case "teacherRegisterPhoneStep":
                return new TeacherRegisterPhoneStep();
                break;
            case "teacherRegisterEmailStep":
                return new TeacherRegisterEmailStep();
                break;
            case "studentRegisterGradeStep":
                return new StudentRegisterGradeStep();
                break;
            case "studentRegisterPhoneStep":
                return new StudentRegisterPhoneStep();
                break;
            case "studentRegisterEmailStep":
                return new StudentRegisterEmailStep();
                break;
            case "authorizeTeacher":
                return new AuthorizeTeacherStep();
                break;
            case "teacherOnline":
                return new TeacherOnlineStep();
                break;
            case "teacherOffline":
                return new TeacherOfflineStep();
                break;
            case "authorizeStudent":
                return new AuthorizeStudentStep();
                break;
            case "studentWriteMaterialForTeacher":
                return new StudentWriteMaterialForTeacherStep();
                break;
            default:
                return new DefaultStep();
                break;
        }
    }

    public function start($request, $stepOfUser)
    {
        $chatId = $request['message']['chat']['id'];

        $name = isset($request['message']['chat']['first_name']) ? $request['message']['chat']['first_name'] : "";
        $message = "Hello " . $name . "\nWelcome to Takweaya!";
        $this->showMessage($chatId, $message);

        $profile = Profile::where('telegram_id', $chatId)->first();

        if ($profile) {

            if ($profile->user->inRole("teacher")) {

                $message = "Please write /online when you are ready to be on the online list to get notifications. \nOnce you're done, send /offline so that you stop evening notification";

                $this->showMessage($chatId, $message);

                $stepOfUser->action = "authorizeTeacher";
                $stepOfUser->save();

                return "Ok";

            } else {

                $message = "What subject do you need help in? \nPlease type name of subject";

                $uniqueSubjects = $profile->user->student->grade->subjects;
//                $uniqueSubjects = DB::table('teachers')
//                    ->select('subject')
//                    ->groupBy('subject')
//                    ->get();

                foreach ($uniqueSubjects as $subject) {
                    $message .= "\n - " . $subject->name;
                }

                $this->showMessage($chatId, $message);

                $stepOfUser->action = "authorizeStudent";
                $stepOfUser->save();

                return "Ok";
            }
        } else {

            //Clear sessions
            if (\Session::has('teacher')) {
                \Session::forget('teacher');
            }
            if (\Session::has('student')) {
                \Session::forget('student');
            }

            $this->getLink($chatId);

            $stepOfUser->action = 'mainRegisterStep';
            $stepOfUser->save();

            return "Ok";

        }
    }
}
