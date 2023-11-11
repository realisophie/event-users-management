<?php

namespace App\Http\Controllers\Auth;

use App\Models\Plan;
use App\Models\Sale;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PaymentController extends Controller
{
    private $plan;
    private $sale;

    public function __construct(Plan $plan, Sale $sale)
    {
        $this->plan = $plan;
        $this->sale = $sale;
    }

    public function index(Request $request, $plan)
    {
        $plan = $this->plan->whereStripeId($plan)->first();
        $user = auth()->user();
        if (!$plan) {
            return redirect()->route('frontend.pricing');
        }
        if ($user->plan_id == $plan->id) {
            return redirect()->route('frontend.pricing')->with([
                'type' => 'info',
                'title' => "Already Subscribed!",
                'message' => "You have already subscribed selected plan. Select a different one."
            ]);
        }
        $intent = $user->createSetupIntent();
        return view('eventmanager.payment', compact('user', 'intent', 'plan'));
    }

    public function subscribe(Request $request, $plan)
    {
        $validated = $request->validate([
            'paymentMethod' => 'required',
        ]);

        $plan = $this->plan->whereStripeId($plan)->first();
        $user = auth()->user();
        if (!$plan) {
            return redirect()->route('frontend.pricing');
        }
        if ($user->plan_id == $plan->id) {
            return redirect()->route('frontend.pricing')->with([
                'type' => 'info',
                'title' => "Already Subscribed!",
                'message' => "You have already subscribed selected plan. Select a different one."
            ]);
        }

        $user->updateDefaultPaymentMethod($validated['paymentMethod']);
        $subscriptionItem = $user->subscription('default')->items->first();
        $subscriptionItem->delete();
        $user->subscription('default')->swap($plan->stripe_id);

        $input = $request->all();

        $redemption_code = '';
        if(isset($input['redemption_code'])){
            $redemption_code = $input['redemption_code'];
        }

        if(isset($redemption_code) && !empty($redemption_code)){
            $user->update([
                'plan_id' => $plan->id,
                'redemption_code' => $redemption_code
            ]);
        }else{
            $user->update([
                'plan_id' => $plan->id
            ]);
        }
        
        $this->sale->create([
            'plan' => $plan->name,
            'cost' => $plan->cost,
        ]);

        return redirect()->route('eventmanager.dashboard')->with([
            'type' => 'success',
            'title' => 'Plan Subscribed',
            'message' => 'Plan has been successfully subscribed.'
        ]);
    }

    public function unSubscribe(Request $request)
    {
        $plan = $this->plan->where('cost', '<=', 0)->first();
        $user = auth()->user();
        if (!$plan) {
            return redirect()->route('eventmanager.dashboard');
        }
        if ($user->plan_id == $plan->id) {
            return redirect()->route('eventmanager.dashboard')->with([
                'type' => 'info',
                'title' => "Already Subscribed!",
                'message' => "You have already subscribed selected plan. Select a different one."
            ]);
        }
        $subscriptionItem = $user->subscription('default')->items->first();
        $subscriptionItem->delete();
        $user->subscription('default')->swap($plan->stripe_id);

        $user->update([
            'plan_id' => $plan->id
        ]);

        return redirect()->route('eventmanager.dashboard')->with([
            'type' => 'success',
            'title' => 'Plan Unsubscribed',
            'message' => 'Plan has been successfully unsubscribed.'
        ]);
    }
}
