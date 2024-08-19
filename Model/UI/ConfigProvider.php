<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MISPay\MISPayMethod\Model\UI;

use Magento\Checkout\Model\ConfigProviderInterface;
use MISPay\MISPayMethod\Helper\MISPayHelper;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'mispaymethod';

    /**
     * @var MISPayHelper
     */
    protected $mispayHelper;

    public function __construct(MISPayHelper $mispayHelper) {
        $this->mispayHelper = $mispayHelper;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $logo = $this->mispayHelper->getLogo();
        $title = $this->mispayHelper->getTitle();
        $description = $this->mispayHelper->getDescription();

        return [
            'payment' => [
                self::CODE => [
                    'description' =>  $description ? $description : __('Split your purchase into 3 interest-free payments, No late fees. sharia-compliant'),
                    'title' =>  $title ? $title : __('Buy now then pay it later with MISpay'),
                    'logo_url' => 'https://cdn.mispay.co/assets/logo/applogo.svg',
                    'logo_visible' => $logo ? 'display: inline-block' : 'display: none',
                    'min_order_total' => $this->mispayHelper->getMinOrderTotal(),
                    'max_order_total' => $this->mispayHelper->getMaxOrderTotal(),
                ]
            ]
        ];
    }
}
