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

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $host    = config('db.host');
            $port    = config('db.port');
            $dbname  = config('db.database');
            $user    = config('db.username');
            $pass    = config('db.password');
            $charset = config('db.charset', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES    => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND  => "SET NAMES {$charset}",
                ]);
            } catch (PDOException $e) {
                Logger::system('Database connection failed: ' . $e->getMessage());
                if (config('app.debug')) {
                    throw $e;
                }
                http_response_code(503);
                echo json_encode([
                    'success' => false,
                    'data'    => null,
                    'message' => 'Service temporarily unavailable. Please try again later.',
                    'errors'  => [],
                ]);
                exit;
            }
        }

        return self::$instance;
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
