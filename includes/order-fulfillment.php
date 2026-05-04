<?php
declare(strict_types=1);

/**
 * Marks an order as paid, generates tickets + QR codes, and sends
 * the confirmation email. Safe to call from the webhook or the
 * status-poller fallback — idempotent if already paid.
 *
 * Returns true on success, false if already paid or on error.
 */
function fulfillPaidOrder(array $order, string $piStatus = 'succeeded'): bool
{
    if ($order['status'] === 'paid') {
        return false; // idempotent
    }

    try {
        $db         = Database::getInstance();
        $orderModel = new Order();

        $db->beginTransaction();

        $orderModel->markPaid((int)$order['order_id'], $piStatus);

        $ticketModel = new Ticket();
        $tickets     = $ticketModel->generateForOrder((int)$order['order_id']);

        $qr = new QRCode();
        foreach ($tickets as $t) {
            $qr->generate(
                SITE_URL . '/public/ticket.php?token=' . urlencode($t['qr_token']),
                'ticket-' . $t['ticket_id']
            );
        }

        $qr->generate(
            SITE_URL . '/public/willcall.php?token=' . urlencode($order['order_barcode_token']),
            'order-' . $order['order_id']
        );

        $db->commit();

        $freshOrder = $orderModel->getById((int)$order['order_id']);
        $items      = $orderModel->getItems((int)$order['order_id']);
        $allTickets = $orderModel->getTickets((int)$order['order_id']);

        $mailer = new Mailer();
        $sent   = $mailer->sendOrderConfirmation($freshOrder, $items, $allTickets);

        Logger::info('Order fulfilled', [
            'order'      => $order['public_order_id'],
            'tickets'    => count($tickets),
            'email_sent' => $sent,
            'source'     => 'fulfillment',
        ]);

        return true;

    } catch (\Throwable $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        Logger::error('fulfillPaidOrder failed', [
            'order' => $order['public_order_id'],
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}
