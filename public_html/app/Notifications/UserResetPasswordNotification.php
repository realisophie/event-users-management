<?php

namespace App\Notifications;

use Mustache_Engine;
use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\ResetPassword;

class UserResetPasswordNotification extends ResetPassword
{
    use Queueable;

    public function toMail($notifiable)
    {
        $email = Email::where('key', 'password_reset')->firstorFail();
        $mustache = new Mustache_Engine();

        $tokens = [
            'first_name' => $notifiable->first_name,
            'last_name' => $notifiable->last_name,
            'email' => $notifiable->email,
        ];
        
        $mail = (new MailMessage)
        ->subject($mustache->render($email->subject, $tokens));

        $message = $mustache->render(nl2br($email->message), $tokens);
        $message = explode('<br />', $message);
        foreach ($message as $line) {
            $mail = $mail->line($line);
        }

        $mail = $mail->action('Reset Password', route('password.reset', $this->token) . '?email=' . urlencode($notifiable->getEmailForPasswordReset()));

        return $mail;
    }
}
