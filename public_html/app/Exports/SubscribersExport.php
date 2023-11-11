<?php

namespace App\Exports;

use App\Models\Subscriber;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\FromCollection;

class SubscribersExport implements FromCollection, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $users = Subscriber::select('id', 'email')->get();
        $users = $users->prepend(new Subscriber([
            'email' => 'Email',
        ]));
        return $users;
    }

    /**
     * @var User $user
     */
    public function map($subscriber): array
    {
        return [
            $subscriber->email,
        ];
    }
}
