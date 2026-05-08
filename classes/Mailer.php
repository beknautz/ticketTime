<?php
declare(strict_types=1);

class Mailer
{
    private bool $usePhpMailer;

    public function __construct()
    {
        $this->usePhpMailer = class_exists('PHPMailer\PHPMailer\PHPMailer')
                           && MAIL_DRIVER === 'smtp';
    }

    /**
     * Send order confirmation + ticket email to customer.
     */
    public function sendOrderConfirmation(array $order, array $items, array $tickets): bool
    {
        $event = (new Event())->getById((int)$order['event_id']);

        $subject = "Your tickets for " . ($event['event_name'] ?? 'the event');
        $body    = $this->buildOrderEmailBody($order, $items, $tickets, $event);

        return $this->send(
            $order['customer_email'],
            $order['customer_first_name'] . ' ' . $order['customer_last_name'],
            $subject,
            $body
        );
    }

    /**
     * Resend tickets email.
     */
    public function resendTickets(array $order, array $items, array $tickets): bool
    {
        return $this->sendOrderConfirmation($order, $items, $tickets);
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        if (MAIL_DRIVER === 'sendgrid') {
            return $this->sendWithSendGrid($toEmail, $toName, $subject, $htmlBody);
        }
        if ($this->usePhpMailer) {
            return $this->sendWithPhpMailer($toEmail, $toName, $subject, $htmlBody);
        }
        return $this->sendWithNativeMail($toEmail, $toName, $subject, $htmlBody);
    }

