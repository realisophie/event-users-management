<?php

namespace App\Http\Controllers\Auth;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Exports\GuestsExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\RedirectResponse;
use Twilio\Rest\Client as TwilioClient;
use App\Notifications\GuestNotification;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Validation\ValidationException;
use App\Notifications\GuestRejectedNotification;
use App\Notifications\GuestAcceptedNotification;
use App\Notifications\MeetingUpdateNotification;
use DB;
use Carbon\Carbon;

class EventController extends Controller
{
    private $client;
    private $twilioClient;

    public function __construct(Client $client)
    {
        $this->middleware('checkevent')->only('create', 'store', 'duplicate');
        $this->client = $client;
        $account_sid = config("services.twilio.sid");
        $auth_token = config("services.twilio.token");
        $this->twilioClient = new TwilioClient($account_sid, $auth_token);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        $zoomMeetings = $user->zoomMeetings()->whereCancelled(0)->withCount('meetingInvitations')->orderBy('start_time', 'desc')->get();
        return view('eventmanager.event.index', compact('zoomMeetings'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('eventmanager.event.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $v = \Validator::make($request->all(), [
            'topic' => 'required|max:190',
            'agenda' => 'required|max:2000',
            'public_url' => 'required|unique:zoom_meetings|unique:profiles|not_in:pricing,blog,terms,privacy,zoomintegration,support,contact,help,cron',
            'start_time' => 'required|date|after_or_equal:now',
			'time_zone'	=> 'required',
            'price' => 'required|numeric',
            "hours" => 'nullable|numeric',
            "minutes" => 'nullable|numeric|min:0|max:60',
            "private" => 'nullable|boolean',
            "require_registration" => 'nullable|boolean',
            "require_approval" => 'nullable|boolean',
            "notify_guest_register" => 'nullable|boolean',
            "location" => 'nullable|string',
            "number_of_tickets" => 'nullable|numeric',
            'image' => 'nullable|string',
            'question.*' => 'required',
            'answer.*' => 'required',
            'speakername.*' => 'required',
            'speakerbio.*' => 'nullable|string',
            'hdn_duplicate_backgound_cover' => 'nullable',
            // 'question.*' => 'required|alpha_spaces_question_mark',
            // 'answer.*' => 'required|alpha_numeric_spaces_question_mark',
            // 'speakername.*' => 'required|alpha_spaces',
            // 'speakerbio.*' => 'required'
        ],[
            'minutes.max' => 'Minutes must not be greater than 60',
            'minutes.numeric' => 'Minutes must be a number',
            'minutes.min' => 'Minutes must be at least 0'
        ]);
        $v->sometimes('minutes', 'required', function ($input) {
            $hours = $input->hours;
            if (!$hours) {
                return true;
            } else {
                return false;
            }
        });
        $v->sometimes('price', 'min:1', function ($input) {
            if ($input->price > 0 && $input->price < 1) {
                return true;
            } else {
                return false;
            }
        });
        $v->sometimes('invite_link', 'required|url', function ($input) {
            if (auth()->user()->zoomtoken) {
                return $input->manually == 1;
            } else {
                return true;
            }
        });
        $data = $v->validate();
        if (!auth()->user()->stripeToken && $data['price'] > 0) {
            return redirect()->back()->withInput()->with([
                'type' => 'warning',
                'title' => 'Fullfill Requirements',
                'message' => 'You need to connect stripe account to create paid events.'
            ]);
        }
        if (auth()->user()->zoomToken) {
            if ($data['hours'] || $data['minutes']) {
                $data['duration'] = 0;
            }
            if ($data['hours']) {
                $data['duration'] = $data['hours'] * 60;
            }
            if ($data['minutes']) {
                $data['duration'] += $data['minutes'];
            }
        }
		//config(['app.timezone' => $data['time_zone']]);
        $time_zone = $data['time_zone'];
		$data['timezone'] = $data['time_zone'];
		$start_time = $data['start_time'] . ':00';
        $data['start_time'] = str_replace(' ', 'T', $data['start_time']);
        $data['start_time'] = $data['start_time'] . ':00';
        $user = auth()->user();
        $event = [
            'price' => $data['price'],
            'public_url' => $data['public_url'],
            'topic' => $data['topic'],
            'agenda' => $data['agenda'],
            'start_time' => $start_time,
        ];
        if (isset($data['hours'])) {
            $event['hours'] = $data['hours'];
        }
        if (isset($data['minutes'])) {
            $event['minutes'] = $data['minutes'];
        }
        if (isset($data['require_registration'])) {
            $event['require_registration'] = $data['require_registration'];
        }
        if (isset($data['require_approval'])) {
            $event['require_approval'] = $data['require_approval'];
        }
        if (isset($data['number_of_tickets'])) {
            $event['number_of_tickets'] = $data['number_of_tickets'];
        }
        if (isset($data['location'])) {
            $event['location'] = $data['location'];
        }
        if (isset($data['notify_guest_register'])) {
            $event['notify_guest_register'] = $data['notify_guest_register'];
        }

        try {
            $ip = $request->ip();
            $ip = $ip == '127.0.0.1' ? '66.102.0.0' : $ip;
            $response = $this->client->request('GET', 'http://ip-api.com/json/' . $ip);
            $data12 = json_decode($response->getBody()->getContents(), true);
            $event['timezone'] = $time_zone;//$data12['timezone'];
        } catch (\Exception $e) {
            abort(500);
        }

        if (isset($data['invite_link'])) {
            $event['join_url'] = $data['invite_link'];
        } else {
            try {
                $response = $this->client->request('POST', 'https://api.zoom.us/v2/users/me/meetings', [
                    "headers" => [
                        "Authorization" => "Bearer " . $user->zoomToken->access_token,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $data
                ]);
            } catch (ClientException $e) {
                $code = $e->getCode();
                if ($code == 401) {
                    $refresh = $this->refreshToken();
                    if ($refresh instanceof RedirectResponse) {
                        return $refresh;
                    }
                    return $this->store($request);
                } else {
                    return redirect()->back()->with([
                        'type' => 'error',
                        'title' => 'Server Error',
                        'message' => 'Something went wrong.'
                    ]);
                }
            }
            $meeting = json_decode($response->getBody()->getContents(), true);
            // cache()->put('meeting', $meeting);
            // $meeting = cache()->get('meeting');
            $event['zoom_id'] = (string) $meeting['id'];
            // if (isset($meeting['timezone'])) {
            //     $event['timezone'] = $meeting['timezone'];
            // }
            if (isset($meeting['password'])) {
                $event['password'] = $meeting['password'];
            }
            $event['start_url'] = $meeting['start_url'];
            $event['join_url'] = $meeting['join_url'];
        }

        // Faqs
        $questions = $request->input('question');
        $answers = $request->input('answer');
        if ($questions && count($questions) > 0) {
            foreach ($questions as $key => $question) {
                $answer = $answers[$key];
                if ($question && $answer) {
                    if (!isset($event['faqs'])) {
                        $event['faqs'] = [];
                    }
                    array_push($event['faqs'], [
                        'question' => $question,
                        'answer' => $answer,
                    ]);
                }
            }
        }

        // Speakers
        $names = $request->input('speakername');
        $bios = $request->input('speakerbio');
        if ($names && count($names) > 0) {
            foreach ($names as $key => $name) {
                $bio = $bios[$key];
                if (!$bio) {
                    $bio = $name;
                }
                if ($name && $bio) {
                    if (!isset($event['speakers'])) {
                        $event['speakers'] = [];
                    }
                    array_push($event['speakers'], [
                        'name' => $name,
                        'bio' => $bio,
                    ]);
                }
            }
        }

        if(isset($data['image']) && !filter_var($data['image'], FILTER_VALIDATE_URL)) {
            $event['background_cover'] = $this->savePhoto($data['image']);
        }elseif(isset($data['hdn_duplicate_backgound_cover'])){
            $event['background_cover'] = $data['hdn_duplicate_backgound_cover'];
        }
        $event['ip_address'] = $ip;

        $user->zoomMeetings()->create($event);
        return redirect()->route('eventmanager.event.index')->with([
            'type' => 'success',
            'title' => 'Successfully Created',
            'message' => 'Event has been successfully created.'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $event = auth()->user()->zoomMeetings()->findorFail($id);
        return view('eventmanager.event.edit', compact('event'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $event = $user->zoomMeetings()->findorFail($id);
        $v = \Validator::make($request->all(), [
            'topic' => 'required|max:190',
            'agenda' => 'required|max:2000',
            'start_time' => 'required|date|after_or_equal:now',
			'time_zone'	=> 'required',
            'price' => 'required|numeric',
            "hours" => 'nullable|numeric',
            "minutes" => 'nullable|numeric|max:60|min:0',
            "private" => 'nullable|boolean',
            "require_registration" => 'nullable|boolean',
            "require_approval" => 'nullable|boolean',
            "notify_guest_register" => 'nullable|boolean',
            "location" => 'nullable|string',
            "number_of_tickets" => 'nullable|numeric',
            'image' => 'nullable|string',
            'question.*' => 'required',
            'answer.*' => 'required',
            'speakername.*' => 'required',
            'speakerbio.*' => 'required'
            // 'question.*' => 'required|alpha_spaces_question_mark',
            // 'answer.*' => 'required|alpha_numeric_spaces_question_mark',
            // 'speakername.*' => 'required|alpha_spaces',
            // 'speakerbio.*' => 'required'
        ],[
            'minutes.max' => 'Minutes must not be greater than 60',
            'minutes.numeric' => 'Minutes must be a number',
            'minutes.min' => 'Minutes must be at least 0'
        ]);

        $v->sometimes('minutes', 'required', function ($input) {
            $hours = $input->hours;
            if (!$hours) {
                return true;
            } else {
                return false;
            }
        });
        $v->sometimes('price', 'min:1', function ($input) {
            if ($input->price > 0 && $input->price < 1) {
                return true;
            } else {
                return false;
            }
        });
        $v->sometimes('invite_link', 'required|url', function ($input) use ($event) {
            if ($event->join_url) {
                return true;
            } else {
                return false;
            }
        });
        $data = $v->validate();
        if (!auth()->user()->stripeToken && $data['price'] > 0) {
            return redirect()->back()->withInput()->with([
                'type' => 'warning',
                'title' => 'Fullfill Requirements',
                'message' => 'You need to connect stripe account to create paid events.'
            ]);
        }
        if (!isset($data['private'])) {
            $data['private'] = 0;
        }
        if (!isset($data['require_registration'])) {
            $event['require_registration'] = 0;
        }
        if (!isset($data['require_approval'])) {
            $event['require_approval'] = 0;
        }
        if (auth()->user()->zoomToken) {
            if ($data['hours'] || $data['minutes']) {
                $data['duration'] = 0;
            }
            if ($data['hours']) {
                $data['duration'] = $data['hours'] * 60;
            }
            if ($data['minutes']) {
                $data['duration'] += $data['minutes'];
            }
        }
        $start_time = $data['start_time'] . ':00';
        $data['start_time'] = str_replace(' ', 'T', $data['start_time']);
        $data['start_time'] = $data['start_time'] . ':00';
$data['timezone'] = $data['time_zone'];
        if ($event->zoom_id) {
            $parseData = $data;
            $parseData['image'] = null;
            if($user->zoomToken!=null){
                try {
                    $this->client->request('PATCH', 'https://api.zoom.us/v2/meetings/' . $event->zoom_id, [
                        "headers" => [
                            "Authorization" => "Bearer " . $user->zoomToken->access_token,
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json'
                        ],
                        'json' => $parseData
                    ]);
                }
                catch (ClientException $e) {
                    $code = $e->getCode();
                    if ($code == 401) {
                        $refresh = $this->refreshToken();
                        if ($refresh instanceof RedirectResponse) {
                            return $refresh;
                        }
                        return $this->update($request, $id);
                    } elseif ($code == 404) {
                        return redirect()->back()->with([
                            'type' => 'error',
                            'title' => 'Not Found',
                            'message' => 'Meeting is not found or has expired in Zoom Account.'
                        ]);
                    } else {
                        return redirect()->back()->with([
                            'type' => 'error',
                            'title' => 'Server Error',
                            'message' => 'Something went wrong.'
                        ]);
                    }
                }
            }
            else{
                return redirect()->back()->with([
                    'type' => 'error',
                    'title' => 'Zoom Connection',
                    'message' => 'Please connect zoom id.'
                ]);
            }


            }
            // $meeting = json_decode($response->getBody()->getContents(), true);
            // cache()->put('meeting', $meeting);
            // $meeting = cache()->get('meeting');
            // dd($meeting);
            $data['start_time'] = $start_time;

            $questions = $request->input('question');
            $answers = $request->input('answer');
            if ($questions && count($questions) > 0) {
                foreach ($questions as $key => $question) {
                    $answer = $answers[$key];
                    if ($question && $answer) {
                        if (!isset($data['faqs'])) {
                            $data['faqs'] = [];
                        }
                        array_push($data['faqs'], [
                            'question' => $question,
                            'answer' => $answer,
                        ]);
                    }
                }
            } else {
                $data['faqs'] = null;
            }

            $names = $request->input('speakername');
            $bios = $request->input('speakerbio');
            if ($names && count($names) > 0) {
                foreach ($names as $key => $name) {
                    $bio = $bios[$key];
                    if ($name && $bio) {
                        if (!isset($data['speakers'])) {
                            $data['speakers'] = [];
                        }
                        array_push($data['speakers'], [
                            'name' => $name,
                            'bio' => $bio,
                        ]);
                    }
                }
            } else {
                $data['speakers'] = null;
            }

            if (isset($data['image']) && !filter_var($data['image'], FILTER_VALIDATE_URL)) {
                $data['background_cover'] = $this->savePhoto($data['image']);
                if ($user->profile->background_cover) {
                    \Storage::disk('public_folder')->delete($user->profile->background_cover);
                }
            }
            unset($data['image']);
            if (isset($data['invite_link']) && $data['invite_link']) {
                $data['join_url'] = $data['invite_link'];
            }

            $event->update($data);
            return redirect()->route('eventmanager.event.index')->with([
                'type' => 'success',
                'title' => 'Successfully Updated',
                'message' => 'Event has been successfully updated.'
            ]);
        }

        /**
         * Remove the specified resource from storage.
         *
         * @param  int  $id
         * @return \Illuminate\Http\Response
         */
        public function destroy($id)
    {

        $user = auth()->user();
        $event = $user->zoomMeetings()->findorFail($id);

        if ($event->zoom_id) {
            if($user->zoomToken!=null){
                try {
					$res = $this->client->request('GET','https://api.zoom.us/v2/meetings/'.$event->zoom_id, [
                        "headers" => [
                            "Authorization" => "Bearer " . $user->zoomToken->access_token,
                            'Content-Type' => 'application/json'
                        ]
                    ]);

                    $this->client->request('DELETE', 'https://api.zoom.us/v2/meetings/' . $event->zoom_id, [
                        "headers" => [
                            "Authorization" => "Bearer " . $user->zoomToken->access_token,
                            'Content-Type' => 'application/json'
                        ]
                    ]);

                }
                catch (ClientException $e) {
                    $code = $e->getCode();
                    if ($code == 401 || $code == 400 || $code == 404) {
                        $event->cancelled = 1;
						$event->save();
						return redirect()->route('eventmanager.event.index')->with([
							'type' => 'success',
							'title' => 'Successfully Cancelled',
							'message' => 'Event has been successfully cancelled.'
						]);
                    } else {
                        return redirect()->back()->with([
                            'type' => 'error',
                            'title' => 'Server Error',
                            'message' => 'Something went wrong.'
                        ]);
                    }
                }
            }
            else{
                return redirect()->back()->with([
                    'type' => 'error',
                    'title' => 'Zoom Connection',
                    'message' => 'Please connect zoom id.'
                ]);
            }
            
        }
        $event->cancelled = 1;
        $event->save();
        return redirect()->route('eventmanager.event.index')->with([
            'type' => 'success',
            'title' => 'Successfully Cancelled',
            'message' => 'Event has been successfully cancelled.'
        ]);
    }

        public function duplicate($id)
    {
        $event = auth()->user()->zoomMeetings()->findorFail($id);
        return view('eventmanager.event.duplicate', compact('event'));
    }

        public function guest($id)
    {
        $user = auth()->user();
        $events = $user->zoomMeetings()->with(['meetingInvitations:id,zoom_meeting_id,meeting_guest_id', 'meetingInvitations.meetingGuest:id,email'])->get();
        $guests = [];
        foreach ($events as $event) {
            foreach ($event->meetingInvitations as $invitation) {
                if (!in_array($invitation->meetingGuest->email, $guests)) {
                    array_push($guests, $invitation->meetingGuest->email);
                }
            }
        }
        $event = $user->zoomMeetings()->with('meetingInvitations.meetingGuest')->findorFail($id);

        // $exceededInviation = false;
        // if ($event->meetingInvitations->where('invitation', 1)->count() >= $user->plan->allowed_e_invites) {
        //     $exceededInviation = true;
        // }

        $invitesExceeded = [];
        if ($user->plan->allowed_e_invites <= 0 || $event->e_invites >= $user->plan->allowed_e_invites) {
            $invitesExceeded['e_invites'] = true;
        }
        if ($user->plan->allowed_sms_invites <= 0 || $event->sms_invites >= $user->plan->allowed_sms_invites) {
            $invitesExceeded['sms_invites'] = true;
        }
        if ($user->plan->allowed_whatsapp_invites <= 0 || $event->whatsapp_invites >= $user->plan->allowed_whatsapp_invites) {
            $invitesExceeded['whatsapp_invites'] = true;
        }
        if (isset($invitesExceeded['e_invites']) && isset($invitesExceeded['sms_invites']) && isset($invitesExceeded['whatsapp_invites'])) {
            $invitesExceeded['all'] = true;
        }


        return view('eventmanager.event.guest', compact('event', 'guests', 'invitesExceeded'));
    }

        public function invite(Request $request, $id)
    {
        $data = $request->validate([
            'message' => 'nullable|string',
        ]);

        $whatsapps = $request->input('whatsapps');
        if ($whatsapps) {
            $whatsapps = explode(',', $whatsapps);
            foreach ($whatsapps as $whatsapp) {
                if ($whatsapp) {
                    $contains_plus = \Str::startsWith($whatsapp, '+');
                    if (!$contains_plus) {
                        return redirect()->back()->with([
                            'type' => 'error',
                            'title' => 'Number Error',
                            'message' => 'Please enter + in-front of country code and mobile number without any spacing'
                        ]);
                    }
                }
            }
        }

        $numbers = $request->input('numbers');
        if ($numbers) {
            $numbers = explode(',', $numbers);
            foreach ($numbers as $number) {
                if ($number) {
                    $contains_plus = \Str::startsWith($number, '+');
                    if (!$contains_plus) {
                        return redirect()->back()->with([
                            'type' => 'error',
                            'title' => 'Number Error',
                            'message' => 'Please enter + in-front of country code and mobile number without any spacing'
                        ]);
                    }
                }
            }
        }

        $user = auth()->user();
        $event = $user->zoomMeetings()->with('meetingInvitations.meetingGuest')->findorFail($id);

        if ($event->e_invites < $user->plan->allowed_e_invites) {
            $emails = $request->input('emails');
            if ($emails) {
                $emails = explode(',', $emails);
            } elseif ($request->input('guests')) {
                $emails = $request->input('guests');
            }
            if ($emails) {
                foreach ($emails as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $guest = $user->meetingGuests()->getModel()->whereEmail($email)->first();
                        if (!$guest) {
                            $guest = $user->meetingGuests()->getModel()->create(['email' => $email]);
                        }

                        $invitation = $guest->meetingInvitations()->whereZoomMeetingId($event->id)->first();
                        if (!$invitation) {
                            $data['zoom_meeting_id'] = $event->id;
                            $data['meeting_guest_id'] = $guest->id;
                            $data['invitation'] = 1;
                            $invitation = $guest->meetingInvitations()->create($data);
                            $event->e_invites += 1;
                        } else {
                            if (!is_null($invitation->status) && !$invitation->status) {
                                $invitation->invitation = 1;
                                $invitation->status = null;
                                $invitation->save();
                                $event->e_invites += 1;
                            }
                        }
                        $mytime = Carbon::now();
                        DB::table('invitation_mails')->insert([
                            'event_id' => $event->id,
                            'email' => $email,
                            'created_at' => $mytime,
                            'updated_at' => $mytime
                        ]);
                        $guest->notify(new GuestNotification($event, $invitation, $data['message']));
                    }
                }
            }
        }

        $message = 'You are invited to "' . $event->topic . '" by ' . $event->user->full_name . ' on ' . $event->start_time->format('F d') . 'th at ' . $event->start_time->format('h:i a') . '.';


        if ($event->whatsapp_invites < $user->plan->allowed_whatsapp_invites) {
            $whatsapps = $request->input('whatsapps');
            if ($whatsapps) {
                $whatsapps = explode(',', $whatsapps);
                foreach ($whatsapps as $whatsapp) {
                    if ($whatsapp) {
                        $twilio_whatsapp = config("services.twilio.whatsapp");
                        $this->twilioClient->messages->create(
                            'whatsapp:' . $whatsapp,
                            // 'whatsapp:' . '+923317601663',
                            ['from' => 'whatsapp:' . $twilio_whatsapp, 'body' => $message]
                        );
                        $event->whatsapp_invites += 1;
                    }
                }
            }
        }
        if ($data['message']) {
            $message .= " \n " . $data['message'];
        }

        if ($event->sms_invites < $user->plan->allowed_sms_invites) {
            $numbers = $request->input('numbers');
            if ($numbers) {
                $numbers = explode(',', $numbers);
                foreach ($numbers as $number) {
                    if ($number) {
                        $message .= " \n " . route('frontend.event', $event->public_url);
                        $twilio_number = config("services.twilio.number");
                        $this->twilioClient->messages->create(
                            $number,
                            // '+923317601663',
                            ['from' => $twilio_number, 'body' => $message]
                        );
                        $event->sms_invites += 1;
                    }
                }
            }
        }

        $event->save();

        return redirect()->route('eventmanager.event.guest', $event->id)->with([
            'type' => 'success',
            'title' => 'Successfully Sent',
            'message' => 'Invitations have been successfully sent.'
        ]);
    }

        public function reminder($id)
    {
        $user = auth()->user();
        $event = $user->zoomMeetings()->with(['meetingReminders', 'meetingInvitations.meetingGuest'])->findorFail($id);


        return view('eventmanager.event.reminder', compact('event'));
    }

        public function reminderSave(Request $request, $id)
    {
        $v = \Validator::make($request->all(), [
            "hours_before" => 'nullable|numeric',
            "minutes_before" => 'nullable|numeric',
            'send_to' => 'required',
            'subject' => 'required|string|max:190',
            'message' => 'string',
        ]);
        $v->sometimes('minutes_before', 'required', function ($input) {
            $hours = $input->hours_before;
            if (!$hours) {
                return true;
            } else {
                return false;
            }
        });
        $data = $v->validate();
        $user = auth()->user();
        $event = $user->zoomMeetings()->with(['meetingReminders', 'meetingInvitations.meetingGuest'])->findorFail($id);
        $event->meetingReminders()->create($data);


        return redirect()->back()->with([
            'type' => 'success',
            'title' => 'Successfully Set',
            'message' => 'Reminder Email has been successfully set.'
        ]);
    }

        public function removeReminder($event, $guest)
    {
        $user = auth()->user();
        $event = $user->zoomMeetings()->with('meetingInvitations.meetingGuest')->findorFail($event);
        $guest = $event->meetingReminders()->findorFail($guest);
        $guest->delete();
        return redirect()->back()->with([
            'type' => 'success',
            'title' => 'Successfully Deleted',
            'message' => 'Reminder Email has been deleted successfully.'
        ]);
    }

        public function removeGuest($event, $guest)
    {
        $user = auth()->user();
        $event = $user->zoomMeetings()->with('meetingInvitations.meetingGuest')->findorFail($event);
        $guest = $event->meetingInvitations()->findorFail($guest);
        $guest->delete();
        $event->e_invites -= 1;
        $event->sms_invites -= 1;
        $event->whatsapp_invites -= 1;
        $event->save();
        return redirect()->back()->with([
            'type' => 'success',
            'title' => 'Successfully Deleted',
            'message' => 'Guest has been deleted successfully.'
        ]);
    }

        public function status(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|boolean',
            'event' => 'required'
        ]);
        $user = auth()->user();
        $event = $user->zoomMeetings()->findorFail($data['event']);
        $invitation = $event->meetingInvitations()->with('meetingGuest')->findorFail($id);
        $invitation->status = $data['status'];
        $invitation->save();
        if ($invitation->status) {
            $invitation->meetingGuest->notify(new GuestAcceptedNotification($event, $invitation));
            return redirect()->route('eventmanager.event.guest', $event->id)->with([
                'type' => 'success',
                'title' => 'Successfully Approved',
                'message' => 'Guest has been approved successfully sent.'
            ]);
        } else {
            $invitation->meetingGuest->notify(new GuestRejectedNotification($event, $invitation));
            return redirect()->route('eventmanager.event.guest', $event->id)->with([
                'type' => 'success',
                'title' => 'Successfully Rejected',
                'message' => 'Guest has been rejected successfully sent.'
            ]);
        }
    }

        public function updateemail(Request $request, $id)
    {
        $data = $request->validate([
            'message' => 'required|string',
            'recepients' => 'required|in:all,invited,approved,declined',
        ]);
        $user = auth()->user();
        $event = $user->zoomMeetings()->findorFail($id);

        $invitations = collect([]);
        if ($data['recepients'] == 'all') {
            $invitations = $event->meetingInvitations()->with('meetingGuest')->get();
        } else if ($data['recepients'] == 'invited') {
            $invitations = $event->meetingInvitations()->with('meetingGuest')->where('invitation', 1)->get();
        } else if ($data['recepients'] == 'approved') {
            $invitations = $event->meetingInvitations()->with('meetingGuest')->where('status', 1)->get();
        } else if ($data['recepients'] == 'declined') {
            $invitations = $event->meetingInvitations()->with('meetingGuest')->where('status', 0)->get();
        }

        foreach ($invitations as $invitation) {
            $invitation->meetingGuest->notify(new MeetingUpdateNotification($data['message'], $event));
        }

        return redirect()->route('eventmanager.event.index')->with([
            'type' => 'success',
            'title' => 'Successfully Sent',
            'message' => 'Update email has been successfully sent.'
        ]);
    }

        public function refreshToken()
    {
        $user = auth()->user();
        $token = $this->getToken(null, $user->zoomToken->refresh_token);
        if ($token instanceof RedirectResponse) {
            return $token;
        }
        $zoomToken = $user->zoomToken;
        $zoomToken->access_token = $token['access_token'];
        $zoomToken->refresh_token = $token['refresh_token'];
        $zoomToken->save();
        $user->refresh();
    }

        public function getToken($code, $refreshtoken = null)
    {
        $params = [];
        if ($code) {
            $params["grant_type"] = 'authorization_code';
            $params["code"] = $code;
            $params["redirect_uri"] = config('services.zoom.redirect');
        } else {
            $params['grant_type'] = 'refresh_token';
            $params['refresh_token'] = $refreshtoken;
        }
        try {
            $response = $this->client->request('POST', 'https://zoom.us/oauth/token', [
                "headers" => [
                    "Authorization" => "Basic " . base64_encode(config('services.zoom.key') . ':' . config('services.zoom.secret'))
                ],
                'form_params' => $params,
            ]);

        } catch (ClientException $e) {
            $code = $e->getCode();
            if ($code == 401) {
                auth()->user()->zoomToken()->delete();
                return redirect()->route('eventmanager.profile')->with([
                    'type' => 'error',
                    'title' => 'Authorization Error',
                    'message' => 'Authorization error in ZOOM account.'
                ]);
            }
        }
		
			$token = json_decode($response->getBody()->getContents(), true);
			return $token;
    }

        private function savePhoto($image)
    {
        try {
            list($mime, $data)   = explode(';', $image);
            list(, $data)       = explode(',', $data);
            $data = base64_decode($data);

            $mime = explode(':', $mime)[1];
            $ext = explode('/', $mime)[1];
            $name = mt_rand() . time();
            $savePath = 'uploads/' . $name . '.' . $ext;

            //file_put_contents(public_path() . '/' . $savePath, $data);
            file_put_contents($savePath, $data);

            return $savePath;
        } catch (\Exception $e) {
            //doing nothing here for not breaking the loop
            // you can pass the error message to your view if you want.
        }
    }

        public function exportGuests($id)
    {
        return Excel::download(new GuestsExport($id), 'guests.csv');
    }
    }
