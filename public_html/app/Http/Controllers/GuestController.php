<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Stripe\Stripe;
use App\Models\Plan;
use App\Models\Blog;
use App\Models\Profile;
use Stripe\PaymentIntent;
use App\Models\Subscriber;
use App\Models\ZoomMeeting;
use App\Models\MeetingGuest;
use Illuminate\Http\Request;
use App\Models\MeetingInvitation;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MeetingReminderNotification;
use App\Notifications\EventManagerGuestRegisteredNotification;
use Illuminate\Support\Facades\Route;
// require '../vendor/send-grid-email/vendor/autoload.php';
class GuestController extends Controller
{
    public function testRouteList(){
        echo "test route list new<br><br>";
        $routeCollection = Route::getRoutes();

        echo "<table border='2'>";
        foreach ($routeCollection as $value) {
            //echo $value->uri()."<br><br>";

            echo "<tr>";
                echo "<td>" . $value->Methods()[0] . "</td>";
                echo "<td>" . $value->uri() . "</td>";
                echo "<td>" . $value->getName() . "</td>";
                echo "<td>" . $value->getActionName() . "</td>";
            echo "</tr>";

        }
        echo '</table>';

        echo "<br><br><br><br>its end";
    }
    
    public function testMail()
    {
        $toarr = array();
        array_push($toarr,array("email"=>"mba8487143@gmail.com","name"=>"Bali G 1"));
        array_push($toarr,array("email"=>"bilawalali512@gmail.com","name"=>"Bali G 2"));
        array_push($toarr,array("email"=>"ridaa.rida786@gmail.com","name"=>"Ma'am Rida"));

 sendGridEmail($toarr,"test group","<b>Send Grid TEST  mail without smtp</b>");

    }
    
    
    public function index()
    {
        // $url = asset('');
        // if ($url == "https://zmsend.com/") {
        //     return view('frontend.commingsoon');
        // }
        return view('frontend.index');
    }


    public function unsubscribee()
    {
        return view('frontend.unsubscribee');
    }


    public function pricing()
    {
        $plans = Plan::where('main_plan',1)->orderBy('order_by')->get();
        return view('frontend.pricing', compact('plans'));
    }

    public function limited_pricing()
    {
        $plans = Plan::where('limitted_plan',1)->orderBy('order_by')->get();
        return view('frontend.limited-pricing', compact('plans'));
    }

    public function platform()
    {
        return view('frontend.platform');
    }

    public function feature()
    {
        return view('frontend.feature');
    }

    public function event($public_url)
    {
        $eventmanager = Profile::wherePublicUrl($public_url)->with('user')->first();

        if ($eventmanager) {
	    if($eventmanager->user==null){	
	    	abort(404);
	    }
            $events = $eventmanager->user->zoomMeetings()->where('cancelled', '!=', 1)->where('private', 0)->orderBy('start_time')->get();
            $events = $events->chunk(4);
            return view('frontend.eventmanager', compact('eventmanager', 'events'));
        } 
	else {
            $event = ZoomMeeting::wherePublicUrl($public_url)->with(['user.stripeToken', 'user.plan'])->withCount('meetingInvitations')->firstorFail();
	    if($event->cancelled){
		abort(404);
	    }
            if($event->user!=null){
		if ($event->user->plan!=null){
	        	if (!$event->user->plan->custom_url) {
                    		abort(404);
            		}
	    	}
	    	else{
			abort(404);
	    	}
	    }
	    else{
		abort(404);
	    }
            $register = null;
            $registers = session()->get('registers');
            if ($registers) {
                $registers = collect($registers);
                $register = $registers->where('zoom_meeting_id', $event->id)->first();
                if ($register) {
                    $register = MeetingInvitation::find($register->id);
                    if (!$register) {
                        session()->forget('registers');
                    }
                }
            }
            $intent = null;
            if ($register && $register->registered) {
            } else {
                if ($event->user->stripeToken && $event->price > 0) {
                    $total = $event->price * 100;
                    $minusCharge = $total * $event->user->plan->percentage_per_ticket_sold;
                    $minusCharge = $minusCharge / 100;
                    $minusCharge = $total - $minusCharge;

                    Stripe::setApiKey(config('services.stripe.secret'));
                    $account_id = $event->user->stripeToken->stripe_id;
                    $intent = PaymentIntent::create([
                        'payment_method_types' => ['card'],
                        'amount' => $total,
                        'currency' => 'usd',
                        'on_behalf_of' => $account_id,
                        'transfer_data' => [
                            'amount' => $minusCharge,
                            'destination' => $event->user->stripeToken->stripe_id,
                        ],
                    ]);
                }
            }
            // dd($event->number_of_tickets, $event->meeting_invitations_count, $event->number_of_tickets && $event->number_of_tickets <= $event->meeting_invitations_count);
            return view('frontend.event', compact('event', 'intent', 'register'));
        }
        return abort(404);
    }

