<?php

namespace MISPay\MISPayMethodDynamicCallback\Helper;

use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Class MISPayRequestHelper
 *
 * @package MISPay\MISPayMethodDynamicCallback\Helper
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
     * @var null|string
     */
    private $accessToken = null;

    /**
     * MISPayRequestHelper constructor.
     *
     * @param MISPayHelper $mispayHelper
     */
    public function __construct(MISPayHelper $mispayHelper, LoggerInterface $logger)
    {
        $this->mispayHelper = $mispayHelper;
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

    public function trackCheckout($checkoutId)
    {
        $accessToken = $this->getAccessToken();
        $url = $this->mispayHelper->getBaseUrl() . $this->mispayHelper->getEndpoints()['trackCheckout'];
        $url = str_replace(':id', $checkoutId, $url);
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array(
                'x-app-secret: ' . $this->mispayHelper->getCredentials()->appSecret,
                "x-app-id: " . $this->mispayHelper->getCredentials()->appId,
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ),
            CURLOPT_CUSTOMREQUEST => 'GET',
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

    /**
     * @return mixed
     */
    public function startCheckoutSession($isDynamic = false)
    {
        $accessToken = $this->getAccessToken();
        $url = $this->mispayHelper->getBaseUrl() . $this->mispayHelper->getEndpoints()['startCheckoutSession'];
        $lang = $this->mispayHelper->getLang() == 'en' ? 'en' : 'ar';
        $callbackUri = $this->mispayHelper->getCallbackUri();

        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array(
                'x-app-secret: ' . $this->mispayHelper->getCredentials()->appSecret,
                "x-app-id: " . $this->mispayHelper->getCredentials()->appId,
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => '{
                "version": "v1.1",
                "orderId": "' . $this->mispayHelper->getIncerementOrderId() . '",
                "purchaseAmount": ' . $this->mispayHelper->getPaymentAmount() . ',
                ' . ($isDynamic ? '"callbackUri": "' . $callbackUri . '",' : '') . '
                "purchaseCurrency": "SAR",
                "lang": "' . $lang . '"
            }',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        );

        // die(json_encode($options, JSON_PRETTY_PRINT));

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $responseBody = json_decode($response);
        return $responseBody->result;
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
