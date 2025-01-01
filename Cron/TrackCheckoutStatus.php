<?php

namespace MISPay\MISPayMethod\Cron;

use MISPay\MISPayMethod\Helper\MISPayRequestHelper;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Zend_Log_Writer_Stream;
use Zend_Log;

class TrackCheckoutStatus
{
    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var MISPayRequestHelper
     */
    protected $mispayRequestHelper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    public function __construct(
        CollectionFactory $orderCollectionFactory,
        MISPayRequestHelper $mispayRequestHelper,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->mispayRequestHelper = $mispayRequestHelper;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
    }

    public function execute()
    {
        // Create a separate log file for MISPay cron
        $writer = new Zend_Log_Writer_Stream(BP . '/var/log/mispay_cron');
        $logger = new Zend_Log();
        $logger->addWriter($writer);
        
        try {
            $logger->info('MISPay Track Checkout Cron - Started execution at ' . date('Y-m-d H:i:s'));
            
            // Get all pending MISPay orders with increment ID
            $orders = $this->orderCollectionFactory->create()
                ->addFieldToSelect('*')
                ->addFieldToFilter(
                    'main_table.created_at',
                    ['gteq' => date('Y-m-d H:i:s', strtotime('-24 hours'))]
                )
                ->addFieldToFilter(
                    'main_table.status',
                    ['in' => ['pending', 'pending_payment', 'new']]
                )
                ->join(
                    ['payment' => 'sales_order_payment'],
                    'main_table.entity_id = payment.parent_id',
                    ['payment_method' => 'payment.method']
                )
                ->addFieldToFilter('payment.method', ['eq' => 'mispaymethod'])
                ->setOrder('main_table.created_at', 'DESC');  // Get most recent orders first

            // Log the SQL query for debugging
            $logger->info('Collection Query: ' . $orders->getSelect()->__toString());
            $logger->info('Found ' . $orders->count() . ' orders to process');

            if ($orders->count() == 0) {
                $logger->info('No pending MISPay orders found in the last 24 hours');
                return;
            }

            foreach ($orders as $order) {
                try {
                    $payment = $order->getPayment();
                    $incrementId = $order->getIncrementId();
                    
                    // Log order details for debugging
                    $logger->info('-------------------');
                    $logger->info('Processing Order #' . $incrementId);
                    $logger->info('Order ID: ' . $order->getId());
                    $logger->info('Order State: ' . $order->getState());
                    $logger->info('Order Status: ' . $order->getStatus());
                    $logger->info('Order Created At: ' . $order->getCreatedAt());
                    $logger->info('Payment Method: ' . $payment->getMethod());
                    
                    // Get track ID from both possible locations
                    $additionalData = json_decode($payment->getAdditionalData() ?: '{}', true) ?: [];
                    $additionalInfo = $payment->getAdditionalInformation();
                    
                    $logger->info('Additional Data: ' . json_encode($additionalData));
                    $logger->info('Additional Info: ' . json_encode($additionalInfo));
                    
                    $trackId = $additionalData['mispay_checkout_track_id'] 
                           ?? $additionalInfo['mispay_checkout_track_id'] 
                           ?? null;

                    if (empty($trackId)) {
                        $logger->info('Order #' . $incrementId . ' - No track ID found');
                        continue;
                    }

                    $logger->info('Processing order #' . $incrementId . ' with track ID: ' . $trackId);
                    
                    // Track the checkout
                    $response = $this->mispayRequestHelper->trackCheckout($trackId);
                    
                    if ($response) {
                        $logger->info('Track response for Order #' . $incrementId . ': ' . json_encode($response));
                        
                        // Update order status based on response
                        if (isset($response->result->status)) {
                            switch ($response->result->status) {
                                case 'success':
                                    $order->setState(Order::STATE_PROCESSING)
                                          ->setStatus(Order::STATE_PROCESSING);
                                    $logger->info('Updated order #' . $incrementId . ' to PROCESSING');
                                    break;
                                case 'canceled':
                                case 'error':
                                    $order->setState(Order::STATE_CANCELED)
                                          ->setStatus(Order::STATE_CANCELED);
                                    $logger->info('Updated order #' . $incrementId . ' to CANCELED');
                                    break;
                            }
                            $this->orderRepository->save($order);
                        }
                    } else {
                        $logger->error('No response received for Order #' . $incrementId);
                    }
                    $logger->info('-------------------');

                } catch (\Exception $e) {
                    $logger->error('Error processing Order #' . $incrementId . ': ' . $e->getMessage());
                    $logger->error('Stack trace: ' . $e->getTraceAsString());
                }
            }
            
            $logger->info('MISPay Track Checkout Cron - Finished execution at ' . date('Y-m-d H:i:s'));
            
        } catch (\Exception $e) {
            $logger->error('MISPay Track Checkout Cron - Error: ' . $e->getMessage());
            $logger->error('Stack trace: ' . $e->getTraceAsString());
        }

        // Save the log file
        $writer->close();
    }

    public function executeForOrder($orderId)
    {
        // Create a separate log file for MISPay cron
        $writer = new Zend_Log_Writer_Stream(BP . '/var/log/mispay_cron');
        $logger = new Zend_Log();
        $logger->addWriter($writer);
        
        try {
            $logger->info('MISPay Track Checkout Manual Trigger - Started for Order #' . $orderId);
            
            // Get the specific order
            $orders = $this->orderCollectionFactory->create()
                ->addFieldToSelect('*')
                ->addFieldToFilter('entity_id', ['eq' => $orderId])
                ->join(
                    ['payment' => 'sales_order_payment'],
                    'main_table.entity_id = payment.parent_id',
                    ['payment_method' => 'payment.method']
                )
                ->addFieldToFilter('payment.method', ['eq' => 'mispaymethod']);

            if ($orders->count() == 0) {
                throw new \Exception('Order not found or is not a MISPay order');
            }

            foreach ($orders as $order) {
                try {
                    $payment = $order->getPayment();
                    $incrementId = $order->getIncrementId();
                    
                    // Get track ID from both possible locations
                    $additionalData = json_decode($payment->getAdditionalData() ?: '{}', true) ?: [];
                    $additionalInfo = $payment->getAdditionalInformation();
                    
                    $trackId = $additionalData['mispay_checkout_track_id'] 
                           ?? $additionalInfo['mispay_checkout_track_id'] 
                           ?? null;

                    if (empty($trackId)) {
                        throw new \Exception('No track ID found for order #' . $incrementId);
                    }

                    // Track the checkout
                    $response = $this->mispayRequestHelper->trackCheckout($trackId);
                    
                    if (!$response) {
                        throw new \Exception('No response received from MISPay API');
                    }
                    
                    $logger->info('Manual track completed successfully for Order #' . $incrementId);
                } catch (\Exception $e) {
                    throw new \Exception('Error processing Order #' . $incrementId . ': ' . $e->getMessage());
                }
            }
            
        } catch (\Exception $e) {
            $logger->error('MISPay Track Checkout Manual Trigger - Error: ' . $e->getMessage());
            throw $e;
        }

        // Save the log file
        $writer->close();
    }
}