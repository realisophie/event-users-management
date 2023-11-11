<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    protected $fillable = ['key', 'name', 'tokens', 'subject', 'message'];

    public $timestamps = false;

    protected $casts = [
        'tokens' => 'array',
    ];
}
