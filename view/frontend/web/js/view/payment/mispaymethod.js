define([
  "uiComponent",
  "Magento_Checkout/js/model/payment/renderer-list",
], function (Component, rendererList) {
  "use strict";
  rendererList.push({
    type: "mispaymethod",
    component:
      "MISPay_MISPayMethod/js/view/payment/method-renderer/mispaymethod-method",
  });
  return Component.extend({});
});
