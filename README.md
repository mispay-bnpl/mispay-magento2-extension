# MIS Pay - Payment Method Extension for Magento2

This PHP module is for extending the payment methods of a Magento application to make it able to receive payment via MIS Pay.

## Installation

```sh
composer require mispay/mispay-magento2-payment-method
bin/magento setup:upgrade
```

to enable it `MISPay_MISPayMethod`;

```sh
bin/magento module:enable MISPay_MISPayMethod
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

That's it! now the customers can use the MISPay option during the checkout process.

---

MISPay ©
All rights reserved ®
https://www.mispay.co
