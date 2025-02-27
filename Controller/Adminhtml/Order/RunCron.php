<?php

namespace MISPay\MISPayMethod\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use MISPay\MISPayMethod\Cron\TrackCheckoutStatus;
use Magento\Framework\Controller\Result\JsonFactory;

class RunCron extends Action
{
    const ADMIN_RESOURCE = 'MISPay_MISPayMethod::runcron';

    protected $trackCheckoutStatus;
    protected $resultJsonFactory;

    public function __construct(
        Context $context,
        TrackCheckoutStatus $trackCheckoutStatus,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->trackCheckoutStatus = $trackCheckoutStatus;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        try {
            $orderId = $this->getRequest()->getParam('id');
            
            if (!$orderId) {
                throw new \Exception(__('Order ID is required'));
            }
            
            $this->trackCheckoutStatus->executeForOrder($orderId);
            
            return $resultJson->setData([
                'success' => true,
                'message' => __('Cron job executed successfully for order #%1', $orderId)
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
} 