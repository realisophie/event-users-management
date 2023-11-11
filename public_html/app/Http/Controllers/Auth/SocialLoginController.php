<?php

namespace App\Http\Controllers\Auth;

use Socialite;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;

class SocialLoginController extends Controller
{
    private $user;
    private $plan;

    public function __construct(User $user, Plan $plan)
    {
        $this->user = $user;
        $this->plan = $plan;
    }

    public function redirect($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, $provider)
    {
        $socialUser = Socialite::driver($provider)->stateless()->user();
        $findUserWithPassword = $this->user->whereEmail($socialUser->email)->whereNotNull('password')->first();
        if ($findUserWithPassword) {
            return redirect()->route('login')->withErrors([
                'email' => 'Email has alread been created with password.'
            ]);
        } else if (!$socialUser->email) {
            return redirect()->route('login')->withErrors([
                'email' => 'No email has associated with authenticated account.'
            ]);
        } else {
            // switch ($provider) {
            //     case 'google':
            //         $this->handleUser($socialUser);
            //         break;
            //     case 'facebook':
            //         $this->handleFacebookCallback();
            //         break;
            // }
            $finduser = $this->user->with('socialLogin')->whereEmail($socialUser->email)->first();

            if (!$finduser) {
                
                $redemption_value = $request->session()->get('redemption');
                $selected_plan = $request->session()->get('plan');

                if($redemption_value == 1){
                    if($selected_plan){
                        $freePlan = $this->plan->whereStripeId($selected_plan)->first();
                    }else{
                        $freePlan = $this->plan->where('cost', '<=', 0)->where('limitted_plan',1)->first();
                    }
                }else{
                    $freePlan = $this->plan->where('cost', '<=', 0)->where('main_plan',1)->first();
                }

                $name = explode(" ", $socialUser->name, 2);
                $data =  [
                    'first_name' => $name[0],
                    'last_name' => $name[1],
                    'email' => $socialUser->email
                ];
                if ($freePlan) {
                    $data['plan_id'] = $freePlan->id;
                }
                event(new Registered($user = $this->user->create($data)));
                $request->session()->forget('redemption');
                $request->session()->forget('plan');
                $user->socialLogin()->create([
                    'provider_id' => $socialUser->id,
                    'provider' => $provider
                ]);
                $user->createAsStripeCustomer([
                    'name' => $user->full_name,
                    'email' => $user->email
                ]);
                if ($freePlan) {
                    $user->newSubscription('default', $freePlan->stripe_id)->add();
                }
                auth()->guard('web')->login($user);
            } else {
                if ($provider != $finduser->socialLogin->provider) {
                    return redirect()->route('login')->withErrors([
                        'email' => "Email has authenticated with {$finduser->socialLogin->provider} account."
                    ]);
                }
                auth()->login($finduser);
            }
            return redirect()->route('eventmanager.dashboard');
        }
    }
}
