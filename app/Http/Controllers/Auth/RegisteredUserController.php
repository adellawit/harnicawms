<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Support dua gaya payload:
        //  - mode admin: name (full name, akan di-split jadi first/last)
        //  - mode form terpisah: first_name + last_name
        $hasSplitName = $request->filled('first_name');

        $rules = [
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class, 'username')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];

        if ($hasSplitName) {
            $rules['first_name'] = ['required', 'string', 'max:100'];
            $rules['last_name'] = ['nullable', 'string', 'max:100'];
            $rules['email'] = ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')];
            $rules['phone'] = ['required', 'string', 'max:30'];
        } else {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['email'] = ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')];
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        }

        // Hindari string kosong di jalur backoffice (unique + nullable lebih aman dengan null).
        if (! $request->filled('email')) {
            $request->merge(['email' => null]);
        }
        if (! $request->filled('phone')) {
            $request->merge(['phone' => null]);
        }

        $request->validate($rules);

        if ($hasSplitName) {
            $firstName = trim($request->string('first_name'));
            $lastName = trim($request->string('last_name')) ?: $firstName;
        } else {
            $trimmed = trim($request->name);
            $parts = preg_split('/\s+/', $trimmed, 2, PREG_SPLIT_NO_EMPTY) ?: [$trimmed];
            $firstName = $parts[0];
            $lastName = $parts[1] ?? $parts[0];
        }

        $email = $request->filled('email') ? strtolower(trim($request->string('email'))) : null;
        $phone = $request->filled('phone') ? trim($request->string('phone')) : null;

        $user = User::create([
            'role_id' => 'c398e914-b34c-4839-bd4f-3025c9c7da67',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $request->username,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect('/dashboard');
    }
}
