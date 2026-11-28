<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Withdrawals · Portal · Cellvigor Biorevive LTD</title>
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
        <a class="sidebar-link" href="members"><i class="bi bi-people"></i> Members</a>
        <a class="sidebar-link" href="network"><i class="bi bi-diagram-3"></i> Network</a>
        <a class="sidebar-link" href="packages"><i class="bi bi-box-seam"></i> Packages</a>
        <a class="sidebar-link" href="products"><i class="bi bi-capsule"></i> Products</a>
        <a class="sidebar-link" href="stores"><i class="bi bi-shop"></i> Stores</a>
        <a class="sidebar-link" href="orders"><i class="bi bi-bag-check"></i> Orders</a>
        
        <a class="sidebar-link" href="payments"><i class="bi bi-credit-card"></i> Payments</a>
        <a class="sidebar-link" href="commissions"><i class="bi bi-cash-stack"></i> Commissions</a>
        <a class="sidebar-link is-active" aria-current="page" href="withdrawals"><i class="bi bi-bank"></i> Withdrawals</a>
        <a class="sidebar-link" href="transactions"><i class="bi bi-receipt"></i> Transactions</a>
        <a class="sidebar-link" href="emails"><i class="bi bi-envelope"></i> Emails</a>
        <a class="sidebar-link" href="settings"><i class="bi bi-gear"></i> Settings</a>
      </nav>
      <div class="sidebar-foot"><a class="sidebar-link" href="login" data-logout><i class="bi bi-box-arrow-left"></i> Logout</a></div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
      <header class="app-topbar">
        <button class="icon-btn menu-toggle" id="sidebarToggle" type="button" aria-expanded="false" aria-controls="appSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><strong>Withdrawals</strong><small>No bank transfer</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar">AD</span><span class="profile-meta"><strong>Admin</strong><small>PORTAL</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="settings">Settings</a></li><li><a class="dropdown-item" href="login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head"><div><h1>Withdrawal requests</h1><p>Cash is 40%. Matching 60% is reserved for Eagle land and Homes LTD. Withdrawals are member-requested on Saturdays only.</p></div></div>
        <section class="panel">
          <div class="table-wrap">
            <table class="data"><thead><tr><th>Member</th><th>Cash (40%)</th><th>Eagle land and Homes LTD (60%)</th><th>Bank</th><th>Account</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody id="adminWithdrawalBody"><tr><td class="loading-row" colspan="8">Loading records…</td></tr></tbody></table>
          </div>
        </section>
      </main>
      <footer class="app-footer">Cellvigor Biorevive LTD</footer>
    </div>
  </div>
  <div class="modal fade" id="viewWithdrawalModal" tabindex="-1" aria-labelledby="viewWithdrawalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="viewWithdrawalTitle">Withdrawal</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="viewWithdrawalBody"></div></div></div></div>
  <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="confirmTitle">Confirm</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p id="confirmText"></p></div><div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand" type="button" id="confirmYes">Confirm</button></div></div></div></div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>
