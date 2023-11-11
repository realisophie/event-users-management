<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['type', 'company_name', 'first_name', 'last_name', 'country', 'address'];
}
