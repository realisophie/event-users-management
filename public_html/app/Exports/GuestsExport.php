<?php

namespace App\Exports;

use App\Models\MeetingInvitation;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\FromCollection;

class GuestsExport implements FromCollection, WithMapping
{
    private $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $user = auth()->user();
        $event = $user->zoomMeetings()->with('meetingInvitations.meetingGuest')->findorFail($this->id);
        $invitations = $event->meetingInvitations;
        $invitations = $invitations->prepend(new MeetingInvitation([
            'zoom_meeting_id' => 0,
        ]));
        return $invitations;
    }

    /**
     * @var User $user
     */
    public function map($invitation): array
    {
        $email = null;
        if ($invitation->zoom_meeting_id != 0) {
            $email = $invitation->meetingGuest->email;
        } else {
            $email = 'Email';
        }
        return [
            $email,
        ];
    }
}
