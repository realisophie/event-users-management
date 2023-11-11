<?php

namespace App\Models;

use Crypt;
use Illuminate\Database\Eloquent\Model;

class ZoomMeeting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'zoom_id', 'topic', 'agenda', 'start_time', 'hours', 'minutes', 'timezone', 'password', 'start_url', 'join_url', 'price', 'public_url', 'faqs', 'speakers', 'location', 'number_of_tickets', 'require_registration', 'background_cover', 'image', 'private','ip_address' ,'require_approval', 'notify_guest_register', 'e_invites', 'sms_invites', 'whatsapp_invites', 'cancelled'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'zoom_id', 'password'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'faqs' => 'array',
        'speakers' => 'array',
        'start_time' => 'datetime:Y-M-d H:m:s',
    ];

    /**
     * Get the event's background cover.
     *
     * @return string
     */
    public function getFormatedBackgroundCoverAttribute()
    {
        if ($this->background_cover) {
            return \URL::to($this->background_cover);
        } else {
            return \URL::to('/assets/images/event.jpg');
        }
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    public function getPasswordAttribute($value)
    {
        return Crypt::decryptString($value);
    }

    public function setZoomIdAttribute($value)
    {
        $this->attributes['zoom_id'] = Crypt::encryptString($value);
    }

    public function getZoomIdAttribute($value)
    {
        if ($value) {
            return Crypt::decryptString($value);
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function meetingInvitations()
    {
        return $this->hasMany(MeetingInvitation::class);
    }

    public function meetingReminders()
    {
        return $this->hasMany(MeetingReminder::class);
    }

    public static function boot()
    {
        parent::boot();

        static::deleted(function ($obj) {
            $obj->meetingInvitations()->delete();
        });
    }
}
