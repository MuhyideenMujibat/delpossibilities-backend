<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>D'EL-Possibilities Receipt</title>
<style>
  body { margin: 0; padding: 0; background-color: #f6f4ee; font-family: Arial, Helvetica, sans-serif; color: #0b1e33; }
  .wrapper { max-width: 560px; margin: 0 auto; padding: 24px 16px; }
  .card { background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e7e2d6; }
  .header { background-color: #0b1e33; padding: 24px 28px; text-align: center; }
  .header img { height: 40px; display: block; margin: 0 auto 8px; }
  .header .brand { color: #ffffff; font-size: 18px; font-weight: bold; letter-spacing: 0.02em; }
  .body { padding: 28px; }
  .eyebrow { color: #8a9a5b; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em; margin: 0 0 4px; }
  h1 { font-size: 20px; margin: 0 0 4px; color: #0b1e33; }
  .subtitle { color: #64748b; font-size: 13px; margin: 0 0 20px; }
  .cylinder { width: 100%; max-height: 220px; object-fit: cover; border-radius: 12px; display: block; margin-bottom: 20px; }
  table.details { width: 100%; border-collapse: collapse; font-size: 14px; }
  table.details td { padding: 8px 0; border-bottom: 1px solid #eef0f2; color: #334155; }
  table.details td.label { color: #64748b; }
  table.details td.value { text-align: right; font-weight: 600; color: #0b1e33; }
  table.details tr.total td { border-top: 2px solid #0b1e33; border-bottom: none; padding-top: 12px; font-size: 16px; }
  .badge { display: inline-block; background-color: #d9531e1a; color: #d9531e; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.04em; border-radius: 999px; padding: 3px 10px; margin-top: 6px; }
  .note { margin-top: 20px; padding: 14px 16px; background-color: #f6f4ee; border-radius: 12px; font-size: 12.5px; color: #475569; }
  .footer { padding: 20px 28px 28px; text-align: center; color: #94a3b8; font-size: 12px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="D'EL-Possibilities">
      @endif
      <div class="brand">D'EL-POSSIBILITIES</div>
    </div>

    <div class="body">
      <p class="eyebrow">Payment Receipt</p>
      <h1>Order #{{ $order->id }}</h1>
      <p class="subtitle">Paid {{ optional($order->paid_at)->format('j M Y, g:i A') ?? $order->created_at->format('j M Y, g:i A') }}</p>

      @if($cylinderUrl)
        <img src="{{ $cylinderUrl }}" alt="Your cylinder" class="cylinder">
      @endif

      <table class="details">
        <tr>
          <td class="label">Refill size</td>
          <td class="value">{{ rtrim(rtrim(number_format((float) $order->kg, 2), '0'), '.') }} kg</td>
        </tr>
        <tr>
          <td class="label">Price per kg</td>
          <td class="value">&#8358;{{ number_format((float) $order->price_per_kg, 2) }}</td>
        </tr>
        <tr>
          <td class="label">Gas cost</td>
          <td class="value">&#8358;{{ number_format((float) $order->kg * (float) $order->price_per_kg, 2) }}</td>
        </tr>
        @if($order->loyalty_discount_applied)
        <tr>
          <td class="label">Loyalty discount</td>
          <td class="value" style="color: #8a9a5b;">&minus;&#8358;{{ number_format((float) $order->loyalty_discount_amount, 2) }}</td>
        </tr>
        @endif
        <tr>
          <td class="label">Delivery fee</td>
          <td class="value">&#8358;{{ number_format((float) $order->delivery_fee, 2) }}</td>
        </tr>
        <tr class="total">
          <td class="label" style="font-weight: bold; color: #0b1e33;">Total paid</td>
          <td class="value">&#8358;{{ number_format((float) $order->total_amount, 2) }}</td>
        </tr>
      </table>

      <table class="details" style="margin-top: 16px;">
        <tr>
          <td class="label">Delivery address</td>
          <td class="value" style="font-weight: 500;">{{ $order->hostel_address }}</td>
        </tr>
        @if($order->paystack_reference)
        <tr>
          <td class="label">Payment reference</td>
          <td class="value" style="font-weight: 500;">{{ $order->paystack_reference }}</td>
        </tr>
        @endif
      </table>

      @if($order->location_type === 'off_campus')
        <span class="badge">Off-campus delivery</span>
      @endif

      <div class="note">
        A downloadable PDF copy of this receipt is attached to this email — save it for your records.
      </div>
    </div>

    <div class="footer">
      Thank you for ordering with D'EL-Possibilities.<br>
      If anything here looks off, just reply to this email.
    </div>
  </div>
</div>
</body>
</html>
