<?php
require_once __DIR__ . '/api/bootstrap.php';
$pdo = db();
ensure_package_description_column($pdo);
ensure_registration_payments_table($pdo);
$packages = $pdo->query('SELECT code, name, description, price, pv, bottles FROM packages ORDER BY price ASC')->fetchAll();

$paidFlag = isset($_GET['paid']) && (string) $_GET['paid'] === '1';
// When returning from Paystack, `ref` is the payment reference; sponsor is `sponsor`.
$paidRef = $paidFlag ? htmlspecialchars(trim((string) ($_GET['ref'] ?? '')), ENT_QUOTES, 'UTF-8') : '';
$sponsorRaw = $paidFlag
    ? trim((string) ($_GET['sponsor'] ?? ''))
    : trim((string) ($_GET['ref'] ?? $_GET['sponsor'] ?? ''));
$sponsorParam = htmlspecialchars($sponsorRaw, ENT_QUOTES, 'UTF-8');
$sideParam = strtolower(trim((string) ($_GET['pt'] ?? $_GET['side'] ?? 'left')));
if (!in_array($sideParam, ['left', 'right'], true)) {
    $sideParam = 'left';
}
$payFailed = isset($_GET['pay']) && (string) $_GET['pay'] === 'failed';
$payError = htmlspecialchars(trim((string) ($_GET['error'] ?? '')), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Register · Cellvigor Biorevive LTD</title>
  <meta name="description" content="Pay for your package, then create your Cellvigor Biorevive LTD member account.">
  <link rel="icon" href="assets/images/cellvigor-logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,560;0,9..144,640;1,9..144,560&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
  <a class="skip-link" href="#main">Skip to content</a>
  <div class="login-leaves" id="loginLeaves" aria-hidden="true"></div>
  <main id="main" class="login-screen">
    <div class="login-card register-card" style="max-width:640px;">
      <img class="login-mark" src="assets/images/cellvigor-logo.png" alt="Cellvigor Biorevive LTD Logo">
      <h1>Join Cellvigor Biorevive LTD</h1>
      <p>Pay for your package first, then create your account</p>

      <div id="payAlert" class="alert alert-warning small" <?= $payFailed ? '' : 'hidden' ?> role="alert">
        <?= $payError !== '' ? $payError : 'Payment could not be completed.' ?>
      </div>
      <div id="paidAlert" class="alert alert-success small" <?= $paidFlag ? '' : 'hidden' ?> role="alert">
        Payment confirmed. Complete your account details below.
      </div>
      <div id="pendingAlert" class="alert alert-info small" hidden role="alert"></div>

      <section id="paymentStep">
        <h2 class="h6 mb-3">Step 1 · Package payment</h2>
        <form id="paymentStartForm" class="register-grid" novalidate>
          <div class="register-full">
            <label class="form-group-label" for="paySponsor">Sponsor Referral Code / Username</label>
            <input class="form-control" id="paySponsor" name="sponsor" value="<?= $sponsorParam ?>" required>
          </div>
          <div class="register-full">
            <label class="form-group-label">Placement Side</label>
            <div class="side-selector">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="paySide" id="paySideLeft" value="left" <?= $sideParam === 'left' ? 'checked' : '' ?>>
                <label class="form-check-label fw-bold text-dark" for="paySideLeft">Left Team</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="paySide" id="paySideRight" value="right" <?= $sideParam === 'right' ? 'checked' : '' ?>>
                <label class="form-check-label fw-bold text-dark" for="paySideRight">Right Team</label>
              </div>
            </div>
          </div>
          <div class="register-full">
            <label class="form-group-label" for="payPackage">Select Package</label>
            <select class="form-select form-control" id="payPackage" name="package" required>
              <?php foreach ($packages as $pkg): ?>
                <option value="<?= htmlspecialchars($pkg['name']) ?>">
                  <?= htmlspecialchars($pkg['name']) ?> — ₦<?= number_format($pkg['price'], 0) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="register-full">
            <label class="form-group-label" for="payEmail">Email Address</label>
            <input class="form-control" id="payEmail" name="email" type="email" required>
          </div>
          <div class="register-full d-grid gap-2">
            <button class="login-submit" type="submit" id="paystackBtn">Pay with Paystack</button>
            <a class="btn btn-outline-success" href="payment-receipt">I paid the admin · Upload receipt</a>
          </div>
        </form>
        <p class="mt-3 mb-0 small text-muted">Already paid? Enter your payment reference to continue.</p>
        <form id="resumePaymentForm" class="register-grid mt-2" novalidate>
          <div class="register-full">
            <input class="form-control" id="resumeRef" name="reference" placeholder="Payment reference (ZGV-REG-… or ZGV-TRF-…)" value="<?= $paidFlag ? $paidRef : '' ?>">
          </div>
          <div class="register-full">
            <button class="btn btn-soft w-100" type="submit">Check payment status</button>
          </div>
        </form>
      </section>

      <form class="register-grid mt-4" id="publicRegisterForm" novalidate <?= $paidFlag ? '' : 'hidden' ?>>
        <h2 class="h6 register-full mb-0">Step 2 · Create account</h2>
        <input type="hidden" id="regPaymentReference" name="paymentReference" value="<?= $paidFlag ? $paidRef : '' ?>">
        <div class="register-full">
          <label class="form-group-label" for="regSponsor">Sponsor</label>
          <input class="form-control" id="regSponsor" name="sponsor" value="<?= $sponsorParam ?>" required>
        </div>
        <div class="register-full">
          <label class="form-group-label">Placement Side</label>
          <div class="side-selector">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="side" id="sideLeft" value="left" <?= $sideParam === 'left' ? 'checked' : '' ?>>
              <label class="form-check-label fw-bold text-dark" for="sideLeft">Left Team</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="side" id="sideRight" value="right" <?= $sideParam === 'right' ? 'checked' : '' ?>>
              <label class="form-check-label fw-bold text-dark" for="sideRight">Right Team</label>
            </div>
          </div>
        </div>
        <div class="register-full">
          <label class="form-group-label" for="regPackage">Package</label>
          <select class="form-select form-control" id="regPackage" name="package" required>
            <?php foreach ($packages as $pkg): ?>
              <option value="<?= htmlspecialchars($pkg['name']) ?>">
                <?= htmlspecialchars($pkg['name']) ?> — ₦<?= number_format($pkg['price'], 0) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-group-label" for="regFirstName">First Name</label>
          <input class="form-control" id="regFirstName" name="firstName" required>
        </div>
        <div>
          <label class="form-group-label" for="regLastName">Last Name</label>
          <input class="form-control" id="regLastName" name="lastName">
        </div>
        <div>
          <label class="form-group-label" for="regEmail">Email Address</label>
          <input class="form-control" id="regEmail" name="email" type="email" required>
        </div>
        <div>
          <label class="form-group-label" for="regPhone">Phone Number</label>
          <input class="form-control" id="regPhone" name="phone" type="tel" required>
        </div>
        <div class="register-full">
          <label class="form-group-label" for="regUsername">Username</label>
          <input class="form-control" id="regUsername" name="username" autocomplete="username" required>
        </div>
        <div>
          <label class="form-group-label" for="regPassword">Password</label>
          <div class="password-field">
            <input class="form-control" id="regPassword" name="password" type="password" autocomplete="new-password" required>
            <button class="password-toggle" type="button" data-toggle-password="regPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <div>
          <label class="form-group-label" for="regConfirmPassword">Confirm Password</label>
          <div class="password-field">
            <input class="form-control" id="regConfirmPassword" name="confirmPassword" type="password" autocomplete="new-password" required>
            <button class="password-toggle" type="button" data-toggle-password="regConfirmPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <div class="register-full mt-2">
          <div class="form-check text-start">
            <input class="form-check-input" type="checkbox" id="regTerms" name="terms" required checked>
            <label class="form-check-label" for="regTerms">I agree to the Cellvigor Biorevive LTD terms &amp; conditions</label>
          </div>
        </div>
        <div class="register-full">
          <button class="login-submit mt-2" type="submit" id="regSubmitBtn">CREATE ACCOUNT</button>
        </div>
      </form>

      <p class="mt-3 mb-0" style="font-size:0.92rem;color:#314033;">
        Already have an account? <a href="login" style="color:#006d03;font-weight:700;text-decoration:none;">Sign in</a>
      </p>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    (function () {
      var box = document.getElementById("loginLeaves");
      if (box && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        for (var i = 0; i < 8; i += 1) {
          var leaf = document.createElement("img");
          leaf.src = "assets/images/leaf.png";
          leaf.alt = "";
          leaf.className = "login-leaf";
          leaf.style.left = Math.random() * 100 + "vw";
          leaf.style.animationDuration = (7 + Math.random() * 7) + "s";
          leaf.style.animationDelay = (-Math.random() * 8) + "s";
          leaf.style.width = (22 + Math.random() * 26) + "px";
          box.appendChild(leaf);
        }
      }

      document.addEventListener("DOMContentLoaded", function () {
        var Z = window.Zenvita;
        var paymentStep = document.getElementById("paymentStep");
        var regForm = document.getElementById("publicRegisterForm");
        var paidAlert = document.getElementById("paidAlert");
        var pendingAlert = document.getElementById("pendingAlert");
        var payAlert = document.getElementById("payAlert");

        function unlockRegistration(data) {
          if (paymentStep) paymentStep.hidden = true;
          if (regForm) regForm.hidden = false;
          if (paidAlert) paidAlert.hidden = false;
          if (pendingAlert) pendingAlert.hidden = true;
          document.getElementById("regPaymentReference").value = data.reference || "";
          if (data.email) {
            document.getElementById("regEmail").value = data.email;
            document.getElementById("regEmail").readOnly = true;
          }
          if (data.package) {
            document.getElementById("regPackage").value = data.package;
            document.getElementById("regPackage").disabled = true;
          }
          var params = new URLSearchParams(window.location.search);
          var sponsor = params.get("sponsor") || document.getElementById("paySponsor").value;
          var side = params.get("side") || (document.querySelector("input[name='paySide']:checked") || {}).value || "left";
          if (sponsor) document.getElementById("regSponsor").value = sponsor;
          var sideInput = document.querySelector("input[name='side'][value='" + side + "']");
          if (sideInput) sideInput.checked = true;
        }

        function showPending(message, reference) {
          if (pendingAlert) {
            pendingAlert.hidden = false;
            pendingAlert.textContent = message + (reference ? " Reference: " + reference : "");
          }
          if (paidAlert) paidAlert.hidden = true;
        }

        function checkStatus(reference) {
          return fetch("api/paystack-status.php?reference=" + encodeURIComponent(reference), { credentials: "same-origin" })
            .then(function (res) { return res.json(); })
            .then(function (data) {
              if (data && data.ok && data.paid) {
                unlockRegistration(data);
                return true;
              }
              if (data && data.error && /already used/i.test(data.error)) {
                if (Z) Z.showToast(data.error, "error");
                return false;
              }
              showPending((data && data.error) || "Payment is not approved yet. If you uploaded a receipt, wait for admin approval.", reference);
              return false;
            });
        }

        var params = new URLSearchParams(window.location.search);
        if (params.get("paid") === "1" && params.get("ref")) {
          checkStatus(params.get("ref"));
        }

        document.getElementById("paymentStartForm").addEventListener("submit", function (e) {
          e.preventDefault();
          var sponsor = document.getElementById("paySponsor").value.trim();
          var sideEl = document.querySelector("input[name='paySide']:checked");
          var side = sideEl ? sideEl.value : "left";
          var pkg = document.getElementById("payPackage").value;
          var email = document.getElementById("payEmail").value.trim();
          if (!sponsor || !email || !pkg) {
            if (Z) Z.showToast("Enter sponsor, email, and package before paying.", "error");
            return;
          }
          var btn = document.getElementById("paystackBtn");
          if (btn) btn.disabled = true;
          fetch("api/paystack-init.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ email: email, package: pkg, sponsor: sponsor, side: side })
          })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (btn) btn.disabled = false;
            if (!data || !data.ok || !data.authorizationUrl) {
              if (Z) Z.showToast((data && data.error) || "Could not start Paystack payment.", "error");
              return;
            }
            window.location.href = data.authorizationUrl;
          })
          .catch(function () {
            if (btn) btn.disabled = false;
            if (Z) Z.showToast("Could not reach Paystack.", "error");
          });
        });

        document.getElementById("resumePaymentForm").addEventListener("submit", function (e) {
          e.preventDefault();
          var reference = document.getElementById("resumeRef").value.trim();
          if (!reference) {
            if (Z) Z.showToast("Enter your payment reference.", "error");
            return;
          }
          checkStatus(reference).then(function (ok) {
            if (!ok && Z) Z.showToast("Payment not ready for registration yet.", "error");
          });
        });

        regForm.addEventListener("submit", function (e) {
          e.preventDefault();
          var paymentReference = document.getElementById("regPaymentReference").value.trim();
          var sponsor = regForm.sponsor.value.trim();
          var side = (regForm.querySelector("input[name='side']:checked") || {}).value || "left";
          var pkg = document.getElementById("regPackage").disabled
            ? document.getElementById("regPackage").value
            : regForm.package.value;
          var first = regForm.firstName.value.trim();
          var last = regForm.lastName.value.trim();
          var email = regForm.email.value.trim();
          var phone = regForm.phone.value.trim();
          var username = regForm.username.value.trim();
          var password = regForm.password.value;
          var confirm = regForm.confirmPassword.value;
          if (!paymentReference) {
            if (Z) Z.showToast("Complete payment before creating an account.", "error");
            return;
          }
          if (!sponsor || !first || !email || username.length < 3 || password.length < 8 || password !== confirm) {
            if (Z) Z.showToast("Please complete all required account fields.", "error");
            return;
          }
          var btn = document.getElementById("regSubmitBtn");
          if (btn) btn.disabled = true;
          fetch("api/add-user.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({
              paymentReference: paymentReference,
              sponsor: sponsor,
              side: side,
              package: pkg,
              firstName: first,
              lastName: last,
              email: email,
              phone: phone,
              username: username,
              password: password
            })
          })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (btn) btn.disabled = false;
            if (!data || !data.ok) {
              if (Z) Z.showToast((data && data.error) || "Registration could not be completed.", "error");
              return;
            }
            if (Z) Z.showToast("Account created successfully! Welcome to Cellvigor Biorevive LTD.");
            window.setTimeout(function () { window.location.href = "dashboard/index"; }, 800);
          })
          .catch(function () {
            if (btn) btn.disabled = false;
            if (Z) Z.showToast("Server communication error. Please try again.", "error");
          });
        });
      });
    })();
  </script>
</body>
</html>
