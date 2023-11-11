<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'website', 'short_description', 'public_url', 'avatar', 'cover_photo'
    ];

    protected $appends = ['formatedAvatar'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user's avatar.
     *
     * @return string
     */
    public function getFormatedAvatarAttribute()
    {
        if ($this->avatar) {
            return \URL::to($this->avatar);
        } else {
            return \URL::to('/assets/images/avatar.png');
        }
    }

    /**
     * Get the user's cover photo.
     *
     * @return string
     */
    public function getFormatedCoverPhotoAttribute()
    {
        if ($this->cover_photo) {
            return \URL::to($this->cover_photo);
        } else {
            return \URL::to('/assets/images/cover.png');
        }
    }
}
