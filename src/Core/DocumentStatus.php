<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core;

/** Статусы документа в реестре (подготовка к ЭДО) */
final class DocumentStatus
{
    public const DRAFT     = 'draft';
    public const GENERATED = 'generated';
    public const SENT      = 'sent';
    public const DELIVERED = 'delivered';
    public const ACCEPTED  = 'accepted';
    public const REJECTED  = 'rejected';

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::GENERATED,
            self::SENT,
            self::DELIVERED,
            self::ACCEPTED,
            self::REJECTED,
        ];
    }
}
