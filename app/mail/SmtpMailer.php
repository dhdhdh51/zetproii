<?php
/**
 * Minimal dependency-free SMTP client (no PHPMailer/Composer required so
 * the app still runs on bare shared hosting). Supports STARTTLS, SSL,
 * and plain auth over LOGIN mechanism.
 */
final class SmtpMailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private string $encryption, // tls | ssl | none
        private int $timeout = 15
    ) {
    }

    /**
     * @throws RuntimeException on any SMTP-level failure
     */
    public function send(string $fromEmail, string $fromName, string $toEmail, string $subject, string $htmlBody): void
    {
        $transport = $this->encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client(
            "{$transport}{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout
        );

        if ($socket === false) {
            throw new RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $this->timeout);

        $this->expect($socket, 220);
        $this->command($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);

        if ($this->encryption === 'tls') {
            $this->command($socket, "STARTTLS", 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed.');
            }
            $this->command($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), 250);
        }

        if ($this->username !== '') {
            $this->command($socket, "AUTH LOGIN", 334);
            $this->command($socket, base64_encode($this->username), 334);
            $this->command($socket, base64_encode($this->password), 235);
        }

        $this->command($socket, "MAIL FROM:<{$fromEmail}>", 250);
        $this->command($socket, "RCPT TO:<{$toEmail}>", 250);
        $this->command($socket, "DATA", 354);

        $headers = [
            "From: " . $this->encodeHeader($fromName) . " <{$fromEmail}>",
            "To: <{$toEmail}>",
            "Subject: " . $this->encodeHeader($subject),
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Date: " . date('r'),
            "Message-ID: <" . bin2hex(random_bytes(16)) . "@" . $this->host . ">",
        ];

        $data = implode("\r\n", $headers) . "\r\n\r\n" . $this->escapeBody($htmlBody) . "\r\n.\r\n";
        fwrite($socket, $data);
        $this->readResponse($socket, 250);

        $this->command($socket, "QUIT", 221, false);
        fclose($socket);
    }

    private function command($socket, string $cmd, int $expectedCode, bool $throwOnMismatch = true): string
    {
        fwrite($socket, $cmd . "\r\n");
        return $this->readResponse($socket, $expectedCode, $throwOnMismatch);
    }

    private function expect($socket, int $expectedCode): string
    {
        return $this->readResponse($socket, $expectedCode);
    }

    private function readResponse($socket, int $expectedCode, bool $throwOnMismatch = true): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            // Multi-line responses have a dash after the code; a space means final line.
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($throwOnMismatch && $code !== $expectedCode) {
            throw new RuntimeException("SMTP error: expected {$expectedCode}, got: " . trim($response));
        }

        return $response;
    }

    private function escapeBody(string $body): string
    {
        // Dot-stuffing per RFC 5321
        return preg_replace('/^\./m', '..', $body) ?? $body;
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
