<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use Notifiable;
    protected $fillable = ['email'];
}
