<?php

namespace App\Http\Controllers\Admin;

use App\Models\Sale;
use App\Models\User;
use Shetabit\Visitor\Models\Visit;
use Laravel\Cashier\Subscription;
use App\Http\Controllers\Controller;
use DB;

class DashboardController extends Controller
{
    private $user;
    private $subscription;
    private $visit;
    private $sale;

    public function __construct(User $user, Subscription $subscription, Visit $visit, Sale $sale)
    {
        $this->user = $user;
        $this->sale = $sale;
        $this->visit = $visit;
        $this->subscription = $subscription;
    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //ini_set('memory_limit', '999964M');

        $last_year = now()->subYears(1);
        $last_year_visits = 0;
        // if($last_year){
        //     $last_year_visits = $this->visit->select('platform', 'browser', 'ip', 'created_at')->whereDate('created_at','>',$last_year)->latest()->get();
        //     $last_year_visits = $last_year_visits->count();
        // }
        
        $users = $this->user->select('id', 'plan_id', 'first_name', 'last_name', 'email', 'email_verified_at', 'created_at')->with('plan:id,name')->latest()->get();
        $notverified = $this->user->whereNull('email_verified_at')->count();
        $verified = $this->user->whereNotNull('email_verified_at')->count();
        $verifiednotverified = $this->user->count();
        //$visits = $this->visit->select('platform', 'browser', 'ip', 'created_at')->latest()->get();
        //$browsers = $visits->groupBy('browser');
        //$platforms = $visits->groupBy('platform');

        $visits = array();
        $browsers = array();
        $platforms = array();
        
        $subscriptions = $this->subscription->has('user')->latest()->limit(10)->with(['user:id,plan_id,first_name,last_name,email', 'user.plan:id,name'])->get();
        $sale = $this->sale->sum('cost');
        $active_users = DB::table('active_users')->get();
        
        return view('admin.dashboard', compact('users', 'verified', 'notverified', 'verifiednotverified', 'subscriptions', 'visits', 'browsers', 'platforms', 'sale','active_users','last_year_visits'));
    }
    
}
