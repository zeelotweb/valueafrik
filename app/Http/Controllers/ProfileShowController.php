<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;

class ProfileShowController extends Controller
{
    public function __invoke(User $user): View
    {
        $user->load(['profile', 'languages', 'heritages', 'interests']);

        return view('profile.show', ['user' => $user]);
    }
}
