<?php
/**
 * Purpose: Payment helper functions wrapping Khalti API calls and local payment logic.
 * Website Section: Booking & Payment.
 * Developer Notes: Build initiation payloads, verify callbacks, handle status mapping, and compute payable amounts/fees.
 */

 if (!function_exists('ridex_khalti_initiate')) {
    function ridex_khalti_initiate(
        int    $bookingId,
        string $bookingNumber,
        int    $amountPaisa   // amount × 100
    ): array {
        $data = [
            'return_url'          => KHALTI_RETURN_URL . '&booking_id=' . $bookingId,
            'website_url'         => KHALTI_WEBSITE_URL,
            'amount'              => $amountPaisa,
            'purchase_order_id'   => 'ORDER_' . $bookingId,
            'purchase_order_name' => 'Vehicle Booking ' . $bookingNumber,
        ];

        $ch = curl_init(KHALTI_INITIATE_URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key ' . KHALTI_SECRET_KEY,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode((string) $response, true) ?? [];
    }
}

if (!function_exists('ridex_khalti_lookup')) {
    function ridex_khalti_lookup(string $pidx): array {
        $ch = curl_init(KHALTI_LOOKUP_URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: key ' . KHALTI_SECRET_KEY,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode(['pidx' => $pidx]));
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode((string) $response, true) ?? [];
    }
}