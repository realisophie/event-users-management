<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MeetingGuest extends Model
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'email'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    // protected $casts = [
    //     'start_url' => 'datetime:yyyy-MM-dd HH:mm:ss',
    // ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function zoomMeeting()
    {
        return $this->belongsTo(ZoomMeeting::class);
    }

    public function meetingInvitations()
    {
        return $this->hasMany(MeetingInvitation::class);
    }
}
