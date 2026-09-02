<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class HelpController extends Controller
{
    public function index(): View
    {
        abort_unless(backpack_user() !== null, 403);

        return view('admin.help.index', [
            'user' => backpack_user(),
        ]);
    }
}
