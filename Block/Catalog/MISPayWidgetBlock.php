<?php

namespace MISPay\MISPayMethod\Block\Catalog;

use Magento\Backend\Model\Auth\Session;
use MISPay\MISPayMethod\Helper\MISPayHelper;
use Magento\Framework\View\Element\Template;

/**
 * Class Payment
 *
 * @package MISPay\MISPayMethod\Block\Catalog\MISPayWidgetBlock
 */
// class MISPayWidgetBlock extends \Magento\Backend\Block\Template
class MISPayWidgetBlock extends Template
{
    protected $config;

    protected $customerSession;

    protected $whitelistFactory;

    protected $_store;

    protected $mispayHelper;

    protected $catalogHelper;

    public function __construct(
        Template\Context $context,
        Session $customerSession,
        \Magento\Framework\Locale\Resolver $store,
        \Magento\Catalog\Helper\Data $catalogHelper,
        MISPayHelper $mispayHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->_store = $store;
        $this->mispayHelper = $mispayHelper;
        $this->catalogHelper = $catalogHelper;
    }

    protected function _toHtml()
    {
        return parent::_toHtml();
    }

    public function isWidgetEnabled()
    {
        return $this->mispayHelper->isWidgetEnabled();
    }

    public function getLang()
    {
        return $this->mispayHelper->getLang();
    }

    /**
     * Returns prices
     *
     * @return float
     */
    public function getPrice()
    {
        $product = $this->catalogHelper->getProduct();
        return $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
    }
}
