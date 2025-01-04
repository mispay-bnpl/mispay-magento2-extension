<?php

namespace MISPay\MISPayMethod\Helper;

use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\OrderFactory;
use Zend_Log_Writer_Stream;
use Zend_Log;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class MISPayRequestHelper
 *
 * @package MISPay\MISPayMethod\Helper
 */
class MISPayRequestHelper
{
    /**
     * @var MISPayHelper
     */
    protected $mispayHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var null|string
     */
    private $accessToken = null;

    /**
     * @var Zend_Log_Writer_Stream
     */
    private $writer;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var bool
     */
    private $isDebugEnabled;

    /**
     * MISPayRequestHelper constructor.
     *
     * @param MISPayHelper $mispayHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        MISPayHelper $mispayHelper,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->mispayHelper = $mispayHelper;
        $this->orderRepository = $orderRepository;
        $this->scopeConfig = $scopeConfig;
        
        $this->isDebugEnabled = $this->scopeConfig->getValue('payment/mispaymethod/debug');

        if ($this->isDebugEnabled) {
            $timestamp = date('YmdHis') . substr(microtime(), 2, 3);
            $this->writer = new Zend_Log_Writer_Stream(BP . '/var/log/mispay_request_helper_' . $timestamp . '.log');
            $logger = new Zend_Log();
            $logger->addWriter($this->writer);
            $this->logger = $logger;
        }
    }

    private function log($message, $type = 'info')
    {
        if (!$this->isDebugEnabled) {
            return;
        }

        switch ($type) {
            case 'error':
                $this->logger->error($message);
                break;
            case 'debug':
                $this->logger->debug($message);
                break;
            case 'warning':
                $this->logger->warn($message);
                break;
            default:
                $this->logger->info($message);
        }
    }

    private function setAccessToken($token)
    {
        $this->accessToken = $token;
    }

    public function getAccessToken()
    {
        $isValid = $this->mispayHelper->isJWTValid($this->accessToken);
        if ($isValid === true) {
            return $this->accessToken;
        }
        return $this->getToken();
    }

    /**
     * @return mixed|string
     */
    public function getToken()
    {
        $curl = curl_init();
        $url = $this->mispayHelper->getBaseUrl() . $this->mispayHelper->getEndpoints()['token'];
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array(
                'x-app-secret: ' . $this->mispayHelper->getCredentials()->appSecret,
                "x-app-id: " . $this->mispayHelper->getCredentials()->appId
            ),
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        );
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->log(curl_error($curl), 'debug');
            return null;
        }
        curl_close($curl);
        $responseBody = json_decode($response);

        if ($responseBody->status !== 'success') {
            $this->log(json_encode($responseBody), 'debug');
            return null;
        }

        $dec = $this->mispayHelper->decrypt($responseBody->result->token);
        $payload = json_decode(($dec));

        if (empty($payload->token)) {
            $this->log(json_encode($responseBody), 'debug');
            return null;
        }

        $this->setAccessToken($payload->token);
        return $payload->token;
    }

    public function trackCheckout($trackId)
    {
        if(empty($trackId)) {
            $this->log('TrackCheckout - Track ID is empty', 'error');
            return null;
        }

        try {
            $this->log('TrackCheckout - Starting for Track ID: ' . $trackId);
            
            // Get Access Token
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                $this->log('TrackCheckout - Failed to get access token', 'error');
                return null;
            }

            // Prepare API URL
            $url = $this->mispayHelper->getBaseUrl() . '/track-checkout/' . $trackId;
            
            $this->log('TrackCheckout - Calling URL: ' . $url);

            // Set up API request
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'x-app-secret: ' . $this->mispayHelper->getCredentials()->appSecret,
                    "x-app-id: " . $this->mispayHelper->getCredentials()->appId,
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ]
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            
            $this->log('TrackCheckout - Response Code: ' . $httpCode);
            $this->log('TrackCheckout - Response: ' . $response);

            if (curl_errno($curl)) {
                $this->log('TrackCheckout - Curl error: ' . curl_error($curl), 'error');
                curl_close($curl);
                return null;
            }

            curl_close($curl);
            
            $responseData = json_decode($response);
            
            if (!$responseData) {
                $this->log('TrackCheckout - Invalid JSON response', 'error');
                return null;
            }

            // Process order status update
            if (isset($responseData->result->status) && isset($responseData->result->orderId)) {
                $orderId = $responseData->result->orderId;
                $status = $responseData->result->status;
                
                $this->log('TrackCheckout - Processing order ' . $orderId . ' with status: ' . $status);

                try {
                    $order = $this->orderRepository->get($orderId);

                    if (!$order->getId()) {
                        $this->log('TrackCheckout - Order not found: ' . $orderId, 'error');
                        return $responseData;
                    }
                    
                    switch ($status) {
                        case 'success':
                            $this->log('Updating order ' . $orderId . ' to PROCESSING');
                            $order->setState(Order::STATE_PROCESSING)
                                  ->setStatus(Order::STATE_PROCESSING);
                            
                            $order->addStatusHistoryComment(
                                __('Payment completed successfully through MISPay. Track ID: %1', $trackId)
                            )->setIsCustomerNotified(true);
                            break;

                        case 'canceled':
                        case 'error':
                            $this->log('Updating order ' . $orderId . ' to CANCELED');
                            $order->setState(Order::STATE_CANCELED)
                                  ->setStatus(Order::STATE_CANCELED);
                            
                            $order->addStatusHistoryComment(
                                __('Payment was canceled or failed through MISPay. Track ID: %1', $trackId)
                            )->setIsCustomerNotified(true);
                            break;

                        case 'pending':
                        case 'in-progress':
                            $this->log('Order ' . $orderId . ' still in progress');
                            $order->addStatusHistoryComment(
                                __('Payment is still in progress through MISPay. Track ID: %1', $trackId)
                            )->setIsCustomerNotified(false);
                            break;

                        default:
                            $this->log('Unknown status received: ' . $status, 'warning');
                            break;
                    }

                    // Save payment information
                    $payment = $order->getPayment();
                    $payment->setLastTransId($trackId)
                        ->setTransactionId($trackId)
                        ->setAdditionalInformation('mispay_status', $status)
                        ->setIsTransactionClosed(in_array($status, ['success', 'canceled', 'error']))
                        ->save();

                    // Save order
                    $this->orderRepository->save($order);
                    
                    $this->log('Order ' . $orderId . ' status updated successfully');
                } catch (NoSuchEntityException $e) {
                    $this->log('Order not found: ' . $e->getMessage(), 'error');
                } catch (\Exception $e) {
                    $this->log('Error saving order: ' . $e->getMessage(), 'error');
                }
            }

            return $responseData;
        } catch (\Exception $e) {
            $this->log('TrackCheckout - Exception: ' . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function startCheckoutSession()
    {
        try {
            $accessToken = $this->getAccessToken();
            $url = $this->mispayHelper->getBaseUrl() . $this->mispayHelper->getEndpoints()['startCheckoutSession'];
            $lang = $this->mispayHelper->getLang() == 'en' ? 'en' : 'ar';

            // Get the order details
            $order = $this->mispayHelper->getOrder();
            $totalPrice = $order->getSubtotal();
            $shippingAmount = $order->getShippingAmount();
            $vat = $order->getTaxAmount();
            
            // Verify the formula: totalPrice + shippingAmount + vat = purchaseAmount
            $calculatedTotal = $totalPrice + $shippingAmount + $vat;
            if (abs($calculatedTotal - $this->mispayHelper->getPaymentAmount()) > 0.01) {
                $this->log('Purchase amount mismatch: ' . $calculatedTotal . ' != ' . $this->mispayHelper->getPaymentAmount(), 'error');
            }

            // Prepare order details
            $orderDetails = [
                'items' => []
            ];

            foreach ($order->getAllItems() as $item) {
                $itemDetails = [
                    'quantity' => $item->getQtyOrdered(),
                    'unitPrice' => $item->getPrice()
                ];

                // Add nameArabic or nameEnglish conditionally
                if ($item->getNameArabic()) {
                    $itemDetails['nameArabic'] = $item->getNameArabic();
                } else {
                    $itemDetails['nameEnglish'] = $item->getName();
                }

                $orderDetails['items'][] = $itemDetails;
            }

            $options = array(
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => array(
                    'x-app-secret: ' . $this->mispayHelper->getCredentials()->appSecret,
                    "x-app-id: " . $this->mispayHelper->getCredentials()->appId,
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ),
                CURLOPT_POSTFIELDS => json_encode([
                    "version" => "v1.2",
                    "orderId" => $this->mispayHelper->getIncerementOrderId(),
                    "purchaseAmount" => $this->mispayHelper->getPaymentAmount(),
                    "totalPrice" => $totalPrice,
                    "shippingAmount" => $shippingAmount,
                    "vat" => $vat,
                    "purchaseCurrency" => "SAR",
                    "lang" => $lang,
                    "customerDetails" => [
                        "mobileNumber" => $this->mispayHelper->getUserPhone(),
                        "email" => $this->mispayHelper->getEmail()
                    ],
                    "orderDetails" => $orderDetails
                ]),
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
            );

            $curl = curl_init();
            curl_setopt_array($curl, $options);
            $response = curl_exec($curl);
            $responseBody = json_decode($response);

            $this->log('StartCheckoutSession Response: ' . json_encode($responseBody));

            if ($responseBody->status === 'success' && !empty($responseBody->result->trackId)) {
                // Save trackId to order
                $order = $this->mispayHelper->getOrder();
                $payment = $order->getPayment();
                
                // Debug log current additional data
                $this->log('Current Additional Data: ' . $payment->getAdditionalData());
                
                // Get existing additional data
                $additionalData = json_decode($payment->getAdditionalData() ?: '{}', true) ?: [];
                
                // Add track ID
                $additionalData['mispay_checkout_track_id'] = $responseBody->result->trackId;
                
                // Save back to payment
                $payment->setAdditionalData(json_encode($additionalData));
                $payment->setAdditionalInformation('mispay_checkout_track_id', $responseBody->result->trackId);
                
                try {
                    // Save order
                    $this->orderRepository->save($order);
                    
                    // Verify save
                    $this->log('Saved Track ID: ' . $responseBody->result->trackId);
                    $this->log('Updated Additional Data: ' . $payment->getAdditionalData());

                    // Immediately trigger initial trackCheckout
                    $this->log('Triggering initial trackCheckout for Track ID: ' . $responseBody->result->trackId);
                    $trackResponse = $this->trackCheckout($responseBody->result->trackId);
                    
                    if ($trackResponse) {
                        $this->log('Initial trackCheckout response: ' . json_encode($trackResponse));
                    } else {
                        $this->log('Initial trackCheckout failed', 'error');
                    }
                } catch (\Exception $e) {
                    $this->log('Error saving order or tracking checkout: ' . $e->getMessage(), 'error');
                }
            } else {
                $this->log('Invalid response from StartCheckoutSession: ' . json_encode($responseBody), 'error');
            }

            return $responseBody->result;
        } catch (\Exception $e) {
            $this->log('StartCheckoutSession Error: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }

    public function endCheckoutSession($checkoutId)
    {
        if(empty($checkoutId)) {
            $this->log('EndCheckoutSession - Checkout ID is empty', 'error');
            return null;
        }

        $accessToken = $this->getAccessToken();
        $url = $this->mispayHelper->getBaseUrl() . $this->mispayHelper->getEndpoints()['endCheckoutSession'];
        $url = str_replace(':id', $checkoutId, $url);
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array(
                'x-app-secret: ' . $this->mispayHelper->getCredentials()->appSecret,
                "x-app-id: " . $this->mispayHelper->getCredentials()->appId,
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        );

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $responseBody = json_decode($response);
        return $responseBody;
    }

    public function __destruct()
    {
        if ($this->writer) {
            $this->writer->close();
        }
    }
}