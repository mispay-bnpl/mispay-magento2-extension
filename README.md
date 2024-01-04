# MIS Pay - Payment Method Extension for Magento2

This PHP module is for extending the payment methods of a Magento application to make it able to receive payment via MIS Pay.

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

Finally clone this repo to `src/app/code/MISPay/MISPayMethod` folder.

```sh
git clone https://github.com/mispay-bnpl/mispay-magento2-extension ./src/app/code/MISPay/MISPayMagento2Payment
```

And upgrade magento setup.

```sh
bin/magento setup:upgrade
```

to enable it `MISPay_MISPayMethod`;

```sh
bin/magento module:enable MISPay_MISPayMethod
```

### Docker Magento File system

Some folders are not bi-directionally synced with the containers. If you want to copy files between host and container these commands can be used.

```sh
bin/copyfromcontainer vendor

# or

bin/copytocontainer pub
```

## Example Callback URL with payload

```
# SUCCESS
https://mispay.magento.dev/mispay/callback?_=QjlRLzVXdXRPb1BneFdrakhqNTViWlpmcndaT3JGeS94OXRTUWo2S051NDJxeTNscjBwMzJJMnJnODdMbE9VWDFidCt3aDcxcHJjUS8yVUZzOGR0VmRTQzVlaXhUNW9zeVRxYjZMSDFBYnFzTkdqSlljaXBhNUp6elZTNlBnbnZhSmhaV1NzVVRVVnkvUG15TkZpMDNqV1c=&appId=a39b5d75577d8837543b20a95c3a2c8ced0bcb61

```
