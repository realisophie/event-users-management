<?php

namespace App\Http\Controllers\Admin;

use Str;
use App\Models\Blog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BlogController extends Controller
{
    private $model;
    private $view = "admin.blog";
    private $route = "admin.blog";
    private $titles = [
        'plural' => 'blogs',
        'singular' => 'blog'
    ];

    public function __construct(Blog $model)
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

        $blogs = $this->model->latest()->select('id', 'title', 'short_description', 'slug')->latest()->get();
        return view($this->view . '.index', compact('blogs', 'title', 'route'));
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
            'title' => 'required|string|max:190|unique:blogs',
            'short_description' => 'required',
            'body' => 'required',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $this->model->create($validated);

        $title = $this->titles['singular'];

        return redirect()->route($this->route . '.index')->with([
            'type' => 'success',
            'title' => ucfirst($title) . " Created!",
            'message' => "The $title has been created successfully"
        ]);
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

        $blog = $this->model->findorFail($id);

        return view($this->view . '.edit', compact('blog', 'title', 'route'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:190||unique:blogs,title,' . $id,
            'short_description' => 'required',
            'body' => 'required',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $blog = $this->model->findorFail($id);
        $blog->update($validated);

        $title = $this->titles['singular'];

        return redirect()->route($this->route . '.index')->with([
            'type' => 'success',
            'title' => ucfirst($title) . " Updated!",
            'message' => "The $title has been updated successfully"
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
        $blog = $this->model->select('id')->findorFail($id);
        $blog->delete();

        $title = $this->titles['singular'];

        return redirect()->route($this->route . '.index')->with([
            'type' => 'success',
            'title' => ucfirst($title) . " Deleted!",
            'message' => "The $title has been deleted successfully"
        ]);
    }
}
