<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Stores · Portal · Cellvigor Biorevive LTD</title>
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
        <a class="sidebar-link is-active" aria-current="page" href="stores"><i class="bi bi-shop"></i> Stores</a>
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
        <div class="topbar-title"><strong>Stores</strong><small class="admin-kicker">Owners</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar">AD</span><span class="profile-meta"><strong>Admin</strong><small>PORTAL</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="settings">Settings</a></li><li><a class="dropdown-item" href="login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head"><div><h1>Store owners</h1><p>Register store owners. Members can choose an active store when booking products for delivery or pickup.</p></div></div>
        <section class="panel mb-3">
          <div class="panel-head"><h2>Register a store owner</h2></div>
          <form id="adminStoreForm" class="row g-3" novalidate>
            <div class="col-md-4"><label class="form-label" for="storeFirstName">Owner first name</label><input class="form-control" id="storeFirstName" name="firstName" required></div>
            <div class="col-md-4"><label class="form-label" for="storeLastName">Owner last name</label><input class="form-control" id="storeLastName" name="lastName"></div>
            <div class="col-md-4"><label class="form-label" for="storeUsername">Username</label><input class="form-control" id="storeUsername" name="username" autocomplete="off" required></div>
            <div class="col-md-4"><label class="form-label" for="storePassword">Password</label><input class="form-control" id="storePassword" name="password" type="password" autocomplete="new-password" required></div>
            <div class="col-md-4"><label class="form-label" for="storeEmail">Email</label><input class="form-control" id="storeEmail" name="email" type="email" autocomplete="off"></div>
            <div class="col-md-4"><label class="form-label" for="storePhone">Phone</label><input class="form-control" id="storePhone" name="phone" type="tel"></div>
            <div class="col-md-4"><label class="form-label" for="storeName">Store name</label><input class="form-control" id="storeName" name="storeName" required></div>
            <div class="col-md-4"><label class="form-label" for="storeCity">City</label><input class="form-control" id="storeCity" name="city"></div>
            <div class="col-md-4"><label class="form-label" for="storeAddress">Address</label><input class="form-control" id="storeAddress" name="address"></div>
            <div class="col-12"><button class="btn btn-brand" type="submit">Create store owner</button></div>
          </form>
        </section>
        <section class="panel">
          <div class="panel-head"><h2>Registered stores</h2></div>
          <div class="table-wrap">
            <table class="data">
              <thead><tr><th>Store ID</th><th>Store</th><th>Owner</th><th>Username</th><th>City</th><th>Phone</th><th>Status</th><th>Created</th><th></th></tr></thead>
              <tbody id="adminStoreBody"><tr><td class="loading-row" colspan="9">Loading stores…</td></tr></tbody>
            </table>
          </div>
        </section>
      </main>
      <footer class="app-footer">Cellvigor Biorevive LTD</footer>
    </div>
  </div>
  <div class="modal fade" id="editStoreModal" tabindex="-1" aria-labelledby="editStoreTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" id="editStoreForm">
        <div class="modal-header"><h2 class="modal-title h5" id="editStoreTitle">Edit store</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <input type="hidden" name="storeId">
          <div class="mb-3"><label class="form-label" for="editStoreName">Store name</label><input class="form-control" id="editStoreName" name="storeName" required></div>
          <div class="mb-3"><label class="form-label" for="editStoreAddress">Address</label><input class="form-control" id="editStoreAddress" name="address"></div>
          <div class="mb-3"><label class="form-label" for="editStoreCity">City</label><input class="form-control" id="editStoreCity" name="city"></div>
          <div class="mb-3"><label class="form-label" for="editStorePhone">Phone</label><input class="form-control" id="editStorePhone" name="phone"></div>
          <div><label class="form-label" for="editStoreStatus">Status</label><select class="form-select" id="editStoreStatus" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        </div>
        <div class="modal-footer"><button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand" type="submit">Save</button></div>
      </form>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>
