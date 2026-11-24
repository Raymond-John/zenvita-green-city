<?php
require_once __DIR__ . '/api/bootstrap.php';
$pdo = db();
ensure_package_description_column($pdo);
$packages = $pdo->query('SELECT code, name, description, price, pv, bottles FROM packages ORDER BY price ASC')->fetchAll();

$sponsorParam = htmlspecialchars(trim((string) ($_GET['ref'] ?? $_GET['sponsor'] ?? '')));
$sideParam = strtolower(trim((string) ($_GET['pt'] ?? $_GET['side'] ?? 'left')));
if (!in_array($sideParam, ['left', 'right'], true)) {
    $sideParam = 'left';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Register · Cellvigor Biorevive LTD</title>
  <meta name="description" content="Create your Cellvigor Biorevive LTD member account.">
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
    <form class="login-card register-card" id="publicRegisterForm" novalidate>
      <img class="login-mark" src="assets/images/cellvigor-logo.png" alt="Cellvigor Biorevive LTD Logo">
      <h1>Join Cellvigor Biorevive LTD</h1>
      <p>Create your new member account</p>

      <div class="register-grid">
        <div class="register-full">
          <label class="form-group-label" for="regSponsor">Sponsor Referral Code / Username</label>
          <input class="form-control" id="regSponsor" name="sponsor" value="<?= $sponsorParam ?>" placeholder="Enter sponsor username or ID (e.g. ZGV00002)" required>
          <p class="field-error" id="regSponsor-error"></p>
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
          <p class="field-error" id="regSide-error"></p>
        </div>

        <div class="register-full">
          <label class="form-group-label" for="regPackage">Select Package</label>
          <select class="form-select form-control" id="regPackage" name="package" required>
            <?php foreach ($packages as $pkg): ?>
              <?php $desc = trim((string) ($pkg['description'] ?? '')); ?>
              <option value="<?= htmlspecialchars($pkg['name']) ?>"<?= $desc !== '' ? ' title="' . htmlspecialchars($desc) . '"' : '' ?>>
                <?= htmlspecialchars($pkg['name']) ?> — ₦<?= number_format($pkg['price'], 0) ?> (<?= (int)$pkg['pv'] ?> PV, <?= (int)$pkg['bottles'] ?> Bottles)
              </option>
            <?php endforeach; ?>
          </select>
          <p class="field-error" id="regPackage-error"></p>
        </div>

        <div>
          <label class="form-group-label" for="regFirstName">First Name</label>
          <input class="form-control" id="regFirstName" name="firstName" placeholder="First Name" required>
          <p class="field-error" id="regFirstName-error"></p>
        </div>
        <div>
          <label class="form-group-label" for="regLastName">Last Name</label>
          <input class="form-control" id="regLastName" name="lastName" placeholder="Last Name">
          <p class="field-error" id="regLastName-error"></p>
        </div>

        <div>
          <label class="form-group-label" for="regEmail">Email Address</label>
          <input class="form-control" id="regEmail" name="email" type="email" placeholder="email@example.com" required>
          <p class="field-error" id="regEmail-error"></p>
        </div>
        <div>
          <label class="form-group-label" for="regPhone">Phone Number</label>
          <input class="form-control" id="regPhone" name="phone" type="tel" placeholder="08012345678" required>
          <p class="field-error" id="regPhone-error"></p>
        </div>

        <div class="register-full">
          <label class="form-group-label" for="regUsername">Username</label>
          <input class="form-control" id="regUsername" name="username" placeholder="Choose a username (min 3 chars)" autocomplete="username" required>
          <p class="field-error" id="regUsername-error"></p>
        </div>

        <div>
          <label class="form-group-label" for="regPassword">Password</label>
          <div class="password-field">
            <input class="form-control" id="regPassword" name="password" type="password" placeholder="Min 8 characters" autocomplete="new-password" required>
            <button class="password-toggle" type="button" data-toggle-password="regPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
          </div>
          <p class="field-error" id="regPassword-error"></p>
        </div>
        <div>
          <label class="form-group-label" for="regConfirmPassword">Confirm Password</label>
          <div class="password-field">
            <input class="form-control" id="regConfirmPassword" name="confirmPassword" type="password" placeholder="Confirm password" autocomplete="new-password" required>
            <button class="password-toggle" type="button" data-toggle-password="regConfirmPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
          </div>
          <p class="field-error" id="regConfirmPassword-error"></p>
        </div>

        <div class="register-full mt-2">
          <div class="form-check text-start">
            <input class="form-check-input" type="checkbox" id="regTerms" name="terms" required checked>
            <label class="form-check-label" for="regTerms">I agree to the Cellvigor Biorevive LTD terms &amp; conditions</label>
          </div>
          <p class="field-error" id="regTerms-error"></p>
        </div>
      </div>

      <button class="login-submit mt-3" type="submit" id="regSubmitBtn">CREATE ACCOUNT</button>

      <p class="mt-3 mb-0" style="font-size:0.92rem;color:#314033;">
        Already have an account? <a href="login" style="color:#006d03;font-weight:700;text-decoration:none;">Sign in</a>
      </p>
    </form>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    (function () {
      var box = document.getElementById("loginLeaves");
      if (!box || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
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
    })();

    document.addEventListener("DOMContentLoaded", function () {
      var form = document.getElementById("publicRegisterForm");
      if (!form) return;
      var Z = window.Zenvita;

      form.addEventListener("submit", function (e) {
        e.preventDefault();
        if (Z && Z.clearFormErrors) Z.clearFormErrors(form);

        var sponsor = form.sponsor.value.trim();
        var side = form.side ? form.querySelector("input[name='side']:checked")?.value : "left";
        var pkg = form.package.value;
        var first = form.firstName.value.trim();
        var last = form.lastName.value.trim();
        var email = form.email.value.trim();
        var phone = form.phone.value.trim();
        var username = form.username.value.trim();
        var password = form.password.value;
        var confirm = form.confirmPassword.value;
        var terms = form.terms.checked;
        var valid = true;

        if (!sponsor) {
          if (Z) Z.setFieldError(form.sponsor, "Enter a sponsor referral code or username.");
          valid = false;
        }
        if (!first) {
          if (Z) Z.setFieldError(form.firstName, "Enter your first name.");
          valid = false;
        }
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          if (Z) Z.setFieldError(form.email, "Enter a valid email address.");
          valid = false;
        }
        if (phone.replace(/\D/g, "").length < 10) {
          if (Z) Z.setFieldError(form.phone, "Enter a phone number with at least 10 digits.");
          valid = false;
        }
        if (username.length < 3) {
          if (Z) Z.setFieldError(form.username, "Username must be at least 3 characters.");
          valid = false;
        }
        if (password.length < 8) {
          if (Z) Z.setFieldError(form.password, "Password must be at least 8 characters.");
          valid = false;
        }
        if (password !== confirm) {
          if (Z) Z.setFieldError(form.confirmPassword, "Passwords do not match.");
          valid = false;
        }
        if (!terms) {
          if (Z) Z.setFieldError(form.terms, "Please accept the terms to continue.");
          valid = false;
        }
        if (!valid) {
          if (Z) Z.showToast("Please correct the highlighted fields.", "error");
          return;
        }

        var btn = document.getElementById("regSubmitBtn");
        if (btn) btn.disabled = true;

        fetch("api/add-user.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
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
            if (Z) Z.showToast(data.error || "Registration could not be completed.", "error");
            return;
          }
          if (Z) Z.showToast("Account created successfully! Welcome to Cellvigor Biorevive LTD.");
          window.setTimeout(function () {
            window.location.href = "dashboard/index";
          }, 800);
        })
        .catch(function () {
          if (btn) btn.disabled = false;
          if (Z) Z.showToast("Server communication error. Please try again.", "error");
        });
      });
    });
  </script>
</body>
</html>
