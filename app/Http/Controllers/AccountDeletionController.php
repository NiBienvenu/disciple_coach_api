<?php

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountDeletionController extends Controller
{
    public function create(): View
    {
        return view('site.delete-account');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ]);

        $email = strtolower(trim($data['email']));

        // Avoid duplicate open requests for the same email.
        $exists = AccountDeletionRequest::where('email', $email)
            ->where('status', 'pending')
            ->exists();

        if (! $exists) {
            AccountDeletionRequest::create([
                'user_id' => User::where('email', $email)->value('id'),
                'name' => $data['name'],
                'email' => $email,
                'reason' => $data['reason'] ?? null,
                'ip_address' => $request->ip(),
            ]);
        }

        // Same message either way, so the form does not reveal who has an account.
        return redirect()->route('account.delete')->with('sent', true);
    }
}
