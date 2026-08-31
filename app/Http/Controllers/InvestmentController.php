<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvestmentController extends Controller
{
    // No Paystack/in-app payment collection here — the investor transfers
    // money by bank app outside this system (see Settings' investment_*
    // bank fields, shown to them before this call), and an admin manually
    // confirms it arrived (confirmPayment below) before any contract exists.
    public function store(Request $request)
    {
        $setting = Setting::current();

        $validated = $request->validate([
            'capital_amount' => ['required', 'numeric', 'min:'.(float) $setting->investment_minimum_amount],
            'tenure_months' => ['required', 'integer', Rule::in($setting->investment_tenures_months)],
        ], [
            'capital_amount.min' => 'The minimum investment is ₦'.number_format((float) $setting->investment_minimum_amount, 2).'.',
            'tenure_months.in' => 'Please choose one of the available tenures.',
        ]);

        $user = $request->user();
        $ratePercent = (float) $setting->investment_monthly_rate_percent;
        $terms = Investment::computeTerms((float) $validated['capital_amount'], (int) $validated['tenure_months'], $ratePercent);

        // Investor identity, rate, and computed terms are all snapshotted at
        // the moment of intent — same reasoning as ProductOrderItem's
        // product snapshot — so neither a later profile edit nor a later
        // admin rate change rewrites the terms this investor agreed to.
        $investment = Investment::create([
            'user_id' => $user->id,
            'capital_amount' => $validated['capital_amount'],
            'tenure_months' => $validated['tenure_months'],
            'rate_percent' => $ratePercent,
            'monthly_return' => $terms['monthly_return'],
            'total_payout' => $terms['total_payout'],
            'investor_name' => $user->name,
            'investor_email' => $user->email,
            'investor_phone' => $user->phone,
            'status' => 'pending',
        ]);

        return response()->json($investment, 201);
    }

    public function mine(Request $request)
    {
        return response()->json(
            $request->user()->investments()->latest()->get()
        );
    }

    public function sign(Request $request, Investment $investment)
    {
        $user = $request->user();

        if ($investment->user_id !== $user->id) {
            abort(403, 'This investment does not belong to you.');
        }

        if ($investment->status !== 'payment_confirmed') {
            throw ValidationException::withMessages([
                'status' => ['This investment is not ready to be signed yet.'],
            ]);
        }

        $validated = $request->validate([
            'signature_name' => ['required', 'string', 'max:255'],
        ]);

        $investment->update([
            'signature_name' => $validated['signature_name'],
            'signed_at' => now(),
            'status' => 'signed',
        ]);

        $this->generateContractPdf($investment);

        return response()->json($investment->fresh());
    }

    // Admin-facing: every row here, filterable by status like
    // AdminProductOrderController::index (default to the actionable queue).
    public function index(Request $request)
    {
        $query = Investment::with('user');

        $status = $request->input('status', 'pending');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json($query->latest()->get());
    }

    // The one manual step in this whole flow: an admin has looked at the
    // bank account and confirmed the investor's transfer actually arrived.
    // Only after this does a contract exist for the investor to sign.
    public function confirmPayment(Request $request, Investment $investment)
    {
        if ($investment->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only a pending investment can have its payment confirmed.'],
            ]);
        }

        $investment->update([
            'status' => 'payment_confirmed',
            'payment_confirmed_at' => now(),
        ]);

        $this->generateContractPdf($investment);

        return response()->json($investment->fresh());
    }

    public function cancel(Request $request, Investment $investment)
    {
        if ($investment->status === 'signed') {
            throw ValidationException::withMessages([
                'status' => ['A signed investment cannot be cancelled here.'],
            ]);
        }

        $investment->update(['status' => 'cancelled']);

        return response()->json($investment->fresh());
    }

    // Shared by confirmPayment (first, unsigned version) and sign (same
    // path, re-rendered with the signature block filled in) — one contract
    // file per investment, overwritten rather than versioned, since only
    // the latest state (unsigned or signed) is ever meaningful.
    private function generateContractPdf(Investment $investment): void
    {
        $logoPath = public_path('images/logo.jpeg');
        $logoDataUri = is_file($logoPath)
            ? 'data:'.(mime_content_type($logoPath) ?: 'image/jpeg').';base64,'.base64_encode(file_get_contents($logoPath))
            : null;

        $pdf = Pdf::loadView('investments.contract', [
            'investment' => $investment,
            'logoDataUri' => $logoDataUri,
        ]);

        $path = 'contracts/investment-'.$investment->id.'.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        $investment->update(['contract_path' => $path]);
    }
}
