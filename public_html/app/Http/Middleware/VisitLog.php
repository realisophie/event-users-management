<?php

namespace App\Http\Middleware;

use Closure;
use DB;
use Carbon\Carbon;

class VisitLog
{
    
    public function handle($request, Closure $next)
    {
        // if(!$request->ajax()){
        //     $now = Carbon::now();
        //     if(auth()->check()){
        //             $check = DB::table('visit_log')->whereDate('created_at',$now)->where('ip',$request->ip())->where('user_id',auth()->user()->id)->first();
        //             $contry = '';
        //             $c = curl_init();
        //             curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        //             curl_setopt($c, CURLOPT_URL, "https://tools.keycdn.com/geo.json?host=".$request->ip());
        //             $contents = curl_exec($c);
        //             curl_close($c);
        //             if ($contents){
        //                 $contry = json_decode($contents)->data->geo->country_name;
        //             }
        //             if(!$check){
        //                 DB::table('visit_log')->insert([
        //                     'ip' => $request->ip(),
        //                     'user_id' => auth()->user()->id,
        //                     'email' => auth()->user()->email,
        //                     'contry' => $contry,
        //                     'created_at' => $now,
        //                     'updated_at' => $now
        //                 ]);
        //             }
        //         }else{
        //             $check = DB::table('visit_log')->whereDate('created_at',$now)->where('ip',$request->ip())->whereRaw('user_id = null')->first();
        //             $contry = '';
        //             $c = curl_init();
        //             curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        //             curl_setopt($c, CURLOPT_URL, "https://tools.keycdn.com/geo.json?host=".$request->ip());
        //             $contents = curl_exec($c);
        //             curl_close($c);
        //             if ($contents){
        //                 $contry = json_decode($contents)->data->geo->country_name;
        //             }
        //             if(!$check){
        //                 DB::table('visit_log')->insert([
        //                     'ip' => $request->ip(),
        //                     'contry' => $contry,
        //                     'created_at' => $now,
        //                     'updated_at' => $now
        //                 ]);
        //             }
        //         }
        // }
        return $next($request);
    }
}