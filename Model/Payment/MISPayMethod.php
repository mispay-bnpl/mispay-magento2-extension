<?php


namespace MISPay\MISPayMethodDynamicCallback\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;

class MISPayMethod extends AbstractMethod
{

    protected $_code = "mispaymethod";
    protected $_isOffline = true;

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }
}
