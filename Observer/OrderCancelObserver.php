<?php

namespace MISPay\MISPayMethodDynamicCallback\Observer;

use MISPay\MISPayMethodDynamicCallback\Helper\MISPayHelper;
use MISPay\MISPayMethodDynamicCallback\Helper\MISPayLogger;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Transaction\Repository as TransactionRepository;
use Psr\Log\LoggerInterface;

class OrderCancelObserver implements ObserverInterface
{
    /**
     * @var MISPayLogger
     */
    protected $logger;

    /**
     * @var MISPayHelper
     */
    protected $mispayHelper;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var TransactionRepository
     */
    protected $transactionRepository;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    public function __construct(
        LoggerInterface          $logger,
        MISPayHelper             $mispayHelper,
        SearchCriteriaBuilder    $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository,
        TransactionRepository    $transactionRepository,
        FilterBuilder            $filterBuilder

    ) {
        $this->logger = new MISPayLogger($logger);
        $this->mispayHelper = $mispayHelper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->transactionRepository = $transactionRepository;
        $this->filterBuilder = $filterBuilder;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        $payment = $order->getPayment();
        $additionalData = json_decode($payment->getAdditionalData());

        if (!empty($additionalData) && !empty($additionalData->mispay_checkout_track_id)) {
            $this->logger->setTrackId($additionalData->mispay_checkout_track_id);
        }

        $this->logger->debug('Cancel flow started for the order with id: ' . $order->getId());
        $this->logger->debug('Additional information for the order\'s payment: ' . print_r(json_encode($order->getPayment()->getAdditionalInformation()), true));

        $searchFilter[] = $this->filterBuilder->setField('order_id')
            ->setValue($order->getId())
            ->create();
        $searchCriteria = $this->searchCriteriaBuilder->addFilters($searchFilter)->create();
        $transactionsRelatedToOrder = $this->transactionRepository->getList($searchCriteria);
        $this->logger->debug('Transaction count for the order: ' . count($transactionsRelatedToOrder->getItems()));

        // If there's some transactions for this order and the order is a Mispay order, then should refund it
        if ($this->mispayHelper->isMispayOrder($order) and count($transactionsRelatedToOrder->getItems()) > 0) {
            // TODO Add refund logic here
            $this->logger->debug('The order has to be refunded');
        } else {
            $this->logger->debug('Order does not have any transaction bound to it, so no refund is required');
        }
        $this->logger->debug('Cancel flow ended for the order' . $order->getId());

        return true;
    }
}
