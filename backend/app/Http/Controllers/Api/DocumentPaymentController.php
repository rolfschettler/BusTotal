<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentPayment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Zahlungen zu einer Rechnung oder Anzahlungsrechnung. Lesen: Administrator, Disponent, Buchhaltung.
 * Buchen und Stornieren: nur Administrator und Buchhaltung (Routen in routes/api.php).
 */
class DocumentPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Document $document): JsonResponse
    {
        return response()->json($this->payload($document));
    }

    public function store(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['nullable', Rule::enum(PaymentKind::class)],
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'paid_on.required' => 'Bitte das Zahlungsdatum angeben.',
            'amount.required' => 'Bitte den Betrag angeben.',
            'amount.gt' => 'Der Betrag muss größer als 0 sein.',
        ]);

        $this->payments->record($document, $data);

        return response()->json($this->payload($document->fresh()), 201);
    }

    public function cancel(Request $request, Document $document, DocumentPayment $payment): JsonResponse
    {
        abort_unless($payment->document_id === $document->id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Bitte einen Grund angeben.']);
        $this->payments->cancel($payment, $data['reason']);

        return response()->json($this->payload($document->fresh()));
    }

    /** Buchungen (auch stornierte, ausgegraut), Zahlstand und die Auswahllisten für die Oberfläche. */
    private function payload(Document $document): array
    {
        return [
            'payments' => $this->payments->applies($document) ? $document->payments()->get() : [],
            'summary' => $this->payments->summary($document),
            'kinds' => PaymentKind::options(),
            'methods' => PaymentMethod::options(),
        ];
    }
}
