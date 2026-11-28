<?php
require_once __DIR__ . '/../api/bootstrap.php';
$pdo = db();
ensure_package_description_column($pdo);
$packages = $pdo->query('SELECT code, name, description, price, pv, bottles FROM packages ORDER BY price ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Register user · Cellvigor Biorevive LTD</title>
  <link rel="icon" href="../assets/images/cellvigor-logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,560;1,9..144,560&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="app-body">
  <div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
      <div class="d-flex"><a class="sidebar-brand" href="index"><img src="../assets/images/cellvigor-logo.png" alt=""><span><strong>CELLVIGOR</strong><small>BIOREVIVE LTD</small></span></a><button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close menu"><i class="bi bi-x-lg"></i></button></div>
      <nav class="sidebar-nav" aria-label="Member">
        <a class="sidebar-link" href="index"><i class="bi bi-grid"></i> Dashboard</a>
        <div class="sidebar-group is-open">
          <button class="sidebar-link sidebar-toggle" type="button" aria-expanded="true"><i class="bi bi-diagram-3"></i><span>My Network</span><i class="bi bi-chevron-down sidebar-caret"></i></button>
          <div class="sidebar-sub">
            <a class="sidebar-link" href="network"><i class="bi bi-diagram-2"></i> Network</a>
            <a class="sidebar-link is-active" aria-current="page" href="register-user"><i class="bi bi-person-plus"></i> Register user</a>
          </div>
        </div>
        <a class="sidebar-link" href="commissions"><i class="bi bi-cash-stack"></i> Commissions</a>
        <a class="sidebar-link" href="packages"><i class="bi bi-box-seam"></i> My Package</a>
        <a class="sidebar-link" href="order"><i class="bi bi-cart3"></i> Order products</a>
        <a class="sidebar-link" href="withdrawals"><i class="bi bi-bank"></i> Withdrawals</a>
        <a class="sidebar-link" href="eagle-lands"><i class="bi bi-buildings"></i> Eagle land and Homes LTD</a>
        <a class="sidebar-link" href="transactions"><i class="bi bi-receipt"></i> Transactions</a>
        <a class="sidebar-link" href="profile"><i class="bi bi-person"></i> Profile</a>
      </nav>
      <div class="sidebar-foot"><a class="sidebar-link" href="../login" data-logout><i class="bi bi-box-arrow-left"></i> Logout</a></div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
      <header class="app-topbar">
        <button class="icon-btn menu-toggle" id="sidebarToggle" type="button" aria-expanded="false" aria-controls="appSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><strong>Register user</strong><small>Same as public register</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar" data-user-avatar>AO</span><span class="profile-meta"><strong data-user-name>Member</strong><small data-user-award-rank>Member</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile">Profile</a></li><li><a class="dropdown-item" href="../login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head">
          <div>
            <h1>Register user</h1>
            <p>This is the same registration form used on the public join page. You are set as the sponsor. You can also share your referral links below.</p>
          </div>
        </div>
        <section class="panel mb-3">
          <div class="panel-head"><h2>Share registration link</h2></div>
          <div class="ref-bar mb-2">
            <label class="visually-hidden" for="addUserLeftLink">Left referral link</label>
            <input class="form-control" id="addUserLeftLink" readonly value="">
            <button class="btn" type="button" data-copy-target="addUserLeftLink">Copy left link</button>
          </div>
          <div class="ref-bar">
            <label class="visually-hidden" for="addUserRightLink">Right referral link</label>
            <input class="form-control" id="addUserRightLink" readonly value="">
            <button class="btn" type="button" data-copy-target="addUserRightLink">Copy right link</button>
          </div>
          <p class="text-muted small mb-0 mt-2">Those links open <a href="../register" id="registerFormLink">register</a> with your sponsor code filled in.</p>
        </section>
        <section class="panel">
          <form id="addUserForm" class="register-grid" novalidate>
            <div class="register-full">
              <label class="form-group-label" for="newSponsor">Sponsor Referral Code / Username</label>
              <input class="form-control" id="newSponsor" name="sponsor" readonly value="">
              <p class="field-error" id="newSponsor-error"></p>
              <p class="text-muted small mb-0">Locked to your account because you are creating this member from your dashboard.</p>
            </div>

            <div class="register-full">
              <label class="form-group-label">Placement Side</label>
              <div class="side-selector">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="side" id="newSideLeft" value="left" checked>
                  <label class="form-check-label fw-bold" for="newSideLeft">Left Team</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="side" id="newSideRight" value="right">
                  <label class="form-check-label fw-bold" for="newSideRight">Right Team</label>
                </div>
              </div>
              <p class="field-error" id="newSide-error"></p>
            </div>

            <div class="register-full">
              <label class="form-group-label" for="newPackage">Select Package</label>
              <select class="form-select form-control" id="newPackage" name="package" required>
                <?php foreach ($packages as $pkg): ?>
                  <?php $desc = trim((string) ($pkg['description'] ?? '')); ?>
                  <option value="<?= htmlspecialchars($pkg['name']) ?>" data-price="<?= (float) $pkg['price'] ?>"<?= $desc !== '' ? ' title="' . htmlspecialchars($desc) . '"' : '' ?>>
                    <?= htmlspecialchars($pkg['name']) ?> — ₦<?= number_format((float) $pkg['price'], 0) ?> (<?= (int) $pkg['pv'] ?> PV, <?= (int) $pkg['bottles'] ?> Bottles)
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="field-error" id="newPackage-error"></p>
            </div>

            <div>
              <label class="form-group-label" for="newFirstName">First Name</label>
              <input class="form-control" id="newFirstName" name="firstName" placeholder="First Name" required>
              <p class="field-error" id="newFirstName-error"></p>
            </div>
            <div>
              <label class="form-group-label" for="newLastName">Last Name</label>
              <input class="form-control" id="newLastName" name="lastName" placeholder="Last Name">
              <p class="field-error" id="newLastName-error"></p>
            </div>

            <div>
              <label class="form-group-label" for="newEmail">Email Address</label>
              <input class="form-control" id="newEmail" name="email" type="email" placeholder="email@example.com" autocomplete="off" required>
              <p class="field-error" id="newEmail-error"></p>
            </div>
            <div>
              <label class="form-group-label" for="newPhone">Phone Number</label>
              <input class="form-control" id="newPhone" name="phone" type="tel" placeholder="08012345678" autocomplete="off" required>
              <p class="field-error" id="newPhone-error"></p>
            </div>

            <div class="register-full">
              <label class="form-group-label" for="newUsername">Username</label>
              <input class="form-control" id="newUsername" name="username" placeholder="Choose a username (min 3 chars)" autocomplete="off" required>
              <p class="field-error" id="newUsername-error"></p>
            </div>

            <div>
              <label class="form-group-label" for="newPassword">Password</label>
              <div class="password-field">
                <input class="form-control" id="newPassword" name="password" type="password" placeholder="Min 8 characters" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-toggle-password="newPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
              </div>
              <p class="field-error" id="newPassword-error"></p>
            </div>
            <div>
              <label class="form-group-label" for="newConfirmPassword">Confirm Password</label>
              <div class="password-field">
                <input class="form-control" id="newConfirmPassword" name="confirmPassword" type="password" placeholder="Confirm password" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-toggle-password="newConfirmPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
              </div>
              <p class="field-error" id="newConfirmPassword-error"></p>
            </div>

            <div class="register-full mt-2">
              <div class="form-check text-start">
                <input class="form-check-input" type="checkbox" id="newTerms" name="terms" required checked>
                <label class="form-check-label" for="newTerms">I confirm this account agrees to the Cellvigor Biorevive LTD terms &amp; conditions</label>
              </div>
              <p class="field-error" id="newTerms-error"></p>
            </div>

            <div class="register-full">
              <button class="btn btn-brand" type="submit" id="addUserSubmitBtn">CREATE ACCOUNT</button>
            </div>
          </form>
        </section>
      </main>
      <footer class="app-footer">Cellvigor Biorevive LTD</footer>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/withdrawal-policy.js"></script>
  <script src="../assets/js/dashboard.js"></script>
</body>
</html>
