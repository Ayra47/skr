<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class CommunitiesController extends Controller
{
    public function index(): View
    {
        return view('pages.communities.index');
    }
}
