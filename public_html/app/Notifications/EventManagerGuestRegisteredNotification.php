<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\MeetingInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Email;
use Mustache_Engine;

class EventManagerGuestRegisteredNotification extends Notification
{
    use Queueable;

    public $invitation;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(MeetingInvitation $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {

        $email = Email::where('key', 'host_notification')->firstorFail();
        $mustache = new Mustache_Engine();

        $duration = '';
        if ($this->invitation->zoomMeeting->hours) {
            $duration .= $this->invitation->zoomMeeting->hours . 'H ';
        }
        $duration .= $this->invitation->zoomMeeting->minutes . 'M';
        $tokens = [
            'event' => [
                'topic' => $this->invitation->zoomMeeting->topic,
                'location' => $this->invitation->zoomMeeting->location,
                'date' => $this->invitation->zoomMeeting->start_time->format('F d'),
                'time' => $this->invitation->zoomMeeting->start_time->format('h:i a'),
                'duration' => $duration,
            ],
            'host' => [
                'first_name' => $notifiable->first_name,
                'last_name' => $notifiable->last_name,
                'email' => $notifiable->email,
            ],
            'guest' => [
                'email' => $this->invitation->meetingGuest->email
            ]
        ];

        $mail = (new MailMessage)
            ->subject($mustache->render($email->subject), $tokens);

        $message = $mustache->render(nl2br($email->message), $tokens);
        $message = explode('<br />', $message);
        foreach ($message as $line) {
            $mail = $mail->line($line);
        }

        $mail = $mail->action('Manage Guests', route('eventmanager.event.guest', $this->invitation->zoomMeeting->id));

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
