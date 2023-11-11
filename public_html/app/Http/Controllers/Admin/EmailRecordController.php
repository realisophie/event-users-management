<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Session;
use DB;
use Carbon\Carbon;

class EmailRecordController extends Controller
{
    
    public function index(Request $req){
        $result = DB::table('zoom_meetings')
            ->select('zoom_meetings.id AS event_id','zoom_meetings.topic','zoom_meetings.user_id','zoom_meetings.created_at','zoom_meetings.location','zoom_meetings.ip_address','zoom_meetings.join_url',
            'invitation_mails.email as i_email','invitation_mails.id as inv_id','invitation_mails.created_at as date_time','users.email as u_email','users.first_name','users.last_name')
            ->join('invitation_mails','zoom_meetings.id','=','invitation_mails.event_id')
            ->join('users','zoom_meetings.user_id','=','users.id');
        
        if($req->has('search_by') && $req->search_by == 1 && $req->has('search')){
            $result->where('zoom_meetings.topic','like','%'.$req->search.'%');    
        }
        
        if($req->has('search_by') && $req->search_by == 2 && $req->has('search')){
            $name = explode(' ',$req->search);
            $f_name = $name[0];
            $l_name = '';
            if(count($name) > 1){
                array_shift($name);
                $l_name = implode(' ',$name);
            }
            $result->where('users.first_name','like','%'.$f_name.'%')->orWhere('users.last_name','like','%'.$l_name.'%');
        }
        
        if($req->has('from') && $req->from != null && $req->has('to') && $req->to != null){
            $result->whereBetween('zoom_meetings.created_at',[Carbon::parse($req->from),Carbon::parse($req->to)]);
        }
        // dd($result->paginate(10));
        $result = $result->paginate(10); 
        
        return view('admin.email_record')->with('result',$result);
    }
    
    public static function ipaddress() {
        //  return view('admin.testing');
         $Ip ="124.109.59.101";
         
        // $new_arr[] = unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip=' . $Ip));
        
        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, "http://www.geoplugin.net/php.gp?ip=124.109.59.101");
        $contents = curl_exec($c);
        curl_close($c);

        // if ($contents) return $contents;
        // else return dd('none');
        
        $new_arr = unserialize($contents);
    
        $outarr = array();
        $outarr['lat'] = $new_arr['geoplugin_latitude'];
        $outarr['lng'] = $new_arr['geoplugin_longitude'];
        $lats =$outarr['lat'];
        $longs =$outarr['lng'];
        

        $c = curl_init();
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_URL, "https://tools.keycdn.com/geo.json?host=".$Ip);
        $contents = curl_exec($c);
        curl_close($c);
        $arr = [];
        if ($contents){ $arr = json_decode($contents)->data->geo->country_name;
        }
        
        $outarr['contry'] = $arr;
                                      
        return $outarr;
    }

    public function delete(Request $req){
        $check = DB::table('invitation_mails')->where('id',$req->id)->delete();
        if($check){
            return ['success'=>true];
        }else{
            return ['error'=>true];
        }
    }
    
    public function deleteAll(Request $req){
        $check = DB::table('invitation_mails')->delete();
        if($check){
            Session::flash('message', ['success'=>'Record deleted.']); 
        }else{
            Session::flash('message', ['danger','Failed to delete.']); 
        }
        return redirect()->back();
    }

    public function exportAll(){
        
        $fileName = 'mail_logs.csv';
        // $tasks = Task::all();
        
        $result = DB::table('zoom_meetings')
            ->select('zoom_meetings.id AS event_id','zoom_meetings.topic','zoom_meetings.user_id','zoom_meetings.created_at','zoom_meetings.location','zoom_meetings.ip_address','zoom_meetings.join_url',
            'invitation_mails.email as i_email','invitation_mails.id as inv_id','invitation_mails.created_at as date_time','users.email as u_email','users.first_name','users.last_name')
            ->join('invitation_mails','zoom_meetings.id','=','invitation_mails.event_id')
            ->join('users','zoom_meetings.user_id','=','users.id');

        $tasks = $result->get(); 
    
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = array('Event ID', 'Event Name', 'Event Manager', 'Event Manager Email', 'Event Date & Time','Country/Location','Ip Address','Registration URL','Sent Mails','Sent Mail Time');

        $callback = function() use($tasks, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($tasks as $task) {
                $row['Event ID']  = $task->event_id;
                $row['Event Name']    = $task->topic;
                $row['Event Manager']    = $task->first_name.' '.$task->last_name;
                $row['Event Manager Email']  = $task->u_email;
                $row['Event Date & Time']  = date('d M Y', strtotime($task->created_at)) .' '. date('H:i A', strtotime($task->created_at));
                
                $c = curl_init();
                curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($c, CURLOPT_URL, "https://tools.keycdn.com/geo.json?host=".$task->ip_address);
                curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'GET');
                $headers_ = array();
                $headers_[] = 'User-Agent: keycdn-tools:https://zmsend.com';
                curl_setopt($c, CURLOPT_HTTPHEADER, $headers_);
                $contents = curl_exec($c);
                
                curl_close($c);
                $contry = "";
                $arr = [];
                if ($contents){ $arr = json_decode($contents)->data->geo->country_name;
                }
                
                $row['Country/Location']  = $arr;
                $row['Ip Address']  = $task->ip_address;
                $row['Registration URL']  = $task->join_url;
                $row['Sent Mails']  = $task->i_email;
                $row['Sent Mail Time']  = date('d M Y', strtotime($task->date_time)) .' '. date('H:i A', strtotime($task->date_time));
                fputcsv($file, array($row['Event ID'], $row['Event Name'], $row['Event Manager'], $row['Event Manager Email'], $row['Event Date & Time'], $row['Country/Location'], $row['Ip Address'], $row['Registration URL'],
                $row['Sent Mails'],$row['Sent Mail Time']));
            }

            fclose($file);
        };
        //dd($callback);
        return response()->stream($callback, 200, $headers);
    }

}