<?php

namespace App\Http\Controllers;

use App\Services\XenditPaymentSyncService;
use App\Services\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XenditWebhookController extends Controller
{
    public function __construct(
        protected XenditService $xendit,
        protected XenditPaymentSyncService $paymentSync,
    ) {}

    public function handle(Request $request)
    {
        if (! $this->xendit->verifyWebhookToken($request->header('x-callback-token'))) {
            Log::warning('Xendit webhook rejected: invalid callback token');

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $outcome = $this->paymentSync->processInvoicePayload($payload, 'webhook', $request);

        $httpStatus = match ($outcome['result']) {
            'error' => $outcome['message'] === 'Order not found' ? 404 : 500,
            default => 200,
        };

        return response()->json([
            'message' => $outcome['message'],
            'result' => $outcome['result'],
        ], $httpStatus);
    }
}
