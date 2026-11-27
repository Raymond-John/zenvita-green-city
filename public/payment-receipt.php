<?php
require_once __DIR__ . '/api/bootstrap.php';
$pdo = db();
ensure_package_description_column($pdo);
ensure_registration_payments_table($pdo);
$packages = $pdo->query('SELECT code, name, price FROM packages ORDER BY price ASC')->fetchAll();
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
  <title>Upload payment receipt · Cellvigor Biorevive LTD</title>
  <link rel="icon" href="assets/images/cellvigor-logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,560;1,9..144,560&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
  <main id="main" class="login-screen">
    <form class="login-card register-card" id="receiptForm" enctype="multipart/form-data" novalidate style="max-width:640px;">
      <img class="login-mark" src="assets/images/cellvigor-logo.png" alt="Cellvigor Biorevive LTD Logo">
      <h1>Upload payment receipt</h1>
      <p>If you paid the admin by bank transfer or cash, upload your receipt for approval.</p>

      <div class="register-grid">
        <div class="register-full">
          <label class="form-group-label" for="sponsor">Sponsor Referral Code / Username</label>
          <input class="form-control" id="sponsor" name="sponsor" value="<?= $sponsorParam ?>" required>
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
          <label class="form-group-label" for="package">Package paid for</label>
          <select class="form-select form-control" id="package" name="package" required>
            <?php foreach ($packages as $pkg): ?>
              <option value="<?= htmlspecialchars($pkg['name']) ?>">
                <?= htmlspecialchars($pkg['name']) ?> — ₦<?= number_format($pkg['price'], 0) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="register-full">
          <label class="form-group-label" for="email">Email Address</label>
          <input class="form-control" id="email" name="email" type="email" required>
        </div>
        <div class="register-full">
          <label class="form-group-label" for="notes">Payment note (optional)</label>
          <input class="form-control" id="notes" name="notes" maxlength="255" placeholder="Bank name, teller number, or date paid">
        </div>
        <div class="register-full">
          <label class="form-group-label" for="receipt">Receipt file</label>
          <input class="form-control" id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
          <p class="text-muted small mb-0 mt-1">JPG, PNG, WEBP, or PDF · max 5 MB</p>
        </div>
        <div class="register-full d-grid gap-2">
          <button class="login-submit" type="submit" id="receiptSubmitBtn">Submit for admin approval</button>
          <a class="btn btn-outline-success" href="register">Back to registration / Paystack</a>
        </div>
      </div>
      <div id="receiptResult" class="alert alert-info small mt-3" hidden></div>
    </form>
  </main>
  <script src="assets/js/main.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      var form = document.getElementById("receiptForm");
      var Z = window.Zenvita;
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var btn = document.getElementById("receiptSubmitBtn");
        var result = document.getElementById("receiptResult");
        var data = new FormData(form);
        if (btn) btn.disabled = true;
        fetch("api/registration-proof.php", {
          method: "POST",
          credentials: "same-origin",
          body: data
        })
        .then(function (res) { return res.json(); })
        .then(function (payload) {
          if (btn) btn.disabled = false;
          if (!payload || !payload.ok) {
            if (Z) Z.showToast((payload && payload.error) || "Could not submit receipt.", "error");
            return;
          }
          result.hidden = false;
          result.textContent = (payload.message || "Submitted.") + " Keep this reference: " + payload.reference +
            ". After admin approval, open Register and check this reference to create your account.";
          if (Z) Z.showToast("Receipt submitted for admin approval.");
          form.reset();
        })
        .catch(function () {
          if (btn) btn.disabled = false;
          if (Z) Z.showToast("Server did not respond.", "error");
        });
      });
    });
  </script>
</body>
</html>
