<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingReminder extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'zoom_meeting_id', 'email', 'subject', 'message', 'hours_before', 'minutes_before', 'send_to', 'sent'
    ];

    protected $casts = [
        'send_to' => 'array',
    ];

    public function zoomMeeting()
    {
        return $this->belongsTo(ZoomMeeting::class);
    }
}
