<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    
    public function index(Request $request){
        $log = DB::table('visit_log')->groupBy('contry');;
        if($request->has('contry') && $request->query('contry') != "all"){
            $log->where('contry',$request->query('contry'));
        }
        if($request->has('from') && $request->query('from') != null && $request->has('to') && $request->query('to') != null){
            $log->whereBetween('created_at',[Carbon::parse($request->from),Carbon::parse($request->to)]);
        }
        $log = $log->get();
        $contry = DB::table('visit_log')->groupBy('contry')->get('contry');
        
        return view('admin.analytics')->with('data',$log)->with('list',$contry);
    }
    
    public function details(Request $request){
        $log = DB::table('visit_log');
        if($request->has('contry') && $request->query('contry') != "all"){
            $log->where('contry',$request->query('contry'));
        }
        if($request->has('from') && $request->query('from') != null && $request->has('to') && $request->query('to') != null){
            $log->whereBetween('created_at',[Carbon::parse($request->from),Carbon::parse($request->to)]);
        }
        $log = $log->get();
        $contry = DB::table('visit_log')->distinct('contry')->get('contry');
        return view('admin.analytics_details')->with('data',$log)->with('list',$contry);
    }
    
    public function delete(Request $request){
        if($request->has('contry')){
            if(DB::table('visit_log')->where('contry',$request->contry)->delete()){
                return ['success'=>'true']; 
            }else{
                return ['error'=>'true']; 
            }
        }else{
            return ['error'=>'true'];
        }
    }
    
}