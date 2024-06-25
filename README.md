# MIS Pay - Payment Method Extension for Magento2 (Dynamic Callback)

This PHP module is for extending the payment methods of a Magento application to make it able to receive payment via MIS Pay.

## Installation

```sh
composer require mispay/mispay-magento2-payment-method-dynamic-callback
bin/magento setup:upgrade
```

to enable it `MISPay_MISPayMethodDynamicCallback`;

```sh
bin/magento module:enable MISPay_MISPayMethodDynamicCallback
```

## Configuration

After the installation process:

- Navigate to **Stores > Configuration** page from the Magento admin panel.
- After making sure that the correct scope has been selected, select **Sales > Payment Methods** from the left panel.
- MISPay method can be enabled from this panel.

### Options

- **Enabled**: it enables the plugin itself. If this is Yes payment method will be visible on the store front side.
- **Payment Area Title**: You can configure the area title via this option.
- **Merchant App ID**: The x-app-id value shared by the MISPay team.
- **Merchant App Secret**: The x-app-secret value shared by the MISPay team.
- **Test Mode**: Indicates whether the integration uses the Sandbox or the Production environment. For testing purposes, the Sandbox environment should be used.
- **Show MISPay Logo**: The MISPay logo will appear if this option is selected.
- **New Order Status**: The status of the new orders.
- **Widget Access Code**: Access code for the widget render. If you leave it empty no widget will be rendered.
- **Callback URL**: Callback URL template for redirecting the ser after payment process.

## Callback URL Template setting

For to be able to use a dynamic callback, `Callback URL` setting should be specified in the admin panel.

> Default value of the the callback URL is: `mispay-dynamic/callback`. This route will be handled by the MISPay plugin automatically. If you're planning to use your own route or modify it by adding some prefix like language code as a folder in the url etc; you can use this field. **If you are leave it empty, default value will be used.**

There are some placeholders you can use for making it dynamic based on some preferences. Here are the placeholders:

- `{LANG}`: 2 letter code of the current language of the user. Ex. `en`, `ar`...
- `{LOCALE}`: 2 letter code of the current country locale setting of the user. `sa`, `us`

Example;

`/{LANG}-{LOCALE}/mispay-dynamic/callback`: this template will be `/ar-sa/mispay-dynamic/callback` if the language is `Arabic` and the locale country setting is `Saudi Arabia`

That's it! now the customers can use the MISPay option during the checkout process.

---

MISPay ©
All rights reserved ®
https://www.mispay.co
