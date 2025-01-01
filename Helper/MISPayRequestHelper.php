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
     * MISPayRequestHelper constructor.
     *
     * @param MISPayHelper $mispayHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        MISPayHelper $mispayHelper,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->mispayHelper = $mispayHelper;
        $this->orderRepository = $orderRepository;

        // Create a separate log file for MISPay cron
        $writer = new Zend_Log_Writer_Stream(BP . '/var/log/mispay_cron.log');
        $logger = new Zend_Log();
        $logger->addWriter($writer);

        $this->logger = $logger;
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
            $this->logger->debug(curl_error($curl));
            return null;
        }
        curl_close($curl);
        $responseBody = json_decode($response);

        if ($responseBody->status !== 'success') {
            $this->logger->debug(json_encode($responseBody));
            return null;
        }

        $dec = $this->mispayHelper->decrypt($responseBody->result->token);
        $payload = json_decode(($dec));

        if (empty($payload->token)) {
            $this->logger->debug(json_encode($responseBody));
            return null;
        }

        $this->setAccessToken($payload->token);
        return $payload->token;
    }

    public function trackCheckout($trackId)
    {
        try {
            $this->logger->info('TrackCheckout - Starting for Track ID: ' . $trackId);
            
            // Get Access Token
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                $this->logger->error('TrackCheckout - Failed to get access token');
                return null;
            }

            // Prepare API URL
            $url = $this->mispayHelper->getBaseUrl() . '/track-checkout/' . $trackId;
            
            $this->logger->info('TrackCheckout - Calling URL: ' . $url);

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
            
            $this->logger->info('TrackCheckout - Response Code: ' . $httpCode);
            $this->logger->info('TrackCheckout - Response: ' . $response);

            if (curl_errno($curl)) {
                $this->logger->error('TrackCheckout - Curl error: ' . curl_error($curl));
                curl_close($curl);
                return null;
            }

            curl_close($curl);
            
            $responseData = json_decode($response);
            
            if (!$responseData) {
                $this->logger->error('TrackCheckout - Invalid JSON response');
                return null;
            }

            // Process order status update
            if (isset($responseData->result->status) && isset($responseData->result->orderId)) {
                $orderId = $responseData->result->orderId;
                $status = $responseData->result->status;
                
                $this->logger->info('TrackCheckout - Processing order ' . $orderId . ' with status: ' . $status);

                try {
                    $order = $this->orderRepository->get($orderId);

                    if (!$order->getId()) {
                        $this->logger->error('TrackCheckout - Order not found: ' . $orderId);
                        return $responseData;
                    }
                    
                    switch ($status) {
                        case 'success':
                            $this->logger->info('Updating order ' . $orderId . ' to PROCESSING');
                            $order->setState(Order::STATE_PROCESSING)
                                  ->setStatus(Order::STATE_PROCESSING);
                            
                            $order->addStatusHistoryComment(
                                __('Payment completed successfully through MISPay. Track ID: %1', $trackId)
                            )->setIsCustomerNotified(true);
                            break;

                        case 'canceled':
                        case 'error':
                            $this->logger->info('Updating order ' . $orderId . ' to CANCELED');
                            $order->setState(Order::STATE_CANCELED)
                                  ->setStatus(Order::STATE_CANCELED);
                            
                            $order->addStatusHistoryComment(
                                __('Payment was canceled or failed through MISPay. Track ID: %1', $trackId)
                            )->setIsCustomerNotified(true);
                            break;

                        case 'pending':
                        case 'in-progress':
                            $this->logger->info('Order ' . $orderId . ' still in progress');
                            $order->addStatusHistoryComment(
                                __('Payment is still in progress through MISPay. Track ID: %1', $trackId)
                            )->setIsCustomerNotified(false);
                            break;

                        default:
                            $this->logger->warning('Unknown status received: ' . $status);
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
                    
                    $this->logger->info('Order ' . $orderId . ' status updated successfully');
                } catch (NoSuchEntityException $e) {
                    $this->logger->error('Order not found: ' . $e->getMessage());
                } catch (\Exception $e) {
                    $this->logger->error('Error saving order: ' . $e->getMessage());
                }
            }

            return $responseData;
        } catch (\Exception $e) {
            $this->logger->error('TrackCheckout - Exception: ' . $e->getMessage());
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
                $this->logger->error('Purchase amount mismatch: ' . $calculatedTotal . ' != ' . $this->mispayHelper->getPaymentAmount());
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

            $this->logger->info('StartCheckoutSession Response: ' . json_encode($responseBody));

            if ($responseBody->status === 'success' && !empty($responseBody->result->trackId)) {
                // Save trackId to order
                $order = $this->mispayHelper->getOrder();
                $payment = $order->getPayment();
                
                // Debug log current additional data
                $this->logger->info('Current Additional Data: ' . $payment->getAdditionalData());
                
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
                    $this->logger->info('Saved Track ID: ' . $responseBody->result->trackId);
                    $this->logger->info('Updated Additional Data: ' . $payment->getAdditionalData());

                    // Immediately trigger initial trackCheckout
                    $this->logger->info('Triggering initial trackCheckout for Track ID: ' . $responseBody->result->trackId);
                    $trackResponse = $this->trackCheckout($responseBody->result->trackId);
                    
                    if ($trackResponse) {
                        $this->logger->info('Initial trackCheckout response: ' . json_encode($trackResponse));
                    } else {
                        $this->logger->error('Initial trackCheckout failed');
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error saving order or tracking checkout: ' . $e->getMessage());
                }
            } else {
                $this->logger->error('Invalid response from StartCheckoutSession: ' . json_encode($responseBody));
            }

            return $responseBody->result;
        } catch (\Exception $e) {
            $this->logger->error('StartCheckoutSession Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function endCheckoutSession($checkoutId)
    {
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
}