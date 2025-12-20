<?php


namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class UserActivated extends Notification implements ShouldQueue
{
    use Queueable;

    public $activatedUser;

    /**
     * Create a new notification instance.
     *
     * @param mixed $activatedUser
     * @return void
     */
    public function __construct($activatedUser)
    {
        $this->activatedUser = $activatedUser;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        // Send via email and store in database.
        return ['mail', 'database'];
        //return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your Account Has Been Activated')
            ->line('Congratulations! Your account has been successfully activated.')
            ->line('User: ' . $this->activatedUser->name)
            ->action('Visit Dashboard', url('/dashboard'))
            ->line('You can now log in and start using your account.');
    }

    /**
     * Get the array representation of the notification for storage in the database.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return [
            'message'            => "User “{$this->activatedUser->name}” was activated.",
            // who got notified:
            'notified_user_id'   => $notifiable->id,
            // who was activated:
            'activated_user_id'  => $this->activatedUser->id,
        ];
    }

    public function koDatabase($notifiable)
    {
        return [
            'message' => "User " . $this->activatedUser->name . " has been activated.",
            'user_id' => $this->activatedUser->id
        ];
    }
}
