<?php

namespace App\Notifications;

use Mustache_Engine;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestNotification extends Notification
{
    use Queueable;

    public $event;
    public $invitation;
    public $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($event, $invitation, $message)
    {
        $this->event = $event;
        $this->invitation = $invitation;
        $this->message = $message;
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
        $email = Email::where('key', 'guest_notification')->firstorFail();
        $mustache = new Mustache_Engine();

        $duration = '';
        if ($this->event->hours) {
            $duration .= $this->event->hours . 'H ';
        }
        $duration .= $this->event->minutes . 'M';
        $tokens = [
            'event' => [
                'topic' => $this->event->topic,
                'location' => $this->event->location,
                'date' => $this->event->start_time->format('F d'),
                'time' => $this->event->start_time->format('h:i a'),
                'duration' => $duration,
            ],
            'host' => [
                'first_name' => $this->event->user->first_name,
                'last_name' => $this->event->user->last_name,
                'email' => $this->event->user->email,
            ],
            'guest' => [
                'email' => $notifiable->email
            ]
        ];

        $mail = (new MailMessage)
            ->subject($mustache->render($email->subject, $tokens));

        $message = $mustache->render(nl2br($email->message), $tokens);
        $message = explode('<br />', $message);
        foreach ($message as $line) {
            $mail = $mail->line($line);
        }

        if ($this->message) {
            $mail = $mail->line($this->message);
        }

        $mail = $mail->action('Accept Invitation', route('frontend.event', $this->event->public_url));

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
