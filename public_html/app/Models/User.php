<?php

namespace App\Models;

use Laravel\Cashier\Billable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\UserResetPasswordNotification;
use App\Notifications\UserEmailVerificationNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'plan_id', 'first_name', 'last_name', 'email', 'phone_code', 'phone_no', 'password', 'verified','redemption_code'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['full_name', 'phone'];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['plan', 'profile', 'zoomToken', 'stripeToken'];

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getPhoneAttribute()
    {
        return "{$this->phone_code} {$this->phone_no}";
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new UserResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new UserEmailVerificationNotification());
    }

    public function socialLogin()
    {
        return $this->hasOne(SocialLogin::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function account()
    {
        return $this->hasOne(Account::class)->withDefault();
    }

    public function profile()
    {
        return $this->hasOne(Profile::class)->withDefault();
    }

    public function zoomToken()
    {
        return $this->hasOne(ZoomToken::class);
    }

    public function stripeToken()
    {
        return $this->hasOne(StripeToken::class);
    }

    public function zoomMeetings()
    {
        return $this->hasMany(ZoomMeeting::class);
    }

    public function meetingGuests()
    {
        return $this->hasMany(MeetingGuest::class);
    }

    public static function boot()
    {
        parent::boot();

        static::deleted(function ($obj) {
            $obj->zoomToken()->delete();
            $obj->stripeToken()->delete();
            $obj->zoomMeetings()->delete();
            $obj->profile()->delete();
            $obj->socialLogin()->delete();
        });
    }
}
