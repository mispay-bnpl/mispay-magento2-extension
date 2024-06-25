<?php

namespace MISPay\MISPayMethodDynamicCallback\Controller\Success;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Session\SuccessValidator;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\Controller\Result\RedirectFactory;

/**
 * Class Index
 *
 * @package MISPay\MISPayMethodDynamicCallback\Controller\Success
 */
class Index implements \Magento\Framework\App\Action\HttpGetActionInterface
{

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
     * @var RedirectFactory
     */
    protected $redirectFactory;

    /**
     * @param RedirectFactory          $redirectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CheckoutSession          $checkoutSession
     * @param SuccessValidator         $successValidator
     */
    public function __construct(
        RedirectFactory $redirectFactory,
        OrderRepositoryInterface $orderRepository,
        CheckoutSession $checkoutSession,
        SuccessValidator $successValidator
    ) {
        $this->redirectFactory = $redirectFactory;
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->successValidator = $successValidator;
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (
            $order->getState() == Order::STATE_PENDING_PAYMENT ||
            $order->getState() == Order::STATE_NEW ||
            $order->getState() == null
        ) {
            $order->setState(Order::STATE_PENDING_PAYMENT);
            $this->orderRepository->save($order);
        }
        $this->checkoutSession->setLastOrderId($order->getId())
            ->setLastSuccessQuoteId($order->getQuoteId())
            ->setLastRealOrderId($order->getIncrementId())
            ->setLastOrderStatus($order->getStatus());
        if (!$this->successValidator->isValid()) {
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }
        return $this->redirectFactory->create()->setPath('checkout/onepage/success');
    }
}
