<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Profile · Cellvigor Biorevive LTD</title>
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
        <div class="sidebar-group">
          <button class="sidebar-link sidebar-toggle" type="button" aria-expanded="false"><i class="bi bi-diagram-3"></i><span>My Network</span><i class="bi bi-chevron-down sidebar-caret"></i></button>
          <div class="sidebar-sub">
            <a class="sidebar-link" href="network"><i class="bi bi-diagram-2"></i> Network</a>
            <a class="sidebar-link" href="register-user"><i class="bi bi-person-plus"></i> Register user</a>
          </div>
        </div>
        <a class="sidebar-link" href="commissions"><i class="bi bi-cash-stack"></i> Commissions</a>
        <a class="sidebar-link" href="packages"><i class="bi bi-box-seam"></i> My Package</a>
        <a class="sidebar-link" href="order"><i class="bi bi-cart3"></i> Order products</a>
        <a class="sidebar-link" href="withdrawals"><i class="bi bi-bank"></i> Withdrawals</a>
        <a class="sidebar-link" href="eagle-lands"><i class="bi bi-buildings"></i> Eagle land and Homes LTD</a>
        <a class="sidebar-link" href="transactions"><i class="bi bi-receipt"></i> Transactions</a>
        <a class="sidebar-link is-active" aria-current="page" href="profile"><i class="bi bi-person"></i> Profile</a>
      </nav>
      <div class="sidebar-foot"><a class="sidebar-link" href="../login" data-logout><i class="bi bi-box-arrow-left"></i> Logout</a></div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
      <header class="app-topbar">
        <button class="icon-btn menu-toggle" id="sidebarToggle" type="button" aria-expanded="false" aria-controls="appSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><strong>Profile</strong><small>Saved in this browser</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar" data-user-avatar>AO</span><span class="profile-meta"><strong data-user-name>Member</strong><small data-user-award-rank>Member</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile">Profile</a></li><li><a class="dropdown-item" href="../login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head">
          <div><h1>Profile</h1><p>Personal, contact, referral, bank, and security details stored for this account.</p></div>
          <div class="d-flex gap-2">
            <button class="btn btn-soft" type="button" id="editProfile">Edit Profile</button>
            <button class="btn btn-outline-brand" type="button" data-bs-toggle="modal" data-bs-target="#passwordModal">Change Password</button>
          </div>
        </div>
        <form id="profileForm" novalidate>
          <div class="row g-3">
            <div class="col-lg-6"><section class="panel"><h2>Personal Information</h2><div class="mb-3"><label class="form-label" for="firstName">First name</label><input class="form-control" id="firstName" name="firstName" readonly><p class="field-error" id="firstName-error"></p></div><div class="mb-3"><label class="form-label" for="lastName">Last name</label><input class="form-control" id="lastName" name="lastName" readonly></div><div><label class="form-label" for="city">City</label><input class="form-control" id="city" name="city" readonly></div></section></div>
            <div class="col-lg-6"><section class="panel"><h2>Contact Information</h2><div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" readonly><p class="field-error" id="email-error"></p></div><div class="mb-3"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" readonly><p class="field-error" id="phone-error"></p></div><div><label class="form-label" for="country">Country</label><input class="form-control" id="country" name="country" readonly></div></section></div>
            <div class="col-lg-6"><section class="panel"><h2>Referral Information</h2><div class="mb-3"><label class="form-label" for="referralId">Referral ID</label><input class="form-control" id="referralId" name="referralId" readonly></div><div class="mb-3"><label class="form-label" for="sponsor">Sponsor code</label><input class="form-control" id="sponsor" name="sponsor" readonly></div><div class="mb-3"><label class="form-label" for="awardRank">Award rank</label><input class="form-control" id="awardRank" name="awardRank" data-user-award-rank readonly></div><div><label class="form-label" for="accountPackage">Account package</label><input class="form-control" id="accountPackage" name="accountPackage" data-user-package readonly></div></section></div>
            <div class="col-lg-6"><section class="panel"><h2>Bank Information</h2><div class="mb-3"><label class="form-label" for="bank">Bank name</label><input class="form-control" id="bank" name="bank" readonly></div><div class="mb-3"><label class="form-label" for="accountName">Account name</label><input class="form-control" id="accountName" name="accountName" readonly></div><div><label class="form-label" for="accountNumber">Account number</label><input class="form-control" id="accountNumber" name="accountNumber" readonly><p class="field-error" id="accountNumber-error"></p></div></section></div>
            <div class="col-12"><section class="panel"><h2>Security</h2><p>Ensure you use a strong password with at least 8 characters to secure your backoffice account.</p><button class="btn btn-brand" type="submit" id="saveProfile" disabled>Save Changes</button></section></div>
          </div>
        </form>
      </main>
      <footer class="app-footer">Cellvigor Biorevive LTD</footer>
    </div>
  </div>
  <div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" id="passwordForm" novalidate>
        <div class="modal-header"><h2 class="modal-title h5" id="passwordTitle">Change Password</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label" for="currentPassword">Current password</label><div class="password-field"><input class="form-control" id="currentPassword" name="currentPassword" type="password"><button class="password-toggle" type="button" data-toggle-password="currentPassword" aria-label="Show password"><i class="bi bi-eye"></i></button></div><p class="field-error" id="currentPassword-error"></p></div>
          <div class="mb-3"><label class="form-label" for="newPassword">New password</label><div class="password-field"><input class="form-control" id="newPassword" name="newPassword" type="password"><button class="password-toggle" type="button" data-toggle-password="newPassword" aria-label="Show password"><i class="bi bi-eye"></i></button></div><p class="field-error" id="newPassword-error"></p></div>
          <div><label class="form-label" for="confirmNewPassword">Confirm new password</label><div class="password-field"><input class="form-control" id="confirmNewPassword" name="confirmNewPassword" type="password"><button class="password-toggle" type="button" data-toggle-password="confirmNewPassword" aria-label="Show password"><i class="bi bi-eye"></i></button></div><p class="field-error" id="confirmNewPassword-error"></p></div>
        </div>
        <div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand" type="submit">Change Password</button></div>
      </form>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/withdrawal-policy.js"></script>
  <script src="../assets/js/dashboard.js"></script>
</body>
</html>
