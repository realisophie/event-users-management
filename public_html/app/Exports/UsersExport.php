<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\FromCollection;

class UsersExport implements FromCollection, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $users = User::select('id', 'plan_id', 'first_name', 'last_name', 'email', 'phone_code', 'phone_no', 'email_verified_at', 'created_at','redemption_code')->with(['plan:id,name', 'profile'])->get();
        $users = $users->prepend(new User([
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'phone_code' => 'Country Code',
            'phone_no' => 'Phone No',
            'email_verified_at' => 'Email Verified',
            'redemption_code' => 'Redemption Code',
        ]));
        return $users;
    }

    /**
     * @var User $user
     */
    public function map($user): array
    {
        $verified = null;
        if ($user->email != 'Email') {
            $verified = $user->email_verified_at ? 'Yes' : 'No';
        } else {
            $verified = 'Email Verified';
        }

        $plan = null;
        if ($user->email != 'Email') {
            $plan = $user->plan->name;
        } else {
            $plan = 'Plan';
        }

        $created_at = null;
        if ($user->email != 'Email') {
            $created_at = $user->created_at;
        } else {
            $created_at = 'Created At';
        }

        $public_url = '';
        if ($user->email != 'Email') {
            if ($user->profile->public_url) {
                $public_url = route('frontend.event', $user->profile->public_url);
            }
        } else {
            $public_url = 'Public URL';
        }



        return [
            $user->first_name,
            $user->last_name,
            $user->email,
            $user->phone_code,
            $user->phone_no,
            $verified,
            $plan,
            $user->redemption_code,
            $public_url,
            $created_at,
        ];
    }
}
