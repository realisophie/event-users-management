<?php
   
namespace App\Console\Commands;
   
use Illuminate\Console\Command;
use App\Notifications\MeetingReminderNotification;
use App\Models\MeetingInvitation;
use Illuminate\Support\Facades\Notification;
use App\Models\ZoomMeeting;

   
class Reminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminder:cron';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
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