<?php
/**
 * PDO database connection singleton.
 * All queries in the application MUST use prepared statements through
 * this class (or a Repository built on top of it) - never raw string
 * concatenation of user input into SQL.
 */
final class Database
{
    private static ?PDO $instance = null;

    /**
     * Set once a connection attempt has failed, so later calls fail fast.
     *
     * WHY: the failure path logs, and Logger tries to persist entries to the
     * system_logs table, which calls back into connection(). With $instance
     * still null that started a fresh connection attempt, which failed, which
     * logged again - recursing until PHP died with "Allowed memory size
     * exhausted" and served an empty 500. One unreachable database was enough
     * to burn the whole memory limit on a single request.
     */
    private static bool $connectionFailed = false;

    public static function connection(): PDO
    {
        if (self::$connectionFailed) {
            throw new RuntimeException('Database connection is unavailable.');
        }

        if (self::$instance === null) {
            $host    = config('db.host');
            $port    = config('db.port');
            $dbname  = config('db.database');
            $user    = config('db.username');
            $pass    = config('db.password');
            $charset = config('db.charset', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            // Align MySQL's session timezone with PHP's configured timezone.
            //
            // WHY THIS MATTERS: MySQL servers are very often set to UTC (this is
            // the default on most shared hosting and RDS) while APP_TIMEZONE is
            // something else, e.g. Asia/Kolkata. Without this, any value written
            // by MySQL (NOW(), DATE_ADD(NOW(), ...)) and later read back and
            // compared in PHP via strtotime() is off by the UTC offset - which
            // silently broke password-reset links entirely (a 60-minute token
            // looked ~5.5 hours expired the instant it was created) and made
            // every other date comparison subtly wrong.
            //
            // Setting the session offset makes NOW()/CURDATE()/INTERVAL maths on
            // the server agree with PHP's clock, so timestamps are consistent
            // end to end regardless of how the host's MySQL is configured.
            $tzOffset = (new DateTime('now', new DateTimeZone((string) config('app.timezone', 'UTC'))))->format('P');

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES    => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND  => "SET NAMES {$charset}",
                ]);
                self::$instance->exec("SET time_zone = '{$tzOffset}'");
            } catch (PDOException $e) {
                // Flag before logging: Logger's DB persistence calls back in here.
                self::$connectionFailed = true;

                Logger::system('Database connection failed: ' . $e->getMessage());

                if (config('app.debug')) {
                    throw $e;
                }

                self::renderUnavailable();
            }
        }

        return self::$instance;
    }

    /**
     * Emits a 503 and stops. Answers in the format the caller can use: JSON for
     * API/AJAX callers, a minimal self-contained HTML page for a browser.
     *
     * Previously this always echoed JSON, so a visitor whose site had wrong
     * database credentials was shown a raw `{"success":false,...}` blob.
     */
    private static function renderUnavailable(): never
    {
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 60');
        }

        $path = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $wantsJson = str_contains($path, '/api/')
            || str_contains($accept, 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

        if ($wantsJson) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'data'    => null,
                'message' => 'Service temporarily unavailable. Please try again later.',
                'errors'  => [],
            ]);
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        // Deliberately inlined: the stylesheet may itself be unreachable, and a
        // database outage should never depend on another request succeeding.
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Temporarily unavailable</title><style>'
            . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
            . 'background:#06060c;color:#f4f5fb;font-family:Inter,Segoe UI,system-ui,sans-serif;padding:24px;}'
            . '.b{max-width:460px;text-align:center;}'
            . 'h1{font-size:1.4rem;margin:0 0 10px;}'
            . 'p{color:#9ba1b8;line-height:1.6;margin:0 0 8px;}'
            . 'code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:5px;font-size:.85em;}'
            . '</style></head><body><div class="b">'
            . '<h1>We can&rsquo;t reach the database</h1>'
            . '<p>The site is up, but it could not connect to its database, so nothing can be loaded right now.</p>'
            . '<p>If you administer this site: check the database settings in <code>env.php</code>, '
            . 'then open <code>diagnose.php</code> for a full check.</p>'
            . '</div></body></html>';
        exit;
    }

    /**
     * Convenience wrapper: run a prepared query and return the PDOStatement.
     *
     * @param array<int|string, mixed> $params
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    public static function beginTransaction(): bool
    {
        return self::connection()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::connection()->commit();
    }

    public static function rollBack(): bool
    {
        return self::connection()->inTransaction() ? self::connection()->rollBack() : false;
    }
}
