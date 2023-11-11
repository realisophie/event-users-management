<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use DB;

class ActiveUserController extends Controller{
    
    public function save(Request $request){
        if(auth()->check()){
            $c = curl_init();
            curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($c, CURLOPT_URL, "http://www.geoplugin.net/php.gp?ip=".$request->ip());
            $contents = curl_exec($c);
            curl_close($c);
            
            $new_arr = unserialize($contents);
        
            $outarr = array();
            $outarr['lat'] = $new_arr['geoplugin_latitude'];
            $outarr['lng'] = $new_arr['geoplugin_longitude'];
            $lats =$outarr['lat'];
            $longs =$outarr['lng'];
            
    
            // $c = curl_init();
            // curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
            // curl_setopt($c, CURLOPT_URL, "https://tools.keycdn.com/geo.json?host=".$request->ip());
            // $contents = curl_exec($c);
            // curl_close($c);
            // $arr = [];
            // if ($contents){ $arr = json_decode($contents)->data->geo->country_name;
            // }
            
            $outarr['contry'] = "Pakistan";
        
            $outarr['user_id'] = auth()->user()->id;
            
            $check = DB::table('active_users')->select('*')->where('user_id',$outarr['user_id'])->first();
            
            $now = Carbon::now();
            DB::table('active_users')->where('time','!=',$now->format('H:i'))->delete();
            if($check){
                DB::table('active_users')->where('id',$check->id)->update([
                    'ip' => $request->ip(),
                    'lat' => $outarr['lat'],
                    'lng' => $outarr['lng'],
                    'user_id' => $outarr['user_id'],
                    'contry' => $outarr['contry'],
                    'time' => $now->format('H:i')
                ]);
            }else{
                DB::table('active_users')->insert([
                    'ip' => $request->ip(),
                    'lat' => $outarr['lat'],
                    'lng' => $outarr['lng'],
                    'user_id' => $outarr['user_id'],
                    'contry' => $outarr['contry'],
                    'time' => $now->format('H:i')
                ]);
            }
        }
        return ['success'=>'true'];
    }
    
    public function getUsers(){
        return DB::table('active_users')->select('*')->where('user_id',$outarr['user_id'])->get();
    }
    
}