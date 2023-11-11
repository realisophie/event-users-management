<?php

namespace App\Http\Controllers\Auth;

use App\Models\Profile;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Hashing\Hasher as Hash;

class ProfileController extends Controller
{
    private $hash;

    public function __construct(Hash $hash)
    {
        $this->hash = $hash;
    }

    public function dashboard()
    {
        $user = auth()->user();
        return view('eventmanager.dashboard', compact('user'));
    }

    public function index()
    {
        $user = auth()->user();
        return view('eventmanager.profile', compact('user'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = [];
        if ($user->password && $request->new_password) {
            $validated = $request->validate([
                'first_name' => 'required|string|max:190|alpha',
                'last_name' => 'required|string|max:190|alpha',
                'website' => 'nullable|url',
                'short_description' => 'required',
                'public_url' => 'required|unique:zoom_meetings|unique:profiles,public_url,' . $user->profile->id . '|not_in:pricing,blog,terms,privacy,zoomintegration,support,contact,help,cron',
                'image' => 'nullable|string',
                'photo' => 'nullable|string',
                'old_password' => 'password:web',
                'new_password' => 'required|string|min:8|confirmed|contain_uppercase|contain_lowercase|contain_digit|contain_special_character'
            ]);
            if ($validated['new_password']) {
                $user->password = $this->hash->make($validated['new_password']);
            }
        } else {
            $validated = $request->validate([
                'first_name' => 'required|string|max:190|alpha',
                'last_name' => 'required|string|max:190|alpha',
                'website' => 'nullable|url',
                'short_description' => 'required',
                'public_url' => 'required|unique:zoom_meetings|unique:profiles,public_url,' . $user->profile->id . '|not_in:pricing,blog,terms,privacy,zoomintegration,support,contact,help,cron',
                'image' => 'nullable|string',
                'photo' => 'nullable|string',
            ]);
        }

        if (isset($validated['image']) && !filter_var($validated['image'], FILTER_VALIDATE_URL)) {
            $validated['avatar'] = $this->savePhoto($validated['image']);
            if ($user->profile->avatar) {
                \Storage::disk('public_folder')->delete($user->profile->avatar);
            }
        }

        if (isset($validated['photo']) && !filter_var($validated['photo'], FILTER_VALIDATE_URL)) {
            $validated['cover_photo'] = $this->savePhoto($validated['photo']);
            if ($user->profile->cover_photo) {
                \Storage::disk('public_folder')->delete($user->profile->cover_photo);
            }
        }

        $user->profile()->getModel()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->save();

        return redirect()->route('eventmanager.profile')->with([
            'type' => 'success',
            'title' => 'Profile Updated',
            'message' => 'Profile has been successfully updated.'
        ]);
    }

    private function savePhoto($image)
    {
        try {
            list($mime, $data)   = explode(';', $image);
            list(, $data)       = explode(',', $data);
            $data = base64_decode($data);

            $mime = explode(':', $mime)[1];
            $ext = explode('/', $mime)[1];
            $name = mt_rand() . time();
            $savePath = 'uploads/' . $name . '.' . $ext;

            file_put_contents($savePath, $data);
            //file_put_contents(public_path() . '/' . $savePath, $data);

            return $savePath;
        } catch (\Exception $e) {
            //doing nothing here for not breaking the loop
            // you can pass the error message to your view if you want.
        }
    }

    public function payout()
    {
        $countries = \DB::table('countries')->select('name')->get();
        $user = auth()->user();
        $account = $user->load('account');
        $account = $user->account;
        return view('eventmanager.payout', compact('countries', 'account'));
    }

    public function payoutPost(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:190',
            'last_name' => 'required|string|max:190',
            'type' => 'required|in:Individual,Company',
            'country' => 'required',
            'address' => 'required',
        ]);

        if ($data['type'] == 'Company') {
            $company = $request->validate([
                'company_name' => 'required|max:190'
            ]);
            $data['company_name'] = $company['company_name'];
        } else {
            $data['company_name'] = NULL;
        }

        $user = auth()->user();
        $user->account()->updateOrCreate(
            ['user_id' => $user->id],
            $data
        );
        return redirect()->back()->with([
            'type' => 'success',
            'title' => "Account Detail Updated",
            'message' => "Your account details have been updated successfully"
        ]);
    }
}
