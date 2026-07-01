<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core;

/**
 * Контекст текущего портала (multi-tenant).
 * Все запросы к БД обязаны фильтровать по portal_id из Tenant::getPortalId().
 */
final class Tenant
{
    private static ?self $current = null;

    public function __construct(
        public readonly int $portalId,
        public readonly string $domain,
        public readonly string $requestId,
    ) {
        if ($portalId <= 0) {
            throw new \RuntimeException('Некорректный portal_id');
        }
    }

    public static function bind(int $portalId, string $domain, string $requestId): void
    {
        self::$current = new self($portalId, $domain, $requestId);
    }

    public static function current(): self
    {
        if (self::$current === null) {
            throw new \RuntimeException('Tenant не инициализирован');
        }

        return self::$current;
    }

    public static function tryCurrent(): ?self
    {
        return self::$current;
    }

    public static function getPortalId(): int
    {
        return self::current()->portalId;
    }

    public static function getRequestId(): string
    {
        return self::current()->requestId;
    }

    public static function getDomain(): string
    {
        return self::current()->domain;
    }

    /** Проверка: запись принадлежит текущему порталу */
    public static function assertPortalId(int $portalId): void
    {
        if ($portalId !== self::getPortalId()) {
            throw new \RuntimeException('Доступ запрещён: несовпадение portal_id');
        }
    }

    public static function clear(): void
    {
        self::$current = null;
    }
}
