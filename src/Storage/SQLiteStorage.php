<?php declare(strict_types=1);

namespace ju1ius\Macaron\Storage;

use ju1ius\Macaron\Cookie;
use ju1ius\Macaron\Cookie\SameSite;
use ju1ius\Macaron\Exception\CookieStorageException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;
use Throwable;


if (!class_exists(SQLite3::class, false)) {
    // @codeCoverageIgnoreStart
    throw new \LogicException(sprintf(
        'You cannot use the %s class, as the sqlite3 extension is not installed.',
        SQLiteStorage::class,
    ));
    // @codeCoverageIgnoreEnd
}


final class SQLiteStorage implements PersistentStorageInterface
{
    private SQLite3 $connection;

    /**
     * @var Operation[]
     */
    private array $pending = [];

    public function __construct(
        private readonly string $filename = ':memory:',
        private bool $persistSessionCookies = false,
        private readonly string $encryptionKey = '',
        private readonly int $maxPendingTasks = 512,
    ) {
    }

    public function __destruct()
    {
        try {
            $this->flush();
        } catch (Throwable) {
        } finally {
            $this->connection?->close();
        }
    }

    public function setPersistSessionCookies(bool $persist): void
    {
        $this->persistSessionCookies = $persist;
    }

    public function clear(): void
    {
        $this->transactional(function (SQLite3 $cx) {
            /** @noinspection SqlWithoutWhere */
            $cx->exec('DELETE FROM cookies');
            $this->pending = [];
        });
    }

    public function load(): array
    {
        return $this->executeFetch([], !$this->persistSessionCookies);
    }

    public function loadDomains(string ...$keys): array
    {
        return $this->executeFetch($keys, !$this->persistSessionCookies);
    }

    public function add(Cookie $cookie): void
    {
        $this->queueOperation(new Operation(OperationType::Add, clone $cookie));
    }

    public function delete(Cookie $cookie): void
    {
        $this->queueOperation(new Operation(OperationType::Delete, clone $cookie));
    }

    public function touch(Cookie $cookie): void
    {
        $this->queueOperation(new Operation(OperationType::Touch, clone $cookie));
    }

    /**
     * @throws CookieStorageException
     */
    public function flush(): void
    {
        if (!$this->pending) {
            return;
        }
        $this->transactional(function(SQLite3 $cx) {
            $add = $del = $touch = null;
            foreach ($this->pending as $op) {
                match ($op->type) {
                    OperationType::Add => $this->executeAdd($add ??= $cx->prepare(self::STMT_ADD), $op->cookie),
                    OperationType::Delete => $this->executeDelete($del ??= $cx->prepare(self::STMT_DEL), $op->cookie),
                    OperationType::Touch => $this->executeTouch($touch ??= $cx->prepare(self::STMT_TOUCH), $op->cookie),
                };
            }
            $this->pending = [];
            $add?->close();
            $del?->close();
            $touch?->close();
        });
    }

    private function queueOperation(Operation $op): void
    {
        if (!$this->persistSessionCookies && !$op->cookie->persistent) {
            // TODO: should we keep delete ops?
            return;
        }
        $this->pending[] = $op;
        if (\count($this->pending) >= $this->maxPendingTasks) {
            $this->flush();
        }
    }

    private function getConnection(): SQLite3
    {
        return $this->connection ??= $this->connect();
    }

    private function connect(): SQLite3
    {
        $cx = new SQLite3(
            $this->filename,
            \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE,
            $this->encryptionKey,
        );
        $cx->enableExceptions(true);
        $this->migrate($cx);

        return $cx;
    }

    /**
     * @template T
     * @param callable(SQLite3): T $task
     * @return T
     *
     * @throws CookieStorageException
     */
    private function transactional(callable $task): mixed
    {
        $cx = $this->getConnection();
        $cx->exec('BEGIN');
        try {
            $result = $task($cx);
            $cx->exec('COMMIT');
            return $result;
        } catch (Throwable $err) {
            $cx->exec('ROLLBACK');
            throw new CookieStorageException('Error while updating SQLite storage.', 0, $err);
        }
    }

