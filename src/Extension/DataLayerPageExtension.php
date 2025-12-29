<?php declare(strict_types=1);

namespace WSC\SWCookieDataLayer\Extension;

use Shopware\Core\Framework\Struct\Struct;
use WSC\SWCookieDataLayer\Struct\DataLayerStruct;

/**
 * DataLayerPageExtension
 *
 * Page extension to properly add DataLayer data to pages
 * Issue #1: Fixed PHP 8.2+ dynamic property deprecation warning
 */
class DataLayerPageExtension extends Struct
{
    protected DataLayerStruct $dataLayer;

    public function __construct(DataLayerStruct $dataLayer)
    {
        $this->dataLayer = $dataLayer;
    }

    public function getDataLayer(): DataLayerStruct
    {
        return $this->dataLayer;
    }
}
