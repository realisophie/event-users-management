<?php

namespace App\Notifications;

use Mustache_Engine;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class UserEmailVerificationNotification extends VerifyEmail
{
    use Queueable;

    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $verificationUrl);
        }

        $email = Email::where('key', 'verification_email')->firstorFail();
        $mustache = new Mustache_Engine();

        $tokens = [
            'first_name' => $notifiable->first_name,
            'last_name' => $notifiable->last_name,
            'email' => $notifiable->email,
        ];
        
        $mail = (new MailMessage)
        ->subject($mustache->render($email->subject, $tokens));
        //->markdown('vendor.mail.markdown.verify-email', ['code' => $this->code]);

        $message = $mustache->render(nl2br($email->message), $tokens);
        $message = explode('<br />', $message);
        foreach ($message as $line) {
            $mail = $mail->line($line);
        }

        $mail = $mail->action('Verify Email Address', $verificationUrl);



        return $mail;
    }
}
