<?php

namespace MISPay\MISPayMethod\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class MISPayHelper
 *
 * @package MISPay\MISPayMethod\Helper
 */
class MISPayHelper
{

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $config;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Context               $context,
        Session               $checkoutSession,
        OrderFactory          $orderFactory,
        StoreManagerInterface $storeManager,
        LoggerInterface       $logger
    ) {
        $this->config = $context->getScopeConfig();
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function encrypt(string $plaintext)
    {
        $salt = openssl_random_pseudo_bytes(16);
        $nonce = openssl_random_pseudo_bytes(12);
        $key = hash_pbkdf2("sha256", $this->getMerchantSecret(), $salt, 40000, 32, true);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, 1, $nonce, $tag);

        return base64_encode($salt . $nonce . $ciphertext . $tag);
    }

    public function decrypt(string $ciphertext)
    {
        $input = base64_decode($ciphertext);
        $salt = substr($input, 0, 16);
        $nonce = substr($input, 16, 12);
        $ciphertext = substr($input, 28, -16);
        $tag = substr($input, -16);
        $key = hash_pbkdf2("sha256", $this->getMerchantSecret(), $salt, 40000, 32, true);

        return openssl_decrypt($ciphertext, 'aes-256-gcm', $key, 1, $nonce, $tag);
    }

    public function decryptResult($encyrptedValue)
    {
        return $this->decrypt(base64_decode($encyrptedValue));
    }

    public function decryptJWT($token)
    {
        return json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $token)[1]))));
    }

    public function isJWTValid($token)
    {
        if (empty($token)) {
            return false;
        }
        try {
            $decoded = $this->decryptJWT($token);
            $expiry = date('Y-m-d H:i:s', $decoded->exp);
            return strtotime($expiry) > strtotime('now');
        } catch (\Throwable $th) {
            $this->logger->error($th);
        }
        return false;
    }

    /**
     * @return string
     */
    public function getScopeInterface()
    {
        return ScopeInterface::SCOPE_STORE;
    }

    public function getBaseUrl()
    {
        if ($this->getTestMode() == 1) {
            return "https://api.mispay.co/sandbox/v1/api";
        }
        return "https://api.mispay.co/v1/api";
    }

    public function getCredentials()
    {
        $credentials = new \stdClass();

        $credentials->appId = $this->getMerchantAppId();
        $credentials->appSecret = $this->getMerchantSecret();

        return $credentials;
    }

    public static function getEndpoints()
    {
        return [
            'token' => '/token',
            'startCheckoutSession' => '/start-checkout',
            'getCheckoutDetail' => '/checkout/:id',
            "trackCheckout" => "/track-checkout/:id",
            'endCheckoutSession' => '/checkout/:id/end',
        ];
    }

    /**
     * @return mixed
     */
    public function getMerchantAppId()
    {
        return $this->config->getValue('payment/mispaymethod/merchant_app_id', $this->getScopeInterface());
    }

    /**
     * @return bool
     */
    public function isWidgetEnabled()
    {
        $value = $this->config->getValue('payment/mispaymethod/is_widget_enabled', $this->getScopeInterface());
        return boolval($value);
    }

    /**
     * @return mixed|int
     */
    public function getMinOrderTotal()
    {
        $value = $this->config->getValue('payment/mispaymethod/min_order_total', $this->getScopeInterface());
        return floatval($value);
    }

    /**
     * @return mixed|int
     */
    public function getMaxOrderTotal()
    {
        $value = $this->config->getValue('payment/mispaymethod/max_order_total', $this->getScopeInterface());
        return floatval($value);
    }

    /**
     * @return mixed|int
     */
    public function getMinWidgetAmountLimit()
    {
        $value = $this->config->getValue('payment/mispaymethod/min_widget_limit', $this->getScopeInterface());
        return floatval($value);
    }

    /**
     * @return mixed|int
     */
    public function getMaxWidgetAmountLimit()
    {
        $value = $this->config->getValue('payment/mispaymethod/max_widget_limit', $this->getScopeInterface());
        return floatval($value);
    }

    /**
     * @return mixed|string
     */
    public function getTitle()
    {
        return $this->config->getValue('payment/mispaymethod/title', $this->getScopeInterface());
    }

    /**
     * @return mixed|string
     */
    public function getDescription()
    {
        return $this->config->getValue('payment/mispaymethod/description', $this->getScopeInterface());
    }

    /**
     * @return mixed|string
     */
    public function getLogo()
    {
        return $this->config->getValue('payment/mispaymethod/mispay_logo', $this->getScopeInterface());
    }

    /**
     * @return mixed
     */
    public function getWidgetAccessKey()
    {
        return $this->config->getValue('payment/mispaymethod/widget_access_key', $this->getScopeInterface());
    }

    /**
     * @return mixed
     */
    public function getTimeoutLimit()
    {
        return $this->config->getValue('payment/mispaymethod/timeout_limit', $this->getScopeInterface());
    }

    /**
     * @return \Magento\Sales\Model\Config\Source\Order\Status
     */
    public function getOrderStatus()
    {
        return $this->config->getValue('payment/mispaymethod/order_status', $this->getScopeInterface());
    }

    /**
     * @return mixed
     */
    public function getMerchantSecret()
    {
        return $this->config->getValue('payment/mispaymethod/merchant_app_secret', $this->getScopeInterface()) ?? 'NnYLzPw6CTtoNk5K';
    }

    /**
     * @return mixed
     */
    public function getDebugOn()
    {
        return $this->config->getValue('payment/mispaymethod/debug_on', $this->getScopeInterface());
    }

    /**
     * @return mixed
     */
    public function getTestMode()
    {
        return $this->config->getValue('payment/mispaymethod/test_mode', $this->getScopeInterface()) ?? 1;
    }

    /**
     * @return mixed
     */
    public function getRealOrderId()
    {
        return $this->checkoutSession->getLastRealOrder()->getId();
    }

    /**
     * @return mixed
     */
    public function getIncerementOrderId()
    {
        return $this->checkoutSession->getLastRealOrder()->getIncrementId();
    }

    /**
     * @return Order|OrderFactory
     */
    public function getOrder()
    {
        return $this->orderFactory->create()->load($this->getRealOrderId());
    }

    /**
     * @return mixed
     */
    public function getUserIp()
    {
        if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else {
            $ip = $_SERVER["REMOTE_ADDR"];
        }
        return $ip;
    }

    /**
     * @return string
     */
    public function getUserBasket()
    {
        $user_basket = [];
        foreach ($this->checkoutSession->getLastRealOrder()->getAllVisibleItems() as $items) {
            $user_basket[] = [
                $items->getName(),
                number_format($items->getBaseOriginalPrice(), 2, '.', '.'),
                $items->getQtyToShip()
            ];
        }
        return base64_encode(json_encode($user_basket));
    }


    /**
     * @return OrderAddressInterface|null
     */
    public function getBilling()
    {
        return $this->getOrder()->getBillingAddress();
    }

    /**
     * @return string|null
     */
    public function getEmail()
    {
        return $this->getBilling()->getEmail();
    }

    /**
     * @return false|string
     */
    public function getPaymentAmount()
    {
        $only2Decimals = number_format($this->getOrder()->getGrandTotal(), 1, '.', '');
        return $only2Decimals;
    }

    /**
     * @return mixed|string
     */
    public function getCurrency()
    {
        $currency = $this->getOrder()->getOrderCurrency()->getId();
        return $currency === 'SAR' || $currency === 'SR' ? 'SR' : $currency;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->getBilling()->getFirstname() . ' ' . $this->getBilling()->getLastname();
    }

    /**
     * @return string
     */
    public function getUserPhone()
    {
        return $this->getBilling()->getTelephone();
    }

    /**
     * @return string
     */
    public function getUserAddress()
    {
        return $this->getBilling()->getStreet()[0]
            . ' ' . $this->getBilling()->getCity()
            . ' ' . $this->getBilling()->getRegion()
            . ' ' . $this->getBilling()->getRegion()
            . ' ' . $this->getBilling()->getCountryId()
            . ' ' . $this->getBilling()->getPostcode();
    }

    /**
     * @return array
     */
    public function getCategoryIds()
    {
        $ids = [];
        foreach ($this->checkoutSession->getLastRealOrder()->getAllItems() as $items) {
            $ids[] = $items->getProduct()->getCategoryIds();
        }
        return $ids;
    }

    /**
     * @return mixed|string
     */
    public function getLang()
    {
        $objectManager = ObjectManager::getInstance()->get('Magento\Framework\Locale\Resolver')->getLocale();
        return explode('_', $objectManager)[0] ?? 'en';
    }

    /**
     * @return mixed|string
     */
    public function getLocale()
    {
        $objectManager = ObjectManager::getInstance()->get('Magento\Framework\Locale\Resolver')->getLocale();
        return explode('_', $objectManager)[1] ?? 'sa';
    }

    /**
     * @return bool
     */
    public function isMispayOrder(\Magento\Sales\Model\Order $order)
    {
        return $order->getPayment()->getMethod() == "mispay" ||
            $order->getPayment()->getMethod() == "mispaymethod";
    }

    /**
     * @return bool
     */
    public function isAmountValid(\Magento\Sales\Model\Order $order)
    {
        return $order->getGrandTotal() > 200 && $order->getGrandTotal() < 3000;
    }

    /**
     * @return string
     */
    public function getTrackIdFromOrder(\Magento\Sales\Model\Order $order)
    {
        $additionalData = $order->getPayment()->getAdditionalInformation();
        if (empty($additionalData)) {
            return "";
        }
        $parsed = json_decode(json_encode($additionalData));
        if (empty($parsed->mispay_checkout_track_id)) {
            return "";
        }
        return $parsed->mispay_checkout_track_id;
    }
}