    public function register(Request $request)
    {
        $validate = $request->validate([
            'email' => 'required|email|max:190',
            'event' => 'required'
        ]);
        $event = ZoomMeeting::findorFail($validate['event']);
        $guest = MeetingGuest::whereEmail($validate['email'])->first();
        if (!$guest) {
            $guest = MeetingGuest::create($validate);
        }
        $invitation = $guest->meetingInvitations()->whereZoomMeetingId($event->id)->first();
        $validated['registered'] = 1;
        $validated['registered_at'] = now();
        $sendNotification = false;
        if (!$invitation) {
            $validated['zoom_meeting_id'] = $event->id;
            $validated['meeting_guest_id'] = $guest->id;
            if (!$event->require_approval) {
                $validated['status'] = 1;
            }
            $invitation = $guest->meetingInvitations()->create($validated);
            $sendNotification = true;
        }
        if (!$invitation->registered) {
            $invitation->update($validated);
            $sendNotification = true;
        }
        if ($sendNotification && $event->notify_guest_register) {
            $event->user->notify(new EventManagerGuestRegisteredNotification($invitation));
        }

        $registers = session()->get('registers');
        if (!$registers) {
            $registers = [];
        }
        array_push($registers, $invitation);
        session()->put('registers', $registers);

        return redirect()->route('frontend.event', $event->public_url);
    }

    public function blog()
    {
        $blogs = Blog::latest()->get();
        return view('frontend.blog', compact("blogs"));
    }

    public function blogs($slug)
    {
        $blog = Blog::whereSlug($slug)->firstorFail();
        return view('frontend.blogs', compact("blog"));
    }

    public function terms()
    {
        return view('frontend.terms');
    }

    public function support()
    {
        return view('frontend.support');
    }

    public function contact()
    {
        return view('frontend.contact');
    }

    public function help()
    {
        return view('frontend.help');
    }

    public function privacy()
    {
        return view('frontend.privacy');
    }

    public function zoom()
    {
        return view('frontend.zoom');
    }

    public function subscribe(Request $request)
    {
        $data = $request->only('email');
        Subscriber::updateOrInsert($data);
        return ['successfull' => true];
    }

    public function cron()
    {
        $events = ZoomMeeting::select('id', 'start_time', 'public_url', 'timezone')->with(['meetingReminders' => function ($query) {
            $query->select('id', 'zoom_meeting_id', 'subject', 'message', 'send_to', 'hours_before', 'minutes_before')->where('sent', 0);
        }])->has('meetingReminders')->get();


        foreach ($events as $event) {
            foreach ($event->meetingReminders as $reminder) {
                $now = now();
                $time = $event->start_time;
                if ($event->timezone) {
                    $now = $now->timezone($event->timezone);
                    $time = $time->shifttimezone($event->timezone);
                }
                if ($reminder->hours_before) {
                    $time = $time->subHours($reminder->hours_before);
                }
                if ($reminder->minutes_before) {
                    $time = $time->subMinutes($reminder->minutes_before);
                }
                $send_to = $reminder->send_to;
                if ($time->lessThanOrEqualTo($now)) {
                    $guests = collect();
                    if (array_search('approved', $send_to) !== false || array_search('invited', $send_to) !== false) {
                        $invitations = $event->meetingInvitations()->where(function ($query) use ($send_to) {
                            $invited = array_search('invited', $send_to);
                            if ($invited !== false) {
                                unset($send_to[$invited]);
                                $query->orWhere('invitation', 1);
                            }
                            $approved = array_search('approved', $send_to);
                            if ($approved !== false) {
                                unset($send_to[$approved]);
                                $query->orWhere('status', 1);
                            }
                        })->with('meetingGuest')->get();
                        foreach ($invitations as $invitation) {
                            $guests = $guests->push($invitation->meetingGuest);
                        }
                    } else {
                        $guests = $event->meetingInvitations()->getModel()->meetingGuest()->getModel()->whereIn('email', $send_to)->get();
                    }
                    Notification::send($guests, new MeetingReminderNotification($reminder, $event));
                    $reminder->update(['sent' => 1]);
                }
            }
        }
    }
}
