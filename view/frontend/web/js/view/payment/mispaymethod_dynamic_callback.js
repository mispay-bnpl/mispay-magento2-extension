define([
  "uiComponent",
  "Magento_Checkout/js/model/payment/renderer-list",
], function (Component, rendererList) {
  "use strict";
  rendererList.push({
    type: "mispaymethod_dynamic_callback",
    component:
      "MISPay_MISPayMethodDynamicCallback/js/view/payment/method-renderer/mispaymethod_dynamic_callback-method",
  });
  return Component.extend({});
});
