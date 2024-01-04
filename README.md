# MIS Pay - Payment Method Extension for Magento2

This PHP module is for extending the payment methods of a Magento application to make it able to receive payment via MIS Pay.

## Installation

```sh
composer require mispay/mispay-magento2-payment
bin/magento setup:upgrade
```

## Development Environment

[Docker Magento](https://) can be used for local development environment.

**Docker Magento installation**

```sh
# Create your project directory then go into it:
mkdir -p ~/Sites/magento
cd $_

# the domain for local dev: mispay.magento.dev
# Run this automated one-liner from the directory you want to install your project.
curl -s https://raw.githubusercontent.com/markshust/docker-magento/master/lib/onelinesetup | bash -s -- mispay.magento.dev 2.4.6-p3 community
```

Edit `src/env/magento.env` file for desired credentials, then run;

```sh
# the domain for local dev: mispay.magento.dev
bin/setup-install mispay.magento.dev
```

import sample data;

```sh
bin/magento sampledata:deploy
bin/magento setup:upgrade
```

disabling 2FA login

```sh
bin/magento module:disable Magento_AdminAdobeImsTwoFactorAuth
bin/magento module:disable Magento_TwoFactorAuth
```

enable `developer` mode

```sh
rm -rf src/generated/metadata/* src/generated/code/*
bin/magento deploy:mode:set developer
```

Finally clone this repo to `src/app/code/Finbyte/MISPayMethod` folder.

```sh
git clone git@gitlab.core.finbyte.cloud:jouri/sdks/magento-extension.git ./src/app/code/MISPay/MISPayMagento2Payment
```

And upgrade magento setup.

```sh
bin/magento setup:upgrade
```

to enable it `Finbyte_MISPayMethod`;

```sh
bin/magento module:enable Finbyte_MISPayMethod
```

### Docker Magento File system

Some folders are not bi-directionally synced with the containers. If you want to copy files between host and container these commands can be used.

```sh
bin/copyfromcontainer vendor

# or

bin/copytocontainer pub
```

## Test Merchant Info

### DEV env

```js
{
  "id": "65802e409f0cae432ee197b1",
  "link": "https://mispay.magento.dev",
  "logo": null,
  "merchantId": "LRZ97-5YST-ATOE",
  "merchantName": "Magento Orhan Test",
  "status": "active",
  "email": "orhan.firik@finbyte.com",
  "companyName": "Fibyte MISPay Magento",
  "createdAt": "2023-12-18T11:34:24.201Z"
}

password: `hiejisDP05!&`
application Mongo Id: `658046769f0cae432ee198fe`
appId: `695f57087c1710a6bdc419fd963bcdb5307bab94`
appSecret: `7047ec6fa233ee52fd5fe278610180ca666e737dc04ce07d06d5eaf794674ccb`
```

### TEST env

```js
appId: 'a39b5d75577d8837543b20a95c3a2c8ced0bcb61',
appSecret: '92c01f1e9fd15dcde69eeebfeb3a8ff08b1697c516b74ca56ebfc27fb52e4430',
```

## Example Callback URL with payload

```
# SUCCESS
https://mispay.magento.dev/mispay/callback?_=QjlRLzVXdXRPb1BneFdrakhqNTViWlpmcndaT3JGeS94OXRTUWo2S051NDJxeTNscjBwMzJJMnJnODdMbE9VWDFidCt3aDcxcHJjUS8yVUZzOGR0VmRTQzVlaXhUNW9zeVRxYjZMSDFBYnFzTkdqSlljaXBhNUp6elZTNlBnbnZhSmhaV1NzVVRVVnkvUG15TkZpMDNqV1c=&appId=a39b5d75577d8837543b20a95c3a2c8ced0bcb61

# TIMEOUT


```
