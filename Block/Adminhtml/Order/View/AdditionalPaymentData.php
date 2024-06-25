<?php

namespace MISPay\MISPayMethodDynamicCallback\Block\Adminhtml\Order\View;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\RequestInterface;

class AdditionalPaymentData extends \Magento\Backend\Block\Template
{
    protected $_template = 'MISPay_MISPayMethodDynamicCallback::order/view/additional_payment_data.phtml';
    protected $orderRepository;
    protected $request;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        OrderRepositoryInterface $orderRepository,
        RequestInterface $request,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->request = $request;
        parent::__construct($context, $data);
    }

    public function getAdditionalData()
    {
        $order = $this->getOrder();
        $payment = $order->getPayment();
        $additionalData = $payment->getAdditionalInformation();

        return $additionalData;
    }

    public function getOrder()
    {
        $orderId = $this->request->getParam('order_id');
        return $this->orderRepository->get($orderId);
    }
}
