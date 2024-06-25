<?php

namespace MISPay\MISPayMethodDynamicCallback\Controller\Callback;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Session\SuccessValidator;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use \Magento\Framework\App\Request\Http;
use Psr\Log\LoggerInterface;
use MISPay\MISPayMethodDynamicCallback\Helper\MISPayHelper;
use MISPay\MISPayMethodDynamicCallback\Helper\MISPayRequestHelper;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;

/**
 * Class Index
 *
 * @package MISPay\MISPayMethodDynamicCallback\Controller\Callback
 */
class Index implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    /**
     * @var Http
     */
    protected $request;

    /**
     * @var RedirectFactory
     */
    protected $redirectFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var SuccessValidator
     */
    private $successValidator;

    /**
     * @var OrderSender
     */
    private $orderSender;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MessageInterface
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
     *
     * @param Http                     $request
     * @param RedirectFactory          $redirectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CheckoutSession          $checkoutSession
     * @param SuccessValidator         $successValidator
     * @param OrderSender              $orderSender
     * @param LoggerInterface          $logger
     * @param ManagerInterface          $messageManager
     * @param MISPayHelper             $mispayHelper
     * @param MISPayRequestHelper      $mispayRequestHelper
     */
    public function __construct(
        Http $request,
        RedirectFactory $redirectFactory,
        OrderRepositoryInterface $orderRepository,
        CheckoutSession $checkoutSession,
        SuccessValidator $successValidator,
        OrderSender $orderSender,
        LoggerInterface $logger,
        ManagerInterface $messageManager,
        MISPayHelper $mispayHelper,
        MISPayRequestHelper $mispayRequestHelper
    ) {
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->successValidator = $successValidator;
        $this->orderSender = $orderSender;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
        $this->mispayHelper = $mispayHelper;
        $this->mispayRequestHelper = $mispayRequestHelper;
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        $encrypted = $this->request->getQuery('_');
        $decrypted = json_decode($this->mispayHelper->decryptResult($encrypted));

        $order = $this->checkoutSession->getLastRealOrder();
        $payment = $order->getPayment();
        $additionalData = json_decode($payment->getAdditionalData());

        if (!isset($decrypted->checkoutId) || $decrypted->code !== 'MP00') {
            $order->setState(Order::STATE_CANCELED)->setStatus(Order::STATE_CANCELED);
            $this->orderRepository->save($order);
            $this->messageManager->addErrorMessage(__('An error occurred during payment process. Reason: ') . __('CODE_' . $decrypted->code));
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        $checkoutId = $decrypted->checkoutId;

        if (
            $order->getState() == Order::STATE_PENDING_PAYMENT ||
            $order->getState() == Order::STATE_NEW ||
            $order->getState() == null
        ) {
            $order->setState($this->mispayHelper->getOrderStatus())->setStatus($this->mispayHelper->getOrderStatus());
            $this->orderRepository->save($order);
        }

        $status = $order->getStatus();

        $this->checkoutSession->setLastOrderId($order->getId())
            ->setLastSuccessQuoteId($order->getQuoteId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($status);

        // if ($decrypted->code !== 'MP00' && !empty($additionalData) && isset($additionalData->mispay_checkout_track_id)) {
        //     $details = $this->mispayRequestHelper->trackCheckout($additionalData->mispay_checkout_track_id);
        //     if ($details->status !== 'success') {
        //         $this->messageManager->addErrorMessage(__('An error occurred during payment process. Reason: ') . __('CODE_' . $details->code));
        //         return $this->redirectFactory->create()->setPath('checkout/cart');
        //     }
        // }

        $endPaymentCall = $this->mispayRequestHelper->endCheckoutSession($checkoutId);

        if (!$endPaymentCall || $endPaymentCall->status != 'success') {
            $this->messageManager->addErrorMessage(__('An error occurred during payment process. Reason: ') . __('CODE_' . $decrypted->code));
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        $additionalData->transactionId = $endPaymentCall->result->transactionId;
        $payment->setAdditionalData(json_encode($additionalData));

        if (!$this->successValidator->isValid()) {
            $this->messageManager->addErrorMessage(__('An error occurred during payment process. Reason:  NOT_VALID'));
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }

        try {
            $this->orderSender->send($order);
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Checkout\Model\Cart */
        $cart = $objectManager->get('Magento\Checkout\Model\Cart');

        $cart->truncate()->save();
        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
        $this->orderRepository->save($order);

        return $this->redirectFactory->create()->setPath('checkout/onepage/success');
    }
}
