/**
 * Focused tests for dashboard withdrawal policy fail-safe fallback.
 * Mirrors logic in public/assets/js/dashboard.js (policy / reservedFor / applyPayload fallback).
 *
 * Run: node public/assets/js/dashboard-withdrawal-fallback.test.js
 */
"use strict";

var pass = 0;
var fail = 0;

function assert(name, ok, detail) {
  if (ok) {
    pass += 1;
    console.log("PASS  " + name + (detail ? " — " + detail : ""));
  } else {
    fail += 1;
    console.log("FAIL  " + name + (detail ? " — " + detail : ""));
  }
}

var SAFE_FALLBACK = {
  isWithdrawDay: false,
  cashPercent: 100,
  reservePercent: 0,
  today: "",
  day: "Saturday",
  eagleLandsEligible: false,
  eagleLandsSplit: false
};

function resolvePolicy(withdrawalPolicy) {
  return withdrawalPolicy || {
    isWithdrawDay: false,
    cashPercent: 100,
    reservePercent: 0,
    today: "",
    day: "Saturday",
    eagleLandsEligible: false,
    eagleLandsSplit: false
  };
}

function usesEagleSplit(p) {
  return !!(p.eagleLandsSplit || (p.eagleLandsEligible && Number(p.reservePercent) > 0));
}

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
  return Math.round(cashAmount * (reservePercent / cashPercent) * 100) / 100;
}

function applyPayloadPolicy(payloadPolicy) {
  return payloadPolicy || {
    isWithdrawDay: false,
    cashPercent: 100,
    reservePercent: 0,
    today: "",
    day: "Saturday",
    eagleLandsEligible: false,
    eagleLandsSplit: false
  };
}

// Vision–Platinum receive 40/60 from API
["vision", "silver", "gold", "diamond", "platinum"].forEach(function (pkg) {
  var api = {
    cashPercent: 40,
    reservePercent: 60,
    eagleLandsEligible: true,
    eagleLandsSplit: true,
    day: "Saturday",
    isWithdrawDay: true
  };
  var p = resolvePolicy(api);
  assert(
    "api_40_60_" + pkg,
    p.cashPercent === 40 && p.reservePercent === 60 && p.eagleLandsEligible === true && p.eagleLandsSplit === true
  );
  assert("reserved_40_60_" + pkg, reservedFor(40000, api) === 60000, "reserved=" + reservedFor(40000, api));
});

// Bronze, Freedom, Pearl receive 100/0 from API
["bronze", "freedom", "pearl"].forEach(function (pkg) {
  var api = {
    cashPercent: 100,
    reservePercent: 0,
    eagleLandsEligible: false,
    eagleLandsSplit: false,
    day: "Saturday",
    isWithdrawDay: true
  };
  var p = resolvePolicy(api);
  assert(
    "api_100_0_" + pkg,
    p.cashPercent === 100 && p.reservePercent === 0 && p.eagleLandsEligible === false && p.eagleLandsSplit === false
  );
  assert("reserved_100_0_" + pkg, reservedFor(40000, api) === 0, "reserved=" + reservedFor(40000, api));
});

// Missing withdrawal policy → safe 100/0 fallback
var missing = resolvePolicy(null);
assert(
  "missing_policy_safe_fallback",
  missing.cashPercent === 100 &&
    missing.reservePercent === 0 &&
    missing.eagleLandsEligible === false &&
    missing.eagleLandsSplit === false
);
assert("missing_policy_zero_reserve", reservedFor(50000, null) === 0);

var applied = applyPayloadPolicy(undefined);
assert(
  "applyPayload_safe_fallback",
  applied.cashPercent === SAFE_FALLBACK.cashPercent &&
    applied.reservePercent === SAFE_FALLBACK.reservePercent &&
    applied.eagleLandsEligible === SAFE_FALLBACK.eagleLandsEligible &&
    applied.eagleLandsSplit === SAFE_FALLBACK.eagleLandsSplit
);

// Zero reserve percentage produces zero reserve (even if flags were wrongly true)
assert(
  "zero_reserve_percent_zero_amount",
  reservedFor(100000, {
    cashPercent: 40,
    reservePercent: 0,
    eagleLandsEligible: true,
    eagleLandsSplit: true
  }) === 0
);

// Valid API policy keeps existing withdrawal calculation (40 cash → 60 reserve)
assert(
  "valid_api_calculation_unchanged",
  reservedFor(20000, {
    cashPercent: 40,
    reservePercent: 60,
    eagleLandsEligible: true,
    eagleLandsSplit: true
  }) === 30000,
  "20k cash → 30k reserve"
);

assert(
  "valid_api_calculation_10000",
  reservedFor(10000, {
    cashPercent: 40,
    reservePercent: 60,
    eagleLandsEligible: true,
    eagleLandsSplit: true
  }) === 15000
);

// Explicit zero cash percent must not fall back to 40 via ||
assert(
  "zero_cash_percent_no_coerce",
  reservedFor(10000, {
    cashPercent: 0,
    reservePercent: 60,
    eagleLandsEligible: true,
    eagleLandsSplit: true
  }) === 0
);

console.log("\nSummary: " + pass + " passed, " + fail + " failed");
process.exit(fail > 0 ? 1 : 0);
