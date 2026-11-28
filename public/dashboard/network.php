<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>My Network · Cellvigor Biorevive LTD</title>
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
            <a class="sidebar-link is-active" aria-current="page" href="network"><i class="bi bi-diagram-2"></i> Network</a>
            <a class="sidebar-link" href="register-user"><i class="bi bi-person-plus"></i> Register user</a>
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
        <div class="topbar-title"><strong>My Network</strong><small>Your team</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar" data-user-avatar>AO</span><span class="profile-meta"><strong data-user-name>Member</strong><small data-user-award-rank>Member</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile">Profile</a></li><li><a class="dropdown-item" href="../login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head"><div><h1>My Network</h1><p>Search the stored team, then filter the left team, right team, and generation.</p></div></div>
        <section class="summary-pills mb-3">
          <div><span>Left Team</span><strong id="teamLeftCount">…</strong></div>
          <div><span>Right Team</span><strong id="teamRightCount">…</strong></div>
          <div><span>Active Members</span><strong id="teamActive">…</strong></div>
          <div><span>Inactive Members</span><strong id="teamInactive">…</strong></div>
        </section>
        <section class="panel mb-3">
          <div class="panel-head">
            <h2>Binary tree</h2>
            <div><label class="visually-hidden" for="treeSearch">Search members</label><input class="form-control" id="treeSearch" type="search" placeholder="Search members"></div>
          </div>
          <div class="org-scroll" id="memberTree"></div>
          <div id="treeDetail" class="mt-3"></div>
        </section>
        <section class="panel">
          <div class="toolbar">
            <label class="visually-hidden" for="memberSearch">Search member cards</label>
            <input class="form-control" id="memberSearch" type="search" placeholder="Search name or ID">
            <label class="visually-hidden" for="sideFilter">Team side</label>
            <select class="form-select" id="sideFilter"><option value="">Left and right</option><option value="left">Left team</option><option value="right">Right team</option></select>
            <label class="visually-hidden" for="statusFilter">Status</label>
            <select class="form-select" id="statusFilter"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
          </div>
          <div class="chip-row mb-3" id="genFilters">
            <button class="chip is-active" type="button" data-gen="all">All levels</button>
            <button class="chip" type="button" data-gen="1">Generation 1</button>
            <button class="chip" type="button" data-gen="2">Generation 2</button>
            <button class="chip" type="button" data-gen="3">Generation 3</button>
          </div>
          <div class="member-grid" id="memberGrid"></div>
          <div class="empty-state" id="memberEmpty" hidden>No members match this search.</div>
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
