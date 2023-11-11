<?php
namespace App\Libraries;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class ZoomClient
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function createMeeting($data)
    {
        try {
            $response = $this->client->request('POST', 'https://api.zoom.us/v2/users/me/meetings', [
                "headers" => [
                    "Authorization" => "Bearer " . auth()->user()->zoomToken->access_token,
                    'Content-Type' => 'application/json'
                ],
                'json' => $data
            ]);
        } catch (ClientException $e) {
            $code = $e->getCode();
            if ($code == 401) {
                $this->refreshToken();
                return $this->createMeeting($data);
            } else {
                return response()->back()->with([
                    'type' => 'error',
                    'title' => 'Server Error',
                    'message' => 'Something went wrong.'
                ]);
            }
        }
        $meeting = json_decode($response->getBody()->getContents(), true);
        cache()->put('meeting', $meeting);
        return $meeting;
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
            $params["redirect_uri"] = config('services.zoom.redirect');
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
