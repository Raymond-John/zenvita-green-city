<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Dashboard · Cellvigor Biorevive LTD</title>
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
      <div class="d-flex">
        <a class="sidebar-brand" href="index"><img src="../assets/images/cellvigor-logo.png" alt=""><span><strong>CELLVIGOR</strong><small>BIOREVIVE LTD</small></span></a>
        <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
      </div>
      <nav class="sidebar-nav" aria-label="Member">
        <a class="sidebar-link is-active" aria-current="page" href="index"><i class="bi bi-grid"></i> Dashboard</a>
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
        <a class="sidebar-link" href="eagle-lands"><i class="bi bi-buildings"></i> Eagle Lands</a>
        <a class="sidebar-link" href="transactions"><i class="bi bi-receipt"></i> Transactions</a>
        <a class="sidebar-link" href="profile"><i class="bi bi-person"></i> Profile</a>
      </nav>
      <div class="sidebar-foot"><a class="sidebar-link" href="../login" data-logout><i class="bi bi-box-arrow-left"></i> Logout</a></div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
      <header class="app-topbar">
        <button class="icon-btn menu-toggle" id="sidebarToggle" type="button" aria-expanded="false" aria-controls="appSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><strong>Dashboard</strong><small>Member workspace</small></div>
        <div class="dropdown">
          <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button>
          <div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div>
        </div>
        <div class="dropdown">
          <button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar" data-user-avatar>AO</span><span class="profile-meta"><strong data-user-name>Member</strong><small>Member</small></span></button>
          <ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile">Profile</a></li><li><a class="dropdown-item" href="../login" data-logout>Logout</a></li></ul>
        </div>
      </header>
      <main class="app-content" id="main">
        <div class="hello-row">
          <div>
            <h1>Hi, <span data-user-handle>@member</span></h1>
            <p>Welcome to your Cellvigor Biorevive LTD backoffice. Signups, packages, earnings, and requests on this page come from your account.</p>
          </div>
          <div class="package-pill">ACCOUNT PACKAGE: <span data-user-package>VISION</span></div>
        </div>
        <div class="ref-bar">
          <label class="visually-hidden" for="referralLink">Referral link</label>
          <input class="form-control" id="referralLink" readonly value="https://example.com/register?ref=ZGV12345">
          <button class="btn" type="button" data-copy-target="referralLink" data-copy-suffix="&amp;pt=left">Left Link</button>
          <button class="btn" type="button" data-copy-target="referralLink" data-copy-suffix="&amp;pt=right">Right Link</button>
        </div>
        <section class="office-cards" aria-label="Summary">
          <article class="point-card left"><span>Remaining Left Points</span><strong id="statLeft">…</strong><small>PV</small></article>
          <article class="point-card right"><span>Remaining Right Points</span><strong id="statRight">…</strong><small>PV</small></article>
          <article class="soft-card"><span>Overall Downlines</span><strong id="statTeam">…</strong><a href="network">Click here for more details</a></article>
        </section>
        <section class="wallet-row">
          <article class="wallet-card"><span>Transaction Wallet</span><strong id="statBalance">…</strong><a href="transactions">Click here for more details</a></article>
          <article class="wallet-card"><span>Bonus Earnings Wallet</span><strong id="statEarnings">…</strong><a href="commissions">Click here for more details</a></article>
          <article class="wallet-card"><span>Unilevel Earnings Wallet</span><strong id="sumIndirect">…</strong><a href="commissions#indirect">Click here for more details</a></article>
        </section>
        <section class="wallet-row">
          <article class="wallet-card"><span>Withdrawable (40%)</span><strong id="statWithdrawable">…</strong><a href="withdrawals">Saturdays only</a></article>
          <article class="wallet-card"><span>Eagle Lands &amp; Properties</span><strong id="statEagleLands">…</strong><a href="eagle-lands">Open Eagle Lands</a></article>
          <article class="wallet-card"><span>Reserve Rule</span><strong id="statReserveRule" style="font-size:1.05rem;">…</strong><a href="eagle-lands">Withdrawal policy</a></article>
        </section>
        <p class="visually-hidden" id="statPv"></p>
        <p class="visually-hidden" id="statDirect"></p>
        <section class="panel-grid mb-3">
          <article class="panel">
            <div class="panel-head"><h2>Network summary</h2></div>
            <div class="summary-pills">
              <div><span>Left Team</span><strong id="teamLeftCount">…</strong></div>
              <div><span>Right Team</span><strong id="teamRightCount">…</strong></div>
              <div><span>Active Members</span><strong id="teamActive">…</strong></div>
              <div><span>Inactive Members</span><strong id="teamInactive">…</strong></div>
            </div>
          </article>
          <article class="panel">
            <div class="panel-head"><h2>Binary tree</h2><a href="network">Open network</a></div>
            <div class="org-scroll" id="memberTree" aria-label="Binary tree"></div>
            <div id="treeDetail" class="mt-3"></div>
          </article>
        </section>
        <section class="office-split mb-3">
          <article class="rank-card">
            <strong>Current package</strong>
            <div>Account package</div>
            <div class="rank-name" data-user-package>Vision</div>
            <div>Left and right PV are the points stored on this account.</div>
          </article>
          <article class="soft-card">
            <span>Direct referrals</span><strong id="sumDirect">…</strong>
            <span>Pairing</span><strong id="sumPairing">…</strong>
            <span>Upgrade</span><strong id="sumUpgrade">…</strong>
          </article>
        </section>
        <section class="panel mb-3">
          <div class="panel-head"><h2>Recent Transactions</h2><a href="commissions">View all</a></div>
          <div class="table-wrap">
            <table class="data">
              <thead><tr><th>Date</th><th>Member</th><th>Commission Type</th><th>Package</th><th>PV</th><th>Amount</th><th>Status</th></tr></thead>
              <tbody id="recentCommissionBody"><tr><td class="loading-row" colspan="7">Loading records…</td></tr></tbody>
            </table>
          </div>
        </section>
        <section class="row g-3">
          <div class="col-lg-4"><article class="panel"><h2>PV Growth</h2><div class="chart-box"><canvas id="pvChart" aria-label="Recorded PV"></canvas></div></article></div>
          <div class="col-lg-4"><article class="panel"><h2>Commission History</h2><div class="chart-box"><canvas id="commissionChart" aria-label="Recorded commissions"></canvas></div></article></div>
          <div class="col-lg-4"><article class="panel"><h2>Team Growth</h2><div class="chart-box"><canvas id="teamChart" aria-label="Team size"></canvas></div></article></div>
        </section>
      </main>
      <footer class="app-footer">Copyright © Cellvigor Biorevive LTD 2026</footer>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/dashboard.js"></script>
</body>
</html>