    private function executeFetch(array $keys = [], bool $excludeSession = false): array
    {
        $sql = 'SELECT * FROM cookies WHERE 1';
        if ($keys) {
            $sql .= sprintf(
                ' AND host_key IN (%s)',
                implode(',', array_fill(0, \count($keys), '?')),
            );
        }
        if ($excludeSession) {
            $sql .= ' AND persistent = 1';
        }
        $stmt = $this->getConnection()->prepare($sql);
        if ($keys) {
            foreach ($keys as $i => $key) {
                $stmt->bindValue($i + 1, $key);
            }
        }

        $result = $stmt->execute();
        $cookies = [];
        while ($row = $result->fetchArray(\SQLITE3_ASSOC)) {
            $cookies[] = $this->mapCookie($row);
        }
        $result->finalize();

        return $cookies;
    }

    private const STMT_ADD = <<<'SQL'
    INSERT INTO cookies (
        host_key, domain, path, name, value,
        created_at, expires_at, accessed_at, updated_at,
        hostonly, secureonly, httponly, persistent, samesite,
        source_scheme, source_port, same_party, priority
    ) VALUES (
        :host_key, :domain, :path, :name, :value,
        :created_at, :expires_at, :accessed_at, :updated_at,
        :hostonly, :secureonly, :httponly, :persistent, :samesite,
        :source_scheme, :source_port, :same_party, :priority
    )
    SQL;

    private function executeAdd(SQLite3Stmt $stmt, Cookie $cookie): SQLite3Result|false
    {
        $stmt->reset();
        $params = [
            // TODO: use etld+1
            ':host_key' => $cookie->domain,
            ':domain' => $cookie->domain,
            ':path' => $cookie->path,
            ':name' => $cookie->name,
            ':value' => $cookie->value,
            ':created_at' => $cookie->createdAt->getTimestamp(),
            ':expires_at' => $cookie->expiresAt,
            ':accessed_at' => $cookie->accessedAt->getTimestamp(),
            ':updated_at' => $cookie->accessedAt->getTimestamp(),
            ':hostonly' => $cookie->hostOnly,
            ':secureonly' => $cookie->secureOnly,
            ':httponly' => $cookie->httpOnly,
            ':persistent' => $cookie->persistent,
            ':samesite' => $cookie->sameSite->value,
            ':source_scheme' => '',
            ':source_port' => 80,
            ':same_party' => 0,
            ':priority' => 0,
        ];
        foreach ($params as $i => $value) {
            $stmt->bindValue($i, $value);
        }

        return $stmt->execute();
    }

    private const STMT_DEL = <<<'SQL'
    DELETE FROM cookies WHERE (
        name=:name
        AND host_key=:host_key
        AND path=:path
    )
    SQL;

    private function executeDelete(SQLite3Stmt $stmt, Cookie $cookie): SQLite3Result|false
    {
        $stmt->reset();
        $stmt->bindValue(':name', $cookie->name);
        // TODO: use etld+1
        $stmt->bindValue(':host_key', $cookie->domain);
        $stmt->bindValue(':path', $cookie->path);

        return $stmt->execute();
    }

    private const STMT_TOUCH = <<<'SQL'
    UPDATE cookies SET accessed_at=:access WHERE (
        name=:name
        AND host_key=:host_key
        AND path=:path
    )
    SQL;

    private function executeTouch(SQLite3Stmt $stmt, Cookie $cookie): SQLite3Result|false
    {
        $stmt->reset();
        $stmt->bindValue(':access', $cookie->accessedAt->getTimestamp());
        $stmt->bindValue(':name', $cookie->name);
        // TODO: use etld+1
        $stmt->bindValue(':host_key', $cookie->domain);
        $stmt->bindValue(':path', $cookie->path);

        return $stmt->execute();
    }

    private function migrate(SQLite3 $cx): void
    {
        $schema = file_get_contents(__DIR__ . '/Resources/000_schema.sql');
        $cx->exec($schema);
    }

    private function mapCookie(array $row): Cookie
    {
        $createdAt = new \DateTimeImmutable("@{$row['created_at']}");
        $accessedAt = new \DateTimeImmutable("@{$row['accessed_at']}");
        $expiresAt = $row['expires_at'];

        return new Cookie(
            $row['name'],
            $row['value'],
            $row['domain'],
            $row['path'],
            (bool)$row['persistent'],
            $expiresAt,
            (bool)$row['hostonly'],
            (bool)$row['secureonly'],
            (bool)$row['httponly'],
            SameSite::tryFrom($row['samesite']) ?? SameSite::Default,
            $createdAt,
            $accessedAt,
        );
    }
}
