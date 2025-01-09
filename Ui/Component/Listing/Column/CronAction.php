<?php

namespace MISPay\MISPayMethod\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\OrderRepository;

class CronAction extends Column
{
    const PAYMENT_METHOD_CODE = 'mispaymethod';
    const STATUS_PENDING = 'pending';

    protected $urlBuilder;
    protected $orderRepository;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        OrderRepository $orderRepository,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->orderRepository = $orderRepository;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $name = $this->getData('name');
                if (isset($item['entity_id'])) {
                    try {
                        $order = $this->orderRepository->get($item['entity_id']);
                        $payment = $order->getPayment();
                        $paymentMethod = $payment ? $payment->getMethod() : '';
                        $orderStatus = $order->getStatus();

                        if ($paymentMethod === self::PAYMENT_METHOD_CODE && 
                            strtolower($orderStatus) === self::STATUS_PENDING) {
                            $item[$name] = [
                                'runcron' => [
                                    'href' => $this->urlBuilder->getUrl(
                                        'mispay/order/runcron',
                                        ['id' => $item['entity_id']]
                                    ),
                                    'label' => __('Update Order Status'),
                                    'confirm' => [
                                        'title' => __('Update Order Status'),
                                        'message' => __('Are you sure you want to check the order status now?')
                                    ]
                                ]
                            ];
                        }
                    } catch (\Exception $e) {
                        // Leave empty if there's an error
                        $item[$name] = [];
                    }
                }
            }
        }
        return $dataSource;
    }
} 