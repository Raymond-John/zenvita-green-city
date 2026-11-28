/**
 * Exercises the production withdrawal-policy module (no duplicated implementation).
 *
 * Run: node public/assets/js/dashboard-withdrawal-fallback.test.js
 */
"use strict";

var path = require("path");
var WP = require(path.join(__dirname, "withdrawal-policy.js"));

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

assert("module_exports_resolvePolicy", typeof WP.resolvePolicy === "function");
assert("module_exports_reservedFor", typeof WP.reservedFor === "function");
assert("module_safe_fallback_100_0", WP.SAFE_FALLBACK.cashPercent === 100 && WP.SAFE_FALLBACK.reservePercent === 0);

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
  var p = WP.resolvePolicy(api);
  assert(
    "api_40_60_" + pkg,
    p.cashPercent === 40 && p.reservePercent === 60 && p.eagleLandsEligible === true && p.eagleLandsSplit === true
  );
  assert("reserved_40_60_" + pkg, WP.reservedFor(40000, api) === 60000, "reserved=" + WP.reservedFor(40000, api));
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
  var p = WP.resolvePolicy(api);
  assert(
    "api_100_0_" + pkg,
    p.cashPercent === 100 && p.reservePercent === 0 && p.eagleLandsEligible === false && p.eagleLandsSplit === false
  );
  assert("reserved_100_0_" + pkg, WP.reservedFor(40000, api) === 0, "reserved=" + WP.reservedFor(40000, api));
});

// Missing withdrawal policy → safe 100/0 fallback
var missing = WP.resolvePolicy(null);
assert(
  "missing_policy_safe_fallback",
  missing.cashPercent === 100 &&
    missing.reservePercent === 0 &&
    missing.eagleLandsEligible === false &&
    missing.eagleLandsSplit === false &&
    missing.isWithdrawDay === false
);
assert("missing_policy_zero_reserve", WP.reservedFor(50000, null) === 0);
assert("missing_policy_no_eagle_split", WP.usesEagleSplit(null) === false);

var malformed = WP.resolvePolicy("not-an-object");
assert(
  "malformed_policy_safe_fallback",
  malformed.cashPercent === 100 && malformed.reservePercent === 0 && malformed.isWithdrawDay === false
);
assert("malformed_policy_zero_reserve", WP.reservedFor(25000, "bad") === 0);

var applied = WP.resolvePolicy(undefined);
assert(
  "applyPayload_safe_fallback",
  applied.cashPercent === WP.SAFE_FALLBACK.cashPercent &&
    applied.reservePercent === WP.SAFE_FALLBACK.reservePercent &&
    applied.eagleLandsEligible === WP.SAFE_FALLBACK.eagleLandsEligible &&
    applied.eagleLandsSplit === WP.SAFE_FALLBACK.eagleLandsSplit
);

// Fallback must not invent a withdraw day or Eagle split that would authorize a prohibited withdrawal UX
assert("fallback_not_withdraw_day", WP.resolvePolicy(null).isWithdrawDay === false);
assert("fallback_not_eagle_eligible", WP.resolvePolicy(null).eagleLandsEligible === false);

assert(
  "zero_reserve_percent_zero_amount",
  WP.reservedFor(100000, {
    cashPercent: 40,
    reservePercent: 0,
    eagleLandsEligible: true,
    eagleLandsSplit: true
  }) === 0
);

assert(
  "valid_api_calculation_unchanged",
  WP.reservedFor(20000, {
    cashPercent: 40,
    reservePercent: 60,
    eagleLandsEligible: true,
    eagleLandsSplit: true
  }) === 30000,
  "20k cash → 30k reserve"
);

assert(
  "valid_api_calculation_10000",
  WP.reservedFor(10000, {
    cashPercent: 40,
    reservePercent: 60,
    eagleLandsEligible: true,
    eagleLandsSplit: true
  }) === 15000
);

assert(
  "zero_cash_percent_no_coerce",
  WP.reservedFor(10000, {
    cashPercent: 0,
    reservePercent: 60,
    eagleLandsEligible: true,
    eagleLandsSplit: true
  }) === 0
);

console.log("\nSummary: " + pass + " passed, " + fail + " failed");
process.exit(fail > 0 ? 1 : 0);
