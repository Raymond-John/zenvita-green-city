<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Eagle Lands &amp; Properties · Cellvigor Biorevive LTD</title>
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
        <a class="sidebar-link is-active" aria-current="page" href="eagle-lands"><i class="bi bi-buildings"></i> Eagle Lands</a>
        <a class="sidebar-link" href="transactions"><i class="bi bi-receipt"></i> Transactions</a>
        <a class="sidebar-link" href="profile"><i class="bi bi-person"></i> Profile</a>
      </nav>
      <div class="sidebar-foot"><a class="sidebar-link" href="../login" data-logout><i class="bi bi-box-arrow-left"></i> Logout</a></div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
      <header class="app-topbar">
        <button class="icon-btn menu-toggle" id="sidebarToggle" type="button" aria-expanded="false" aria-controls="appSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><strong>Eagle Lands &amp; Properties</strong><small>Reserved 60%</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar" data-user-avatar>AO</span><span class="profile-meta"><strong data-user-name>Member</strong><small>Member</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile">Profile</a></li><li><a class="dropdown-item" href="../login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head"><div><h1>Eagle Lands &amp; Properties</h1><p>For Vision–Platinum plans, 60% of every Saturday withdrawal cycle is reserved here. Plans below Vision are not on the 40/60 reduction.</p></div></div>
        <section class="wallet-row mb-3">
          <article class="wallet-card"><span>Eagle Lands Reserve</span><strong id="eagleLandsBalance">…</strong><a href="transactions">View related transactions</a></article>
          <article class="wallet-card"><span>Wallet Balance</span><strong id="eagleWalletBalance">…</strong><a href="withdrawals">Open withdrawals</a></article>
          <article class="wallet-card"><span>Withdrawable (40%)</span><strong id="eagleWithdrawable">…</strong><a href="withdrawals">Request on Saturday</a></article>
        </section>
        <section class="panel mb-3">
          <div class="panel-head"><h2>Reserve history</h2></div>
          <p class="text-muted small">Each Saturday cash withdrawal moves a matching 60% into Eagle Lands. Those reserved amounts are listed here.</p>
          <div class="table-wrap" id="eagleReserveWrap">
            <table class="data">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Cash (40%)</th>
                  <th>Eagle Lands (60%)</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="eagleReserveBody"><tr><td class="loading-row" colspan="4">Loading reserve history…</td></tr></tbody>
            </table>
          </div>
          <div class="empty-state" id="eagleReserveEmpty" hidden>No Eagle Lands reserves yet. They appear when you request a Saturday withdrawal.</div>
        </section>
        <section class="panel">
          <h2>How it works</h2>
          <ul class="mb-0">
            <li>Cash withdrawals are allowed only on <strong>Saturdays</strong>.</li>
            <li>Vision–Platinum members may withdraw up to <strong>40%</strong> of available balance as cash.</li>
            <li>For those plans, the matching <strong>60%</strong> is moved into this Eagle Lands &amp; Properties reserve.</li>
            <li>Plans below Vision are not on the 40/60 split and can withdraw without that Eagle Lands reduction.</li>
          </ul>
        </section>
      </main>
      <footer class="app-footer">Cellvigor Biorevive LTD</footer>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/dashboard.js"></script>
</body>
</html>
