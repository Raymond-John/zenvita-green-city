<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Emails · Portal · Cellvigor Biorevive LTD</title>
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
        <a class="sidebar-link" href="withdrawals"><i class="bi bi-bank"></i> Withdrawals</a>
        <a class="sidebar-link" href="transactions"><i class="bi bi-receipt"></i> Transactions</a>
        <a class="sidebar-link is-active" aria-current="page" href="emails"><i class="bi bi-envelope"></i> Emails</a>
        <a class="sidebar-link" href="settings"><i class="bi bi-gear"></i> Settings</a>
      </nav>
      <div class="sidebar-foot"><a class="sidebar-link" href="login" data-logout><i class="bi bi-box-arrow-left"></i> Logout</a></div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
      <header class="app-topbar">
        <button class="icon-btn menu-toggle" id="sidebarToggle" type="button" aria-expanded="false" aria-controls="appSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><strong>Emails</strong><small>Official member mail</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar">AD</span><span class="profile-meta"><strong>Admin</strong><small>PORTAL</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="settings">Settings</a></li><li><a class="dropdown-item" href="login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head">
          <div>
            <h1>Send email</h1>
            <p>Choose a template, edit the message, and send an official CELLVIGOR branded email to members.</p>
          </div>
        </div>

        <div id="emailSmtpBanner" class="alert alert-warning" hidden>
          SMTP is not configured. Open <a href="settings">Settings</a> and save Hostinger mailbox details before sending.
        </div>

        <div class="row g-3">
          <div class="col-lg-7">
            <section class="panel">
              <div class="panel-head"><h2>Compose</h2></div>
              <form id="adminEmailForm" novalidate>
                <div class="mb-3">
                  <label class="form-label" for="emailTemplate">Template</label>
                  <select class="form-select" id="emailTemplate" name="template"></select>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="emailAudience">Recipients</label>
                  <select class="form-select" id="emailAudience" name="audience">
                    <option value="selected">Selected members</option>
                    <option value="all_active">All active members with email</option>
                  </select>
                </div>
                <div class="mb-3" id="emailMemberWrap">
                  <label class="form-label" for="emailMemberSearch">Find members</label>
                  <input class="form-control mb-2" id="emailMemberSearch" type="search" placeholder="Search name, username, email, or member code" autocomplete="off">
                  <select class="form-select" id="emailMembers" name="userIds" multiple size="8" aria-label="Select members"></select>
                  <p class="form-text mb-0">Hold Ctrl (Windows) or Cmd (Mac) to select multiple. Placeholders like <code>{{full_name}}</code> are filled per recipient.</p>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="emailSubject">Subject</label>
                  <input class="form-control" id="emailSubject" name="subject" maxlength="180" required>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="emailTitle">Headline inside email</label>
                  <input class="form-control" id="emailTitle" name="title" maxlength="120" placeholder="Defaults to subject if left blank">
                </div>
                <div class="mb-3">
                  <label class="form-label" for="emailBody">Message</label>
                  <textarea class="form-control" id="emailBody" name="body" rows="12" required></textarea>
                  <p class="form-text mb-0" id="emailPlaceholderHelp"></p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <button class="btn btn-brand" type="submit" id="emailSendBtn">Send email</button>
                  <button class="btn btn-outline-brand" type="button" id="emailPreviewBtn">Preview</button>
                </div>
                <p class="field-error mt-2 mb-0" id="emailFormError"></p>
                <p class="text-success mt-2 mb-0" id="emailFormSuccess" hidden></p>
              </form>
            </section>
          </div>
          <div class="col-lg-5">
            <section class="panel mb-3">
              <div class="panel-head"><h2>Preview</h2></div>
              <div id="emailPreview" class="email-preview-box">
                <p class="mb-0 text-muted">Select a template and click Preview to see the branded layout.</p>
              </div>
            </section>
            <section class="panel">
              <div class="panel-head"><h2>Recent sends</h2></div>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th></tr></thead>
                  <tbody id="emailRecentBody"><tr><td colspan="4">Loading…</td></tr></tbody>
                </table>
              </div>
            </section>
          </div>
        </div>
      </main>
      <footer class="app-footer">Cellvigor Biorevive LTD</footer>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>
