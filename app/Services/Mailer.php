<?php

declare(strict_types=1);

/**
 * Outgoing email through PHP's built-in mail() — no libraries (Plan §23).
 *
 * Sending is off unless config sets mail.enabled, because a stock XAMPP box has
 * no mail server and mail() would only fail slowly. Callers treat a false
 * return as "not delivered" and never tell the visitor whether an account
 * exists.
 */
final class Mailer
{
    public function __construct(private bool $enabled, private string $from)
    {
    }

    public static function fromConfig(): self
    {
        return new self(
            (bool) Config::get('mail.enabled', false),
            (string) Config::get('mail.from', 'no-reply@mithra.lk')
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return bool true when mail() accepted the message for delivery
     */
    public function send(string $to, string $subject, string $body): bool
    {
        if (!$this->enabled || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        // Header injection is impossible: $to is a validated address, $from and
        // $subject come from our own code, never from the request.
        $headers = [
            'From'         => $this->from,
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Mailer'     => 'Mithra',
        ];

        return mail($to, $subject, $body, $headers);
    }
}
