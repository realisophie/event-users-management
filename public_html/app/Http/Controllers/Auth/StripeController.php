<?php

namespace App\Http\Controllers\Auth;

use Stripe\OAuth;
use Stripe\Stripe;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StripeController extends Controller
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function redirect()
    {
        return redirect()->to('https://dashboard.stripe.com/oauth/authorize?response_type=code&scope=read_write&suggested_capabilities[]=transfers&redirect_uri=' . route('stripe.callback') . '&client_id=' . config('services.stripe.connectkey'));
    }

    public function callback(Request $request)
    {
        $error = $request->input('error');
        if ($error) {
            return redirect()->route('eventmanager.payout')->with([
                'type' => 'danger',
                'title' => 'Unauthorized',
                'message' => 'Authorization failed.'
            ]);
        } else {
            try {
                $token = $this->getToken($request->input('code'));

                $data = [
                    'stripe_id' => $token['stripe_user_id'],
                    'access_token' => $token['access_token'],
                    'refresh_token' => $token['refresh_token'],
                ];
                auth()->user()->stripeToken()->create($data);
                return redirect()->route('eventmanager.payout')->with([
                    'type' => 'success',
                    'title' => 'Successfully Added',
                    'message' => 'Stripe account has been successfully added.'
                ]);
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    }

    public function getToken($code, $refreshtoken = null)
    {
        $params = [];
        if ($code) {
            $params["grant_type"] = 'authorization_code';
            $params["code"] = $code;
        } else {
            $params['grant_type'] = 'refresh_token';
            $params['refresh_token'] = $refreshtoken;
        }
        $response = $this->client->request('POST', 'https://connect.stripe.com/oauth/token', [
            'auth' => [config('services.stripe.secret'), ''],
            "form_params" => $params,
        ]);
        $token = json_decode($response->getBody()->getContents(), true);
        return $token;
    }

    public function remove()
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            OAuth::deauthorize([
                'client_id' => config('services.stripe.connectkey'),
                'stripe_user_id' => auth()->user()->stripeToken->stripe_id,
            ]);
        } catch (\Throwable $th) {
        }

        auth()->user()->stripeToken()->delete();


        return redirect()->back()->with([
            'type' => 'success',
            'title' => "Account Disconnected",
            'message' => "Your stripe account has been disconnected successfully"
        ]);
    }
}
