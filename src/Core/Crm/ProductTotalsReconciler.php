<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Crm;

/**
 * @deprecated Строки не масштабируются — цены берутся из CRM как есть.
 *             Итог сделки подставляется только в блок ВсегоОпл (UpdMapper).
 */
final class ProductTotalsReconciler
{
    /**
     * @param list<array<string, mixed>> $products
     * @param array<string, mixed> $entity
     * @return list<array<string, mixed>>
     */
    public static function reconcileToEntity(array $products, array $entity): array
    {
        return $products;
    }
}
