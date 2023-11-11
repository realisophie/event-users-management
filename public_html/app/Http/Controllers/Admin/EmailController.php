<?php

namespace App\Http\Controllers\Admin;

use App\Models\Email;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EmailController extends Controller
{
    private $model;
    private $view = "admin.email";
    private $route = "admin.email";
    private $titles = [
        'plural' => 'emails',
        'singular' => 'email'
    ];

    public function __construct(Email $model)
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

        $emails = $this->model->all();
        return view($this->view . '.index', compact('emails', 'title', 'route'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $title = ucfirst($this->titles['singular']);
        $route = $this->route;

        $email = $this->model->findorFail($id);

        return view($this->view . '.edit', compact('email', 'title', 'route'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            "message" => "required",
            "subject" => "required",
        ]);

        $item = $this->model->findorFail($id);
        $item->update($data);

        $title = $this->titles['singular'];

        return redirect()->route($this->route . '.index')->with([
            'type' => 'success',
            'title' => ucfirst($title) . " Updated!",
            'message' => "The $title has been updated successfully"
        ]);
    }
}
