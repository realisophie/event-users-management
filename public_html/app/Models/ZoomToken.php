<?php

namespace App\Models;

use Crypt;
use Illuminate\Database\Eloquent\Model;

class ZoomToken extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'zoom_id', 'zoom_email', 'access_token', 'refresh_token'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'zoom_id', 'access_token', 'refresh_token'
    ];

    // public function setZoomIdAttribute($value)
    // {
    //     $this->attributes['zoom_id'] = Crypt::encryptString($value);
    // }

    // public function getZoomIdAttribute($value)
    // {
    //     return Crypt::decryptString($value);
    // }

    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute($value)
    {
        return Crypt::decryptString($value);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
