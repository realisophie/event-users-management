<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeToken extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'stripe_id', 'access_token', 'refresh_token'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'stripe_id', 'access_token', 'refresh_token'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
