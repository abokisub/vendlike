<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class InvoiceService
{
    /**
     * Generate a PDF invoice and return as attachment array for MailController
     */
    public static function generatePdf(string $serviceType, array $data): ?array
    {
        try {
            $data['service_type'] = $serviceType;
            $data['app_name'] = config('app.name', 'Vendlike');
            $data['app_url'] = config('app.url');

            $pdf = \PDF::loadView('pdf.invoice', $data);
            $pdf->setPaper('A4', 'portrait');

            $filename = self::getFilename($serviceType, $data);

            return [
                'data' => $pdf->output(),
                'name' => $filename,
                'mime' => 'application/pdf',
            ];
        } catch (\Exception $e) {
            Log::error('Invoice PDF generation failed', [
                'service' => $serviceType,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private static function getFilename(string $serviceType, array $data): string
    {
        $ref = $data['reference'] ?? $data['transid'] ?? date('YmdHis');
        $prefix = match ($serviceType) {
            'GIFT_CARD' => 'GiftCard_Invoice',
            'JAMB_PIN' => 'JAMB_Invoice',
            'RECHARGE_CARD' => 'RechargeCard_Invoice',
            'EXAM_PIN' => 'ExamPIN_Invoice',
            'MARKETPLACE' => 'Order_Invoice',
            default => 'Invoice',
        };
        return $prefix . '_' . $ref . '.pdf';
    }
}
