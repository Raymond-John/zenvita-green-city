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
  <title>Members · Portal · Cellvigor Biorevive LTD</title>
  <link rel="icon" href="../assets/images/cellvigor-logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,560;1,9..144,560&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="app-body admin-body">
  <div class="app-shell">
    <aside class="app-sidebar" id="appSidebar">
      <div class="d-flex"><a class="sidebar-brand" href="index"><img src="../assets/images/cellvigor-logo.png" alt=""><span><strong>CELLVIGOR</strong><small>PORTAL</small></span></a><button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close menu"><i class="bi bi-x-lg"></i></button></div>
      <nav class="sidebar-nav" aria-label="Portal">
        <a class="sidebar-link" href="index"><i class="bi bi-grid"></i> Dashboard</a>
        <a class="sidebar-link is-active" aria-current="page" href="members"><i class="bi bi-people"></i> Members</a>
        <a class="sidebar-link" href="network"><i class="bi bi-diagram-3"></i> Network</a>
        <a class="sidebar-link" href="packages"><i class="bi bi-box-seam"></i> Packages</a>
        <a class="sidebar-link" href="products"><i class="bi bi-capsule"></i> Products</a>
        <a class="sidebar-link" href="stores"><i class="bi bi-shop"></i> Stores</a>
        <a class="sidebar-link" href="orders"><i class="bi bi-bag-check"></i> Orders</a>
        
        <a class="sidebar-link" href="payments"><i class="bi bi-credit-card"></i> Payments</a>
        <a class="sidebar-link" href="commissions"><i class="bi bi-cash-stack"></i> Commissions</a>
        <a class="sidebar-link" href="withdrawals"><i class="bi bi-bank"></i> Withdrawals</a>
        <a class="sidebar-link" href="transactions"><i class="bi bi-receipt"></i> Transactions</a>
        <a class="sidebar-link" href="settings"><i class="bi bi-gear"></i> Settings</a>
      </nav>
      <div class="sidebar-foot"><a class="sidebar-link" href="login" data-logout><i class="bi bi-box-arrow-left"></i> Logout</a></div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
      <header class="app-topbar">
        <button class="icon-btn menu-toggle" id="sidebarToggle" type="button" aria-expanded="false" aria-controls="appSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><strong>Members</strong><small>Local records</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar">AD</span><span class="profile-meta"><strong>Admin</strong><small>PORTAL</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="settings">Settings</a></li><li><a class="dropdown-item" href="login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head"><div><h1>Members</h1><p>Register members with the same form used on the public join page. Leave sponsor empty only for the first member.</p></div></div>
        <section class="panel mb-3">
          <div class="panel-head"><h2>Register user</h2></div>
          <form id="adminAddUserForm" class="register-grid" novalidate>
            <div class="register-full">
              <label class="form-group-label" for="adminSponsor">Sponsor Referral Code / Username</label>
              <input class="form-control" id="adminSponsor" name="sponsor" placeholder="Leave empty for the first member">
              <p class="field-error" id="adminSponsor-error"></p>
            </div>
            <div class="register-full">
              <label class="form-group-label">Placement Side</label>
              <div class="side-selector">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="side" id="adminSideLeft" value="left" checked>
                  <label class="form-check-label fw-bold" for="adminSideLeft">Left Team</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="side" id="adminSideRight" value="right">
                  <label class="form-check-label fw-bold" for="adminSideRight">Right Team</label>
                </div>
              </div>
              <p class="field-error" id="adminSide-error"></p>
            </div>
            <div class="register-full">
              <label class="form-group-label" for="adminPackage">Select Package</label>
              <select class="form-select form-control" id="adminPackage" name="package" required>
                <?php foreach ($packages as $pkg): ?>
                  <?php $desc = trim((string) ($pkg['description'] ?? '')); ?>
                  <option value="<?= htmlspecialchars($pkg['name']) ?>"<?= $desc !== '' ? ' title="' . htmlspecialchars($desc) . '"' : '' ?>>
                    <?= htmlspecialchars($pkg['name']) ?> — ₦<?= number_format((float) $pkg['price'], 0) ?> (<?= (int) $pkg['pv'] ?> PV, <?= (int) $pkg['bottles'] ?> Bottles)
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="field-error" id="adminPackage-error"></p>
            </div>
            <div>
              <label class="form-group-label" for="adminFirstName">First Name</label>
              <input class="form-control" id="adminFirstName" name="firstName" placeholder="First Name" required>
              <p class="field-error" id="adminFirstName-error"></p>
            </div>
            <div>
              <label class="form-group-label" for="adminLastName">Last Name</label>
              <input class="form-control" id="adminLastName" name="lastName" placeholder="Last Name">
              <p class="field-error" id="adminLastName-error"></p>
            </div>
            <div>
              <label class="form-group-label" for="adminEmail">Email Address</label>
              <input class="form-control" id="adminEmail" name="email" type="email" placeholder="email@example.com" autocomplete="off" required>
              <p class="field-error" id="adminEmail-error"></p>
            </div>
            <div>
              <label class="form-group-label" for="adminPhone">Phone Number</label>
              <input class="form-control" id="adminPhone" name="phone" type="tel" placeholder="08012345678" autocomplete="off" required>
              <p class="field-error" id="adminPhone-error"></p>
            </div>
            <div class="register-full">
              <label class="form-group-label" for="adminUsername">Username</label>
              <input class="form-control" id="adminUsername" name="username" placeholder="Choose a username (min 3 chars)" autocomplete="off" required>
              <p class="field-error" id="adminUsername-error"></p>
            </div>
            <div>
              <label class="form-group-label" for="adminPassword">Password</label>
              <div class="password-field">
                <input class="form-control" id="adminPassword" name="password" type="password" placeholder="Min 8 characters" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-toggle-password="adminPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
              </div>
              <p class="field-error" id="adminPassword-error"></p>
            </div>
            <div>
              <label class="form-group-label" for="adminConfirmPassword">Confirm Password</label>
              <div class="password-field">
                <input class="form-control" id="adminConfirmPassword" name="confirmPassword" type="password" placeholder="Confirm password" autocomplete="new-password" required>
                <button class="password-toggle" type="button" data-toggle-password="adminConfirmPassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
              </div>
              <p class="field-error" id="adminConfirmPassword-error"></p>
            </div>
            <div class="register-full mt-2">
              <div class="form-check text-start">
                <input class="form-check-input" type="checkbox" id="adminTerms" name="terms" required checked>
                <label class="form-check-label" for="adminTerms">I confirm this account agrees to the Cellvigor Biorevive LTD terms &amp; conditions</label>
              </div>
              <p class="field-error" id="adminTerms-error"></p>
            </div>
            <div class="register-full">
              <button class="btn btn-brand" type="submit" id="adminRegisterSubmitBtn">CREATE ACCOUNT</button>
            </div>
          </form>
        </section>
        <section class="panel">
          <div class="toolbar">
            <label class="visually-hidden" for="memberSearch">Search members</label>
            <input class="form-control" id="memberSearch" type="search" placeholder="Search name, email, or ID">
            <label class="visually-hidden" for="memberPackage">Package</label>
            <select class="form-select" id="memberPackage"><option value="">All packages</option><option>Vision</option><option>Silver</option><option>Gold</option><option>Diamond</option></select>
            <label class="visually-hidden" for="memberStatus">Status</label>
            <select class="form-select" id="memberStatus"><option value="">All statuses</option><option>Active</option><option>Inactive</option><option>Suspended</option></select>
          </div>
          <div class="table-wrap" id="memberTableWrap">
            <table class="data">
              <thead><tr><th>Member ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Package</th><th>Award Rank</th><th>PV</th><th>Status</th><th>Join Date</th><th>Actions</th></tr></thead>
              <tbody id="adminMemberBody"><tr><td class="loading-row" colspan="10">Loading records…</td></tr></tbody>
            </table>
          </div>
          <div class="empty-state" id="memberEmpty" hidden>No members match this filter.</div>
          <div class="pager" id="memberPager"></div>
        </section>
      </main>
      <footer class="app-footer">Cellvigor Biorevive LTD</footer>
    </div>
  </div>
  <div class="modal fade" id="viewMemberModal" tabindex="-1" aria-labelledby="viewMemberTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="viewMemberTitle">Member</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="viewMemberBody"></div></div></div></div>
  <div class="modal fade" id="editMemberModal" tabindex="-1" aria-labelledby="editMemberTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="editMemberForm"><div class="modal-header"><h2 class="modal-title h5" id="editMemberTitle">Edit member</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><input type="hidden" name="memberId"><div class="mb-3"><label class="form-label" for="editName">Name</label><input class="form-control" id="editName" name="name" required></div><div class="mb-3"><label class="form-label" for="editEmail">Email</label><input class="form-control" id="editEmail" name="email" type="email" required></div><div class="mb-3"><label class="form-label" for="editPhone">Phone</label><input class="form-control" id="editPhone" name="phone" required></div><div class="mb-3"><label class="form-label" for="editPackage">Package</label><select class="form-select" id="editPackage" name="package"><option>Vision</option><option>Silver</option><option>Gold</option><option>Diamond</option></select></div><div class="mb-3"><label class="form-label" for="editAwardRank">Award Rank</label><input class="form-control" id="editAwardRank" name="awardRank" readonly><p class="text-muted small mb-0 mt-1">Assigned automatically by generation (first → end of the tree).</p></div><div><label class="form-label" for="editStatus">Status</label><select class="form-select" id="editStatus" name="status"><option>Active</option><option>Inactive</option><option>Suspended</option></select></div></div><div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand" type="submit">Save</button></div></form></div></div></div>
  <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="confirmTitle">Confirm</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p id="confirmText"></p></div><div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand" type="button" id="confirmYes">Confirm</button></div></div></div></div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>
