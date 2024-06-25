<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MISPay\MISPayMethodDynamicCallback\Model\UI;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\ObjectManager;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'mispaymethod_dynamic_callback';

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $objectManager = ObjectManager::getInstance();
        $logo = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')
            ->getValue('payment/mispaymethod_dynamic_callback/mispay_logo');

        return [
            'payment' => [
                self::CODE => [
                    'logo_url' => 'https://cdn.mispay.co/assets/logo/applogo.svg',
                    'logo_visible' => $logo ? 'display: inline' : 'display: none'
                ]
            ]
        ];
    }
}
