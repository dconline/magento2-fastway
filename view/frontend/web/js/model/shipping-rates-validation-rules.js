/* global define */
define([], function () {
  'use strict'

  return {
    // 需要的地址参数
    getRules: function () {
      return {
        'postcode': {
          'required': true
        },
        'city': {
          'required': true
        }
      }
    }
  }
})
