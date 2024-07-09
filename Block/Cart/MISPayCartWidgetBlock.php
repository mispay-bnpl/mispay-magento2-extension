<?php

namespace MISPay\MISPayMethod\Block\Cart;

use MISPay\MISPayMethod\Helper\MISPayHelper;
use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

/**
 * Class Payment
 *
 * @package MISPay\MISPayMethod\Block\Cart\MISPayCartWidgetBlock
 */
class MISPayCartWidgetBlock extends Template
{
    protected $mispayHelper;

    protected $checkoutSession;

    protected $pricingHelper;

    public function __construct(
        Template\Context $context,
        MISPayHelper $mispayHelper,
        CheckoutSession $checkoutSession,
        PricingHelper $pricingHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->mispayHelper = $mispayHelper;
        $this->checkoutSession = $checkoutSession;
        $this->pricingHelper = $pricingHelper;
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

    public function getPrice()
    {
        $quote = $this->checkoutSession->getQuote();
        return $quote->getGrandTotal();
    }

    public function isVisible()
    {
        return $this->mispayHelper->isWidgetEnabled()
            && $this->getPrice() >= $this->mispayHelper->getMinOrderTotal()
            && $this->getPrice() <= $this->mispayHelper->getMaxOrderTotal();
    }
}
