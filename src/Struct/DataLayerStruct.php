<?php declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * DataLayerStruct
 *
 * Holds DataLayer event data for pages
 * Issue #1: Fixed PHP 8.2+ dynamic property deprecation warning
 */
class DataLayerStruct extends Struct
{
    protected array $dataLayerEvent;
    protected string $activeRoute;

    public function __construct(array $dataLayerEvent, string $activeRoute)
    {
        $this->dataLayerEvent = $dataLayerEvent;
        $this->activeRoute = $activeRoute;
    }

    public function getDataLayerEvent(): array
    {
        return $this->dataLayerEvent;
    }

    public function getActiveRoute(): string
    {
        return $this->activeRoute;
    }
}