    private function sendWithSendGrid(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $payload = [
            'personalizations' => [[
                'to' => [['email' => $toEmail, 'name' => $toName]],
            ]],
            'from'    => ['email' => MAIL_FROM_ADDRESS, 'name' => MAIL_FROM_NAME],
            'reply_to'=> ['email' => MAIL_REPLY_TO],
            'subject' => $subject,
            'content' => [
                ['type' => 'text/plain', 'value' => strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody))],
                ['type' => 'text/html',  'value' => $htmlBody],
            ],
        ];

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . SENDGRID_API_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            Logger::error('SendGrid cURL error', ['error' => $curlErr, 'to' => $toEmail]);
            return false;
        }

        if ($httpCode >= 400) {
            Logger::error('SendGrid API error', ['http' => $httpCode, 'response' => $response, 'to' => $toEmail]);
            return false;
        }

        Logger::info('SendGrid email sent', ['to' => $toEmail, 'subject' => $subject, 'http' => $httpCode]);
        return true;
    }

    private function sendWithPhpMailer(string $toEmail, string $toName, string $subject, string $body): bool
    {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = !empty(MAIL_USERNAME);
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl'
                                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;

            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addReplyTo(MAIL_REPLY_TO);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $mail->send();
            return true;
        } catch (\Exception $e) {
            Logger::error('PHPMailer failed', ['error' => $e->getMessage(), 'to' => $toEmail]);
            return false;
        }
    }

    private function sendWithNativeMail(string $toEmail, string $toName, string $subject, string $body): bool
    {
        $boundary = '----=_Part_' . uniqid();
        $headers  = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
            'Reply-To: ' . MAIL_REPLY_TO,
            'X-Mailer: TicketTime/1.0',
        ]);

        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $message = "--{$boundary}\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
                 . quoted_printable_encode($textBody) . "\r\n"
                 . "--{$boundary}\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
                 . quoted_printable_encode($body) . "\r\n"
                 . "--{$boundary}--";

        $to = sprintf('"%s" <%s>', $toName, $toEmail);

        $result = @mail($to, $subject, $message, $headers);

        if (!$result) {
            Logger::error('Native mail failed', ['to' => $toEmail, 'subject' => $subject]);
        }

        return $result;
    }

    private function buildOrderEmailBody(array $order, array $items, array $tickets, ?array $event): string
    {
        $siteName     = SITE_NAME;
        $siteUrl      = SITE_URL;
        $pickupMsg    = getSiteSetting('ticket_pickup_message', '');
        $pickupBlock  = $pickupMsg
            ? '<div style="background:#e8f4fd;border-left:4px solid #0d6efd;padding:12px 16px;margin:16px 0;border-radius:4px;">'
              . '<strong>Important:</strong> ' . htmlspecialchars($pickupMsg)
              . '</div>'
            : '';
        $eventName = $event['event_name'] ?? 'Event';
        $eventDate = $event ? date('l, F j, Y g:i A', strtotime($event['event_start'])) : '';
        $eventLoc  = $event['event_location'] ?? '';
        $orderId   = htmlspecialchars($order['public_order_id']);
        $custName  = htmlspecialchars($order['customer_first_name'] . ' ' . $order['customer_last_name']);
        $total     = formatMoney((float)$order['total']);

        // Items table
        $itemRows = '';
        foreach ($items as $item) {
            $itemRows .= sprintf(
                '<tr><td>%s</td><td style="text-align:center">%d</td><td style="text-align:right">%s</td></tr>',
                htmlspecialchars($item['ticket_name']),
                $item['quantity'],
                formatMoney((float)$item['line_total'])
            );
        }

        // Ticket links
        $ticketLinks = '';
        foreach ($tickets as $t) {
            $url = $siteUrl . '/public/ticket.php?token=' . urlencode($t['qr_token']);
            $ticketLinks .= sprintf(
                '<p><a href="%s" style="background:#0d6efd;color:#fff;padding:8px 16px;text-decoration:none;border-radius:4px;">View Ticket: %s</a></p>',
                htmlspecialchars($url),
                htmlspecialchars($t['ticket_code'])
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px;">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">

  <!-- Header -->
  <div style="background:#0d6efd;color:#fff;padding:24px;text-align:center;">
    <h1 style="margin:0;font-size:24px;">{$siteName}</h1>
    <p style="margin:8px 0 0;opacity:0.9;">Order Confirmation</p>
  </div>

  <!-- Body -->
  <div style="padding:32px;">
    <p>Hi {$custName},</p>
    <p>Thanks for your order! Here are your tickets for <strong>{$eventName}</strong>.</p>
    {$pickupBlock}

    <table style="width:100%;border-collapse:collapse;margin:16px 0;">
      <tr style="background:#f8f9fa;">
        <th style="padding:8px;text-align:left;border-bottom:2px solid #dee2e6;">Event</th>
        <th style="padding:8px;text-align:right;border-bottom:2px solid #dee2e6;"></th>
      </tr>
      <tr>
        <td style="padding:8px;"><strong>{$eventName}</strong></td>
        <td style="padding:8px;text-align:right;">{$eventDate}</td>
      </tr>
      <tr>
        <td colspan="2" style="padding:8px;color:#666;">{$eventLoc}</td>
      </tr>
    </table>

    <h3>Order #{$orderId}</h3>
    <table style="width:100%;border-collapse:collapse;margin:16px 0;">
      <tr style="background:#f8f9fa;">
        <th style="padding:8px;text-align:left;border-bottom:2px solid #dee2e6;">Ticket</th>
        <th style="padding:8px;text-align:center;border-bottom:2px solid #dee2e6;">Qty</th>
        <th style="padding:8px;text-align:right;border-bottom:2px solid #dee2e6;">Total</th>
      </tr>
      {$itemRows}
      <tr style="border-top:2px solid #dee2e6;font-weight:bold;">
        <td colspan="2" style="padding:8px;">Total Paid</td>
        <td style="padding:8px;text-align:right;">{$total}</td>
      </tr>
    </table>

    <h3>Your Tickets</h3>
    <p>Click each link below to view and download your individual tickets:</p>
    {$ticketLinks}

    <hr style="border:none;border-top:1px solid #dee2e6;margin:24px 0;">

    <h3>Will-Call Backup</h3>
    <p>If you can't access your email at the gate, staff can look up your order by Order ID:</p>
    <p><strong>Order ID:</strong> {$orderId}</p>

    <hr style="border:none;border-top:1px solid #dee2e6;margin:24px 0;">
    <p style="color:#666;font-size:14px;">
      Questions? Contact us at <a href="mailto:{$this->supportEmail()}">{$this->supportEmail()}</a>
      or call {$this->supportPhone()}
    </p>
  </div>

  <!-- Footer -->
  <div style="background:#f8f9fa;padding:16px;text-align:center;color:#666;font-size:12px;">
    <p style="margin:0;">&copy; {$siteName}. All rights reserved.</p>
  </div>
</div>
</body>
</html>
HTML;
    }

    private function supportEmail(): string
    {
        return defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@example.com';
    }

    private function supportPhone(): string
    {
        return defined('SUPPORT_PHONE') ? SUPPORT_PHONE : '';
    }
}
