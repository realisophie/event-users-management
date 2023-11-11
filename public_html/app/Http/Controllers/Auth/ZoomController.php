<?php

namespace App\Http\Controllers\Auth;

use GuzzleHttp\Client;
use App\Models\ZoomToken;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ZoomController extends Controller
{
    private $client;
    private $zoomToken;

    public function __construct(Client $client, ZoomToken $zoomToken)
    {
        $this->client = $client;
        $this->zoomToken = $zoomToken;
    }

    public function redirect()
    {
        return redirect()->to('https://zoom.us/oauth/authorize?response_type=code&client_id=' . config('services.zoom.key') . '&redirect_uri=' . route('zoom.callback'));
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'payload.user_id' => 'required'
        ]);

        $token = $request->headers->get('authorization');
        // if ($token == 'b12sPnhtSn2SD-V6d7mpTw') {
            $zoomToken = $this->zoomToken->whereZoomId($data['payload']['user_id'])->first();
            if ($zoomToken) {
                $id = $zoomToken->zoom_id;
                $zoomToken->delete();
            }

            if (isset($data['payload']['user_data_retention']) && $data['payload']['user_data_retention'] == 'false') {
                try {
                    $params = [
                        'client_id' => $data['payload']['client_id'],
                        'account_id' => $data['payload']['account_id'],
                        'user_id' => $id,
                        'deauthorization_event_received' => $data['payload'],
                        'compliance_completed' => true
                    ];
                    $this->client->request('POST', 'https://api.zoom.us/oauth/data/compliance', [
                        "headers" => [
                            "Authorization" => "Basic " . base64_encode(config('services.zoom.key') . ':' . config('services.zoom.secret'))
                        ],
                        'json' => $params,
                    ]);
                } catch (\Throwable $th) {
                }
            }
        // }
        return response()->json();
    }

    public function callback(Request $request)
    {
        try {
            $token = $this->getToken($request->input('code'));

            $response = $this->client->request('GET', 'https://api.zoom.us/v2/users/me', [
                "headers" => [
                    "Authorization" => "Bearer " . $token['access_token'],
                ],
            ]);
            $zoomuser = json_decode($response->getBody()->getContents(), true);

            $alreadyConnected = auth()->user()->zoomToken()->getModel()->whereZoomEmail($zoomuser['email'])->first();
            if ($alreadyConnected) {
                return redirect()->route('eventmanager.profile')->with([
                    'type' => 'error',
                    'title' => 'Already Connected',
                    'message' => 'ZOOM account has been already connected with other account.'
                ]);
            } else {
                $data = [
                    'zoom_id' => $zoomuser['id'],
                    'zoom_email' => $zoomuser['email'],
                    'access_token' => $token['access_token'],
                    'refresh_token' => $token['refresh_token'],
                ];
                auth()->user()->zoomToken()->create($data);
                return redirect()->route('eventmanager.profile')->with([
                    'type' => 'success',
                    'title' => 'Successfully Added',
                    'message' => 'ZOOM account has been successfully added.'
                ]);
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function refreshToken()
    {
        $user = auth()->user();
        $token = $this->getToken(null, $user->zoomToken->refresh_token);
        $zoomToken = $user->zoomToken;
        $zoomToken->access_token = $token['access_token'];
        $zoomToken->refresh_token = $token['refresh_token'];
        $zoomToken->save();
        $user->refresh();
    }

    public function getToken($code, $refreshtoken = null)
    {
        $params = [];
        if ($code) {
            $params["grant_type"] = 'authorization_code';
            $params["code"] = $code;
            $params["redirect_uri"] = route('zoom.callback');
        } else {
            $params['grant_type'] = 'refresh_token';
            $params['refresh_token'] = $refreshtoken;
        }
        $response = $this->client->request('POST', 'https://zoom.us/oauth/token', [
            "headers" => [
                "Authorization" => "Basic " . base64_encode(config('services.zoom.key') . ':' . config('services.zoom.secret'))
            ],
            'form_params' => $params,
        ]);
        $token = json_decode($response->getBody()->getContents(), true);
        return $token;
    }
}
