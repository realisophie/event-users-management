<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stripe_id', 'stripe_product_id', 'name', 'interval', 'interval_count', 'cost', 'custom_url', 'custom_e_invites', 'allowed_events', 'allowed_e_invites', 'allowed_sms_invites', 'allowed_whatsapp_invites', 'percentage_per_ticket_sold', 'order_by', 'main_plan', 'limitted_plan'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'zoom_id', 'access_token', 'refresh_token'
    ];

    public function getCostAttribute($value)
    {
        return number_format($value, 2, '.', '');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public static function boot()
    {
        parent::boot();

        static::deleted(function ($obj) {
            $obj->users()->delete();
        });
    }
}
