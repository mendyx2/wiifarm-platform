<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Members\MobileVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MobileVerificationController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->mobile_verified_at) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/verify-mobile', [
            'number' => '+'.$user->dial_code.' '.$user->mobile,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function send(Request $request, MobileVerification $verification): RedirectResponse
    {
        $verification->send($request->user());

        return back()->with('status', 'code-sent');
    }

    public function verify(Request $request, MobileVerification $verification): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $problem = $verification->verify($request->user(), $request->string('code')->toString());

        if ($problem) {
            return back()->withErrors(['code' => $problem]);
        }

        return to_route('dashboard');
    }
}
