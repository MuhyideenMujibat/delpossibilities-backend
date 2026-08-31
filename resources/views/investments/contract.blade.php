<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'DejaVu Sans', sans-serif; color: #0b1e33; font-size: 12px; margin: 0; }
  .header { background-color: #0b1e33; padding: 18px 28px; }
  .header table { width: 100%; }
  .header img { height: 32px; }
  .header .brand { color: #ffffff; font-size: 15px; font-weight: bold; letter-spacing: 0.04em; }
  .content { padding: 24px 28px; }
  .eyebrow { color: #8a9a5b; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em; margin: 0 0 4px; }
  h1 { font-size: 18px; margin: 0 0 4px; color: #0b1e33; }
  .subtitle { color: #64748b; font-size: 11px; margin: 0 0 16px; }
  .notice { background: #fef3e6; border: 1px solid #f0d9b5; color: #7a4a12; font-size: 10px; padding: 10px 12px; margin-bottom: 16px; }
  table.details { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 16px; }
  table.details td { padding: 6px 0; border-bottom: 1px solid #eef0f2; color: #334155; }
  table.details td.label { color: #64748b; width: 45%; }
  table.details td.value { text-align: right; font-weight: bold; color: #0b1e33; }
  .clause { font-size: 11px; color: #334155; line-height: 1.6; margin-bottom: 10px; }
  .clause strong { color: #0b1e33; }
  .signature-block { margin-top: 28px; border-top: 1px solid #eef0f2; padding-top: 16px; }
  .signature-name { font-family: 'DejaVu Sans', cursive; font-size: 20px; color: #0b1e33; border-bottom: 1px solid #334155; display: inline-block; padding: 4px 12px; min-width: 220px; }
  .footer { margin-top: 28px; color: #94a3b8; font-size: 10px; text-align: center; }
</style>
</head>
<body>
  <div class="header">
    <table>
      <tr>
        <td style="width: 40px;">
          @if($logoDataUri)
            <img src="{{ $logoDataUri }}" alt="Logo">
          @endif
        </td>
        <td class="brand">D'EL-POSSIBILITIES</td>
      </tr>
    </table>
  </div>

  <div class="content">
    <p class="eyebrow">Investment Contract</p>
    <h1>Investment #{{ $investment->id }}</h1>
    <p class="subtitle">Generated {{ now()->format('j M Y, g:i A') }}</p>

    <div class="notice">
      This document is a template summary of the investment terms agreed between the investor and
      D'EL-Possibilities and does not constitute independently reviewed legal advice. Both parties are
      encouraged to have this reviewed by their own legal counsel before relying on it.
    </div>

    <table class="details">
      <tr>
        <td class="label">Investor</td>
        <td class="value">{{ $investment->investor_name }}</td>
      </tr>
      <tr>
        <td class="label">Email</td>
        <td class="value">{{ $investment->investor_email }}</td>
      </tr>
      <tr>
        <td class="label">Phone</td>
        <td class="value">{{ $investment->investor_phone }}</td>
      </tr>
      <tr>
        <td class="label">Capital invested</td>
        <td class="value">&#8358;{{ number_format((float) $investment->capital_amount, 2) }}</td>
      </tr>
      <tr>
        <td class="label">Tenure</td>
        <td class="value">{{ $investment->tenure_months }} month(s)</td>
      </tr>
      <tr>
        <td class="label">Monthly rate</td>
        <td class="value">{{ rtrim(rtrim(number_format((float) $investment->rate_percent, 2), '0'), '.') }}%</td>
      </tr>
      <tr>
        <td class="label">Monthly return</td>
        <td class="value">&#8358;{{ number_format((float) $investment->monthly_return, 2) }} / month</td>
      </tr>
      <tr>
        <td class="label">Total payout at maturity</td>
        <td class="value">&#8358;{{ number_format((float) $investment->total_payout, 2) }}</td>
      </tr>
      <tr>
        <td class="label">Payment confirmed</td>
        <td class="value">{{ optional($investment->payment_confirmed_at)->format('j M Y, g:i A') ?? '—' }}</td>
      </tr>
    </table>

    <p class="clause">
      <strong>1. Capital.</strong> The investor has transferred the amount above directly to D'EL-Possibilities'
      designated bank account, and D'EL-Possibilities confirms receipt of this amount as of the date noted above.
    </p>
    <p class="clause">
      <strong>2. Returns.</strong> D'EL-Possibilities will pay the investor a monthly return at the rate stated
      above, calculated on the capital invested, for the duration of the tenure stated above. The full capital is
      returned at maturity, in addition to the monthly returns already received during the tenure.
    </p>
    <p class="clause">
      <strong>3. Term.</strong> This agreement runs for the tenure stated above from the date payment was confirmed,
      after which the parties will agree on renewal or return of capital.
    </p>
    <p class="clause">
      <strong>4. Signature.</strong> By signing below, the investor acknowledges the terms above and confirms the
      amount and payment date are correct.
    </p>

    <div class="signature-block">
      @if($investment->signature_name)
        <p style="font-size: 10px; color: #64748b; margin-bottom: 6px;">Signed by:</p>
        <div class="signature-name">{{ $investment->signature_name }}</div>
        <p style="font-size: 10px; color: #64748b; margin-top: 8px;">
          {{ optional($investment->signed_at)->format('j M Y, g:i A') }}
        </p>
      @else
        <p style="font-size: 10px; color: #64748b; margin-bottom: 6px;">Investor signature (pending):</p>
        <div class="signature-name">&nbsp;</div>
      @endif
    </div>

    <p class="footer">Generated by D'EL-Possibilities.</p>
  </div>
</body>
</html>
