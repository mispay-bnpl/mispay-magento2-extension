<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MISPay\MISPayMethod\Model\UI;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class ConfigProvider
 */
final class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'mispaymethod';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface      $scopConfigProvider
     */
    public function __construct(ScopeConfigInterface $scopConfigProvider)
    {
        $this->scopeConfig = $scopConfigProvider;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $logo = $this->getLogo();
        $title = $this->getTitle();
        $description = $this->getDescription();

        return [
            'payment' => [
                self::CODE => [
                    'description' =>  $description ? $description : __('Split your purchase into 3 interest-free payments, No late fees. sharia-compliant'),
                    'title' =>  $title ? $title : __('Buy now then pay it later with MISpay'),
                    'logo_url' => 'https://cdn.mispay.co/assets/logo/applogo.svg',
                    'logo_visible' => $logo ? 'display: inline-block' : 'display: none',
                    'min_order_total' => $this->getMinOrderTotal(),
                    'max_order_total' => $this->getMaxOrderTotal()
                ]
            ]
        ];
    }

    private function parseKeyName($key)
    {
        return 'payment/' . self::CODE . '/' . $key;
    }

    private function getLogo()
    {
        return $this->scopeConfig->getValue($this->parseKeyName('mispay_logo'));
    }

    private function getTitle()
    {
        return $this->scopeConfig->getValue($this->parseKeyName('title'));
    }

    private function getDescription()
    {
        return $this->scopeConfig->getValue($this->parseKeyName('description'));
    }

    /**
     * @return int
     */
    private function getMinOrderTotal()
    {
        return (int) $this->scopeConfig->getValue($this->parseKeyName('min_order_total'));
    }

    /**
     * @return int
     */
    private function getMaxOrderTotal()
    {
        return (int) $this->scopeConfig->getValue($this->parseKeyName('max_order_total'));
    }
}
