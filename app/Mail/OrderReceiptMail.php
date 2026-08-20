<?php

namespace App\Mail;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class OrderReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function build(): self
    {
        // The PDF (rendered server-side by dompdf, which has no browser
        // session and would otherwise need remote fetching enabled) gets
        // images inlined as base64 data URIs. The HTML email body instead
        // uses real hosted URLs, because Gmail and most webmail clients
        // strip data: URI images from received HTML mail.
        $data = [
            'order' => $this->order,
            'logoDataUri' => $this->fileToDataUri(public_path('images/logo.jpeg')),
            'logoUrl' => asset('images/logo.jpeg'),
            'cylinderDataUri' => $this->order->cylinder_image
                ? $this->fileToDataUri(Storage::disk('public')->path($this->order->cylinder_image))
                : null,
            'cylinderUrl' => $this->order->cylinder_image_url,
        ];

        $pdf = Pdf::loadView('receipts.pdf', $data);

        return $this
            ->subject("Your D'EL-Possibilities Receipt — Order #{$this->order->id}")
            ->view('emails.receipt')
            ->with($data)
            ->attachData($pdf->output(), "delpossibilities-receipt-order-{$this->order->id}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }

    private function fileToDataUri(?string $path): ?string
    {
        if (! $path || ! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }
}
