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
     * @var Zend_Log_Writer_Stream
     */
    private $writer;

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

        $this->orderRepository = $orderRepository;

        $timestamp = date('YmdHis') . substr(microtime(), 2, 3);
        $this->writer = new Zend_Log_Writer_Stream(BP . '/var/log/mispay_cron_' . $timestamp . '.log');
        $this->logger = new Zend_Log();
        $this->logger->addWriter($this->writer);    
    }

    public function execute()
    {
        try {
            $this->logger->info('MISPay Track Checkout Cron - Started execution at ' . date('Y-m-d H:i:s'));
            
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
            $this->logger->info('Collection Query: ' . $orders->getSelect()->__toString());
            $this->logger->info('Found ' . $orders->count() . ' orders to process');

            if ($orders->count() == 0) {
                $this->logger->info('No pending MISPay orders found in the last 24 hours');
                $this->logger->info('MISPay Track Checkout Cron - Finished execution at ' . date('Y-m-d H:i:s'));
                $this->logger->info('-------------------');
                return;
            }

            foreach ($orders as $order) {
                try {
                    $payment = $order->getPayment();
                    $incrementId = $order->getIncrementId();
                    
                    // Log order details for debugging
                    $this->logger->info('-------------------');
                    $this->logger->info('Processing Order #' . $incrementId);
                    $this->logger->info('Order ID: ' . $order->getId());
                    $this->logger->info('Order State: ' . $order->getState());
                    $this->logger->info('Order Status: ' . $order->getStatus());
                    $this->logger->info('Order Created At: ' . $order->getCreatedAt());
                    $this->logger->info('Payment Method: ' . $payment->getMethod());
                    
                    // Get track ID from both possible locations
                    $additionalData = json_decode($payment->getAdditionalData() ?: '{}', true) ?: [];
                    $additionalInfo = $payment->getAdditionalInformation();
                    
                    $this->logger->info('Additional Data: ' . json_encode($additionalData));
                    $this->logger->info('Additional Info: ' . json_encode($additionalInfo));
                    
                    $trackId = $additionalData['mispay_checkout_track_id'] 
                           ?? $additionalInfo['mispay_checkout_track_id'] 
                           ?? null;

                    if (empty($trackId)) {
                        $this->logger->info('Order #' . $incrementId . ' - No track ID found');
                        continue;
                    }

                    $this->logger->info('Processing order #' . $incrementId . ' with track ID: ' . $trackId);
                    
                    // Track the checkout
                    $response = $this->mispayRequestHelper->trackCheckout($trackId);
                    
                    if ($response) {
                        $this->logger->info('Track response for Order #' . $incrementId . ': ' . json_encode($response));
                        
                        // Update order status based on response
                        if (isset($response->result->status)) {
                            switch ($response->result->status) {
                                case 'success':
                                    $order->setState(Order::STATE_PROCESSING)
                                          ->setStatus(Order::STATE_PROCESSING);
                                    $this->logger->info('Updated order #' . $incrementId . ' to PROCESSING');
                                    break;
                                case 'canceled':
                                case 'error':
                                    $order->setState(Order::STATE_CANCELED)
                                          ->setStatus(Order::STATE_CANCELED);
                                    $this->logger->info('Updated order #' . $incrementId . ' to CANCELED');
                                    break;
                            }
                            $this->orderRepository->save($order);
                        }
                    } else {
                        $this->logger->error('No response received for Order #' . $incrementId);
                    }
                    $this->logger->info('-------------------');

                } catch (\Exception $e) {
                    $this->logger->error('Error processing Order #' . $incrementId . ': ' . $e->getMessage());
                    $this->logger->error('Stack trace: ' . $e->getTraceAsString());
                }
            }
            
            $this->logger->info('MISPay Track Checkout Cron - Finished execution at ' . date('Y-m-d H:i:s'));
            
        } catch (\Exception $e) {
            $this->logger->error('MISPay Track Checkout Cron - Error: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
        }
    }

    public function executeForOrder($orderId)
    {
        if (empty($orderId)) {
           return false;
        }
        
        try {
            $this->logger->info('MISPay Track Checkout Manual Trigger - Started for Order #' . $orderId);
            
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
                    
                    $this->logger->info('Manual track completed successfully for Order #' . $incrementId);
                } catch (\Exception $e) {
                    throw new \Exception('Error processing Order #' . $incrementId . ': ' . $e->getMessage());
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error('MISPay Track Checkout Manual Trigger - Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function __destruct()
    {
        if ($this->writer) {
            $this->writer->close();
        }
    }
}