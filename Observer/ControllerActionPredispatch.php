<?php

namespace MISPay\MISPayMethodDynamicCallback\Observer;

use Magento\Framework\App\Response\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use MISPay\MISPayMethodDynamicCallback\Helper\MISPayHelper;
use MISPay\MISPayMethodDynamicCallback\Helper\MISPayRequestHelper;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Class ControllerActionPredispatch
 *
 * @package MISPay\MISPayMethodDynamicCallback\Observer
 */
class ControllerActionPredispatch implements ObserverInterface
{

    /**
     * @var Http
     */
    protected $request;

    /**
     * @var mixed
     */
    protected $urlBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var MISPayRequestHelper
     */
    protected $mispayRequestHelper;

    /**
     * @var MISPayHelper
     */
    protected $mispayHelper;

    /**
     * ControllerActionPredispatch constructor.
     *
     * @param Http                           $redirect
     * @param LoggerInterface                $logger
     * @param ManagerInterface               $messageManager
     * @param OrderRepositoryInterface       $orderRepository
     * @param MISPayHelper                   $mispayHelper
     * @param MISPayRequestHelper            $mispayRequestHelper
     */
    public function __construct(
        Http $redirect,
        LoggerInterface $logger,
        ManagerInterface $messageManager,
        OrderRepositoryInterface $orderRepository,
        MISPayHelper $mispayHelper,
        MISPayRequestHelper $mispayRequestHelper

    ) {
        $this->request = $redirect;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
        $this->orderRepository = $orderRepository;
        $this->mispayHelper = $mispayHelper;
        $this->mispayRequestHelper = $mispayRequestHelper;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $request = $observer->getData('request');
        if ($request->getModuleName() != "checkout" || $request->getActionName() != "success") {
            return;
        }

        $orderId = $this->mispayHelper->getRealOrderId();
        $incerementOrderId = $this->mispayHelper->getIncerementOrderId();

        if (!$orderId) {
            return;
        }

        $order = $this->orderRepository->get($orderId);
        $isMISPayOrder = $this->mispayHelper->isMispayOrder($order);

        if (!$isMISPayOrder || $order->getState() != Order::STATE_NEW) {
            return;
        }

        $this->urlBuilder = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
        $url = $this->urlBuilder->getUrl("checkout/cart");

        $startCheckoutResult = $this->mispayRequestHelper->startCheckoutSession(true); // isDynamic=true

        // die(print_r($startCheckoutResult));

        if (empty($startCheckoutResult) || empty($startCheckoutResult->url)) {
            $err = !empty($startCheckoutResult->code) ? $startCheckoutResult->code . ': ' . $startCheckoutResult->message : '';
            $this->logger->critical($err);
            $this->messageManager->addErrorMessage('An error occurred during the starting checkout process. Please try again.' . $err);
        } else {
            $url = $startCheckoutResult->url;
            $details = array(
                'TIMESTAMP' => gmdate("YmdHis", time()),
                'amount' => $order->getTotalDue(),
                'mispay_checkout_test_mode' => $this->mispayHelper->getTestMode(),
                'mispay_checkout_user_ip' => $this->mispayHelper->getUserIp(),
                'mispay_checkout_app_id' => $this->mispayHelper->getMerchantAppId(),
                'mispay_checkout_url' => $url,
                'mispay_checkout_track_id' => $startCheckoutResult->trackId,
                'order_id' => $orderId,
                'order_increment_id' => $incerementOrderId,
            );
            $payment = $order->getPayment();
            $payment->setAdditionalData(json_encode($details));

            $this->orderRepository->save($order);
        }

        $this->refillCart($order);



        $this->request->setRedirect($url);
    }

    private function refillCart(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        // refill cart
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logger = $objectManager->get('Psr\Log\LoggerInterface');
        $cart = $objectManager->get('Magento\Checkout\Model\Cart');
        /** @var \Magento\Sales\Api\Data\OrderItemInterface[] */
        $items = $order->getItems();

        foreach ($items as $item) {
            try {
                $cart->addOrderItem($item);
            } catch (\Exception $e) {
                $logger->critical($e);
            }
        }
        $cart->save();
    }
}
