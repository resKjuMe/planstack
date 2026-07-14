<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ApiDocsController extends Controller
{
    /**
     * Public, login-free reference for the Planstack REST API. The page is a
     * static hand-maintained mirror of routes/api.php and the API resources.
     */
    public function __invoke(): View
    {
        return view('api.docs');
    }
}
