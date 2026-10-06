<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Privacy\Services\DeleteAccount;
use App\Domain\Privacy\Services\PersonalDataExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AccountDataController extends Controller
{
    public function export(Request $request, PersonalDataExport $export): StreamedResponse
    {
        $request->validate(['export_password' => ['required', 'current_password:web'], 'include_sensitive_data' => ['required', 'accepted']]);
        return $export->download($request->user());
    }

    public function destroy(Request $request, DeleteAccount $delete): RedirectResponse
    {
        $request->validate(['delete_password' => ['required', 'current_password:web'], 'confirm_delete_account' => ['required', 'accepted']]);
        $delete->handle($request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return to_route('login')->with('status', __('privacy.account_deleted'));
    }
}
