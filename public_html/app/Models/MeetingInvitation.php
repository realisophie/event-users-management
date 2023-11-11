<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingInvitation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'zoom_meeting_id', 'meeting_guest_id', 'invitation', 'message', 'registered', 'registered_at', 'status'
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function meetingGuest()
    {
        return $this->belongsTo(MeetingGuest::class);
    }

    public function zoomMeeting()
    {
        return $this->belongsTo(ZoomMeeting::class);
    }
}
