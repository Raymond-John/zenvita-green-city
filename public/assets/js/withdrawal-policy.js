/**
 * Production withdrawal-policy helpers (shared by dashboard.js and Node tests).
 * Safe fallback when policy is missing/malformed: 100% cash, 0% Eagle reserve.
 */
(function (root, factory) {
  if (typeof module === "object" && module.exports) {
    module.exports = factory();
  } else {
    root.ZenvitaWithdrawalPolicy = factory();
  }
})(typeof self !== "undefined" ? self : this, function () {
  "use strict";

  var SAFE_FALLBACK = {
    isWithdrawDay: false,
    cashPercent: 100,
    reservePercent: 0,
    today: "",
    day: "Saturday",
    eagleLandsEligible: false,
    eagleLandsSplit: false
  };

  function cloneSafeFallback() {
    return {
      isWithdrawDay: SAFE_FALLBACK.isWithdrawDay,
      cashPercent: SAFE_FALLBACK.cashPercent,
      reservePercent: SAFE_FALLBACK.reservePercent,
      today: SAFE_FALLBACK.today,
      day: SAFE_FALLBACK.day,
      eagleLandsEligible: SAFE_FALLBACK.eagleLandsEligible,
      eagleLandsSplit: SAFE_FALLBACK.eagleLandsSplit
    };
  }

  /**
   * Normalize API / payload withdrawal policy.
   * Missing or non-object input → safe 100/0 fallback (does not invent 40/60).
   */
  function resolvePolicy(withdrawalPolicy) {
    if (!withdrawalPolicy || typeof withdrawalPolicy !== "object") {
      return cloneSafeFallback();
    }
    return withdrawalPolicy;
  }

  function usesEagleSplit(policy) {
    var p = resolvePolicy(policy);
    return !!(p.eagleLandsSplit || (p.eagleLandsEligible && Number(p.reservePercent) > 0));
  }

  /**
   * Matching Eagle reserve for a cash withdrawal amount.
   * Never coerces 0 → 40/60 via || defaults.
   */
  function reservedFor(cashAmount, withdrawalPolicy) {
    var p = resolvePolicy(withdrawalPolicy);
    if (!usesEagleSplit(p)) {
      return 0;
    }
    var cashPercent = Number(p.cashPercent);
    var reservePercent = Number(p.reservePercent);
    if (!isFinite(cashPercent) || !isFinite(reservePercent) || cashPercent <= 0 || reservePercent <= 0) {
      return 0;
    }
    return Math.round(Number(cashAmount) * (reservePercent / cashPercent) * 100) / 100;
  }

  return {
    SAFE_FALLBACK: SAFE_FALLBACK,
    resolvePolicy: resolvePolicy,
    usesEagleSplit: usesEagleSplit,
    reservedFor: reservedFor
  };
});
