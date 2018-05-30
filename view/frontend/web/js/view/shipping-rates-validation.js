/* browser:true */
/* global define */
define(
  [
    'uiComponent',
    'Magento_Checkout/js/model/shipping-rates-validator',
    'Magento_Checkout/js/model/shipping-rates-validation-rules',
    'Dc_Fastway/js/model/shipping-rates-validator',
    'Dc_Fastway/js/model/shipping-rates-validation-rules',
    'rjsResolver'
  ],
  function (
    Component,
    defaultShippingRatesValidator,
    defaultShippingRatesValidationRules,
    shippingRatesValidator,
    shippingRatesValidationRules,
    resolver
  ) {
    'use strict'
    defaultShippingRatesValidator.registerValidator('fastway', shippingRatesValidator)
    defaultShippingRatesValidationRules.registerRules('fastway', shippingRatesValidationRules)
    return Component.extend({})
  }
)
