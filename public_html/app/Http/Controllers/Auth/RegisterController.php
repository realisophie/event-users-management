<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Plan;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Session;

class RegisterController extends Controller
{
    private $user;
    private $plan;
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/account/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Plan $plan, User $user)
    {
        $this->middleware('guest');
        $this->plan = $plan;
        $this->user = $user;
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showRegistrationForm()
    {
        $countries = \DB::table('countries')->select('name', 'phonecode')->get();

        return view('userauth.register', compact('countries'));
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'privacy' => ['required'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_no' => ['required', 'digits_between:8,190', 'numeric'],
            'phone_code' => ['required', 'max:255'],
            'password' => 'required|string|min:8|confirmed|contain_uppercase|contain_lowercase|contain_digit|contain_special_character'
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(array $data)
    {
        // if(isset($data['hdn_limited_plan']) && ($data['hdn_limited_plan'] == 1)){
        //     if(isset($data['hdn_selected_plan'])){
        //         $freePlan = $this->plan->whereStripeId($data['hdn_selected_plan'])->first();
        //     }else{
        //         $freePlan = $this->plan->where('cost', '<=', 0)->where('limitted_plan',1)->first();
        //     }
        // }else{
        //     $freePlan = $this->plan->where('cost', '<=', 0)->where('main_plan',1)->first();
        // }

        $redemption_value = Session::get('redemption');
        $selected_plan = Session::get('plan');

        if($redemption_value == 1){
            if($selected_plan){
                $freePlan = $this->plan->whereStripeId($selected_plan)->first();
            }else{
                $freePlan = $this->plan->where('cost', '<=', 0)->where('limitted_plan',1)->first();
            }
        }else{
            $freePlan = $this->plan->where('cost', '<=', 0)->where('main_plan',1)->first();
        }
        
        $data = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_no' => $data['phone_no'],
            'phone_code' => $data['phone_code'],
            'password' => Hash::make($data['password']),
        ];
        if ($freePlan) {
            $data['plan_id'] = $freePlan->id;
        }
        $user = $this->user->create($data);
        Session::forget('redemption');
        Session::forget('plan');
        $user->createAsStripeCustomer([
            'name' => $user->full_name,
            'email' => $user->email
        ]);
        if ($freePlan) {
            $user->newSubscription('default', $freePlan->stripe_id)->add();
        }
        return $user;
    }
}
