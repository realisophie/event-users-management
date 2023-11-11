<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    private $model;
    private $view = "admin.user";
    private $route = "admin.user";
    private $titles = [
        'plural' => 'users',
        'singular' => 'user'
    ];

    public function __construct(User $model)
    {
        $this->model = $model;
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

        $users = $this->model->select('id', 'plan_id', 'first_name', 'last_name', 'email', 'phone_code', 'phone_no', 'email_verified_at', 'created_at','redemption_code')->latest()->with('plan:id,name')->latest()->get();
        return view($this->view . '.index', compact('users', 'title', 'route'));
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $admin = $this->model->findorFail($id);
        $admin->delete();

        $title = $this->titles['singular'];

        return redirect()->route($this->route . '.index')->with([
            'type' => 'success',
            'title' => ucfirst($title) . " Deleted!",
            'message' => "The $title has been deleted successfully"
        ]);
    }

    public function export() 
    {
        return Excel::download(new UsersExport, 'users.csv');
    }
}
