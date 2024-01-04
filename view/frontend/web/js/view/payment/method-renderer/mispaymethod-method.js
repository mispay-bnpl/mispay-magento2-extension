define(["Magento_Checkout/js/view/payment/default", "mage/url"], function (
  Component,
  url
) {
  "use strict";
  return Component.extend({
    defaults: {
      template: "MISPay_MISPayMethod/payment/mispaymethod",
    },
    getPaymentAcceptanceMarkSrc: function () {
      return window.checkoutConfig.payment.mispaymethod.logo_url;
    },
    isLogoVisible: function () {
      return window.checkoutConfig.payment.mispaymethod.logo_visible;
    },
    afterPlaceOrder: function () {
      window.location.replace(url.build("mispay/redirect/"));
    },
    getMailingAddress: function () {
      return window.checkoutConfig.payment.checkmo.mailingAddress;
    },
  });
});
