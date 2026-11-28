<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Packages · Portal · Cellvigor Biorevive LTD</title>
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
        <a class="sidebar-link is-active" aria-current="page" href="packages"><i class="bi bi-box-seam"></i> Packages</a>
        <a class="sidebar-link" href="products"><i class="bi bi-capsule"></i> Products</a>
        <a class="sidebar-link" href="stores"><i class="bi bi-shop"></i> Stores</a>
        <a class="sidebar-link" href="orders"><i class="bi bi-bag-check"></i> Orders</a>
        
        <a class="sidebar-link" href="payments"><i class="bi bi-credit-card"></i> Payments</a>
        <a class="sidebar-link" href="commissions"><i class="bi bi-cash-stack"></i> Commissions</a>
        <a class="sidebar-link" href="withdrawals"><i class="bi bi-bank"></i> Withdrawals</a>
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
        <div class="topbar-title"><strong>Packages</strong><small>Local catalogue</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar">AD</span><span class="profile-meta"><strong>Admin</strong><small>PORTAL</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="settings">Settings</a></li><li><a class="dropdown-item" href="login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head">
          <div><h1>Packages</h1><p>Create packages, edit pricing details, and write the description members see.</p></div>
          <button class="btn btn-brand" type="button" id="addPackageBtn">Add package</button>
        </div>
        <section class="panel">
          <div class="table-wrap">
            <table class="data">
              <thead>
                <tr>
                  <th>Package name</th>
                  <th>Description</th>
                  <th>Price</th>
                  <th>PV</th>
                  <th>Product quantity</th>
                  <th>Referral percentage</th>
                  <th>Pairing value</th>
                  <th>Daily cap</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="adminPackageBody"><tr><td class="loading-row" colspan="9">Loading packages…</td></tr></tbody>
            </table>
          </div>
        </section>
      </main>
      <footer class="app-footer">Cellvigor Biorevive LTD</footer>
    </div>
  </div>
  <div class="modal fade" id="packageModal" tabindex="-1" aria-labelledby="packageTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <form class="modal-content" id="packageForm">
        <div class="modal-header"><h2 class="modal-title h5" id="packageTitle">Edit package</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <input type="hidden" name="key" value="">
          <input type="hidden" name="create" value="0">
          <div class="mb-3"><label class="form-label" for="pkgName">Package name</label><input class="form-control" id="pkgName" name="name" maxlength="40" required></div>
          <div class="mb-3">
            <label class="form-label" for="pkgDescription">Description</label>
            <textarea class="form-control" id="pkgDescription" name="description" rows="4" maxlength="2000" placeholder="What members see about this package"></textarea>
            <p class="text-muted small mb-0" id="pkgDescriptionHint">Shown on member package pages and upgrade cards.</p>
          </div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="pkgPrice">Price</label><input class="form-control" id="pkgPrice" name="price" type="number" min="0" step="1" required></div>
            <div class="col-md-6"><label class="form-label" for="pkgPv">PV</label><input class="form-control" id="pkgPv" name="pv" type="number" min="0" required></div>
            <div class="col-md-6"><label class="form-label" for="pkgBottles">Product quantity</label><input class="form-control" id="pkgBottles" name="bottles" type="number" min="0" required></div>
            <div class="col-md-6"><label class="form-label" for="pkgReferral">Referral percentage</label><input class="form-control" id="pkgReferral" name="referral" type="number" min="0" step="0.1" required></div>
            <div class="col-md-6"><label class="form-label" for="pkgPairing">Pairing value</label><input class="form-control" id="pkgPairing" name="pairing" type="number" min="0" required></div>
            <div class="col-md-6"><label class="form-label" for="pkgCap">Daily cap</label><input class="form-control" id="pkgCap" name="dailyCap" type="number" min="0" required></div>
            <div class="col-md-6"><label class="form-label" for="pkgMatches">Daily pairs</label><input class="form-control" id="pkgMatches" name="matches" type="number" min="1" value="45"></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand" type="submit" id="packageSaveBtn">Save</button></div>
      </form>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>
