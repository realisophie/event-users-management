<?php

namespace App\Http\Controllers\Admin;

use Mail;
use App\Models\Subscriber;
use App\Mail\NewsletterMail;
use App\Exports\SubscribersExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;

class NewsletterController extends Controller
{
    private $model;
    private $view = "admin.newsletter";
    private $route = "admin.newsletter";
    private $titles = [
        'plural' => 'newsletters',
        'singular' => 'newsletter'
    ];

    public function __construct(Subscriber $model)
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

        $items = $this->model->select('id', 'email', 'created_at')->latest()->get();
        return view($this->view . '.index', compact('items', 'title', 'route'));
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
            'subject' => 'required|string|max:250',
            'message' => 'required',
        ]);

        $subscribers = $this->model->select('email')->get();

        foreach ($subscribers as $subscriber) {
            Mail::to($subscriber->email)->send(new NewsletterMail($validated));
        }

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
        $admin = $this->model->select('id')->findorFail($id);
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
        return Excel::download(new SubscribersExport, 'subscribers.csv');
    }
}
