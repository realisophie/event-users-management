<?php

namespace App\Http\Controllers\Admin;

use Str;
use App\Models\Plan;
use Stripe\StripeClient;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Stripe\Exception\InvalidRequestException;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiConnectionException;

class PlanController extends Controller
{
    private $model;
    private $stripe;
    private $view = "admin.plan";
    private $route = "admin.plan";
    private $titles = [
        'plural' => 'plans',
        'singular' => 'plan'
    ];

    public function __construct(Plan $model)
    {
        $this->model = $model;
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $title = ucfirst($this->titles['plural']);
        $route = $this->route;

        $plans = $this->model->orderBy('order_by')->latest()->get();
        return view($this->view . '.index', compact('plans', 'title', 'route'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $title = ucfirst($this->titles['singular']);
        $route = $this->route;
        return view($this->view . '.create', compact('title', 'route'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|max:190|alpha|unique:plans",
            "interval" => "required|max:190",
            "interval_count" => "required|numeric|min:0",
            "cost" => "required|numeric",
            "order_by" => "required|numeric",
            "custom_url" => "nullable|boolean",
            "custom_e_invites" => "nullable|boolean",
            "allowed_events" => "required|numeric|min:0",
            "allowed_e_invites" => "required|numeric|min:0",
            "allowed_sms_invites" => "required|numeric|min:0",
            "allowed_whatsapp_invites" => "required|numeric|min:0",
            "percentage_per_ticket_sold" => "required|numeric|min:0|max:100",
        ]);

        $id = Str::slug($validated['name']);
        $custom_url = (isset($validated['custom_url']) && $validated['custom_url']) ? 'Yes' : 'No';
        $custom_e_invites = (isset($validated['custom_e_invites']) && $validated['custom_e_invites']) ? 'Yes' : 'No';
        if ($validated['allowed_events'] == 0) {
            $allowed_events = 'Unlimited';
        } else {
            $allowed_events = $validated['allowed_events'];
        }
        try {
            $plan  = $this->stripe->plans->create(array(
                "amount" => $validated['cost'] * 100,
                "interval" => $validated['interval'],
                "product" => array(
                    "name" => $validated['name'],
                    'metadata' => [
                        'Custom URL' => $custom_url,
                        'Custom E-Invites' => $custom_e_invites,
                        'Allowed Events' => $allowed_events,
                        'Allowed E-Invites per Event' => $validated['allowed_e_invites'],
                        'Allowed SMS-Invites per Event' => $validated['allowed_sms_invites'],
                        'Allowed Whatsapp-Invites per Event' => $validated['allowed_whatsapp_invites'],
                    ]
                ),
                'interval_count' => $validated['interval_count'],
                "currency" => "usd",
                "id" => $id
            ));
        } catch (InvalidRequestException $ex) {
            throw ValidationException::withMessages([
                'dummy' => $ex->getMessage(),
            ]);
        } catch (ApiConnectionException $ex) {
            throw ValidationException::withMessages([
                'dummy' => $ex->getMessage(),
            ]);
        }

        $validated['stripe_id'] = $id;
        $validated['stripe_product_id'] = $plan['product'];

        $input = $request->all();
        if(isset($input['main_plan'])){
            $validated['main_plan'] = 1;
        }
        if(isset($input['limitted_plan'])){
            $validated['limitted_plan'] = 1;
        }

        $this->model->create($validated);

        $title = $this->titles['singular'];

        return redirect()->route($this->route . '.index')->with([
            'type' => 'success',
            'title' => ucfirst($title) . " Created!",
            'message' => "The $title has been created successfully"
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $plan = $this->model->select('id', 'stripe_id', 'stripe_product_id')->findorFail($id);
        try {
            $this->stripe->plans->delete(
                $plan->stripe_id,
                []
            );
            $this->stripe->products->delete(
                $plan->stripe_product_id,
                []
            );
        } catch (InvalidRequestException $ex) {
            return redirect()->route($this->route . '.index')->with([
                'type' => 'danger',
                'title' => "An Error Occured",
                'message' => $ex->getMessage()
            ]);
        } catch (ApiConnectionException $ex) {
            return redirect()->route($this->route . '.index')->with([
                'type' => 'danger',
                'title' => "An Error Occured",
                'message' => $ex->getMessage()
            ]);
        }
        $plan->delete();

        $title = $this->titles['singular'];

        return redirect()->route($this->route . '.index')->with([
            'type' => 'success',
            'title' => ucfirst($title) . " Deleted!",
            'message' => "The $title has been deleted successfully"
        ]);
    }
}
