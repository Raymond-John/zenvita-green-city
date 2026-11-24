<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Settings · Portal · Cellvigor Biorevive LTD</title>
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
        <a class="sidebar-link is-active" aria-current="page" href="settings"><i class="bi bi-gear"></i> Settings</a>
      </nav>
      <div class="sidebar-foot"><a class="sidebar-link" href="login" data-logout><i class="bi bi-box-arrow-left"></i> Logout</a></div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
      <header class="app-topbar">
        <button class="icon-btn menu-toggle" id="sidebarToggle" type="button" aria-expanded="false" aria-controls="appSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><strong>Settings</strong><small>Stored locally</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar">AD</span><span class="profile-meta"><strong>Admin</strong><small>PORTAL</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="settings">Settings</a></li><li><a class="dropdown-item" href="login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head"><div><h1>Settings</h1><p>General, commission, network, payment, and notification preferences.</p></div></div>
        <div class="settings-grid">
          <div class="settings-nav" role="tablist">
            <button class="tab is-active" type="button" data-settings-tab="general">General Settings</button>
            <button class="tab" type="button" data-settings-tab="commission">Commission Settings</button>
            <button class="tab" type="button" data-settings-tab="network">Network Settings</button>
            <button class="tab" type="button" data-settings-tab="payment">Payment Settings</button>
            <button class="tab" type="button" data-settings-tab="smtp">Email / SMTP</button>
            <button class="tab" type="button" data-settings-tab="notification">Notification Settings</button>
          </div>
          <form id="settingsForm">
            <section class="panel" data-settings-panel="general">
              <h2>General Settings</h2>
              <div class="mb-3"><label class="form-label" for="siteName">Site name</label><input class="form-control" id="siteName" name="siteName"></div>
              <div class="mb-3"><label class="form-label" for="currency">Currency</label><select class="form-select" id="currency" name="currency"><option value="NGN">NGN</option></select></div>
              <div><label class="form-label" for="supportEmail">Support email</label><input class="form-control" id="supportEmail" name="supportEmail" type="email" placeholder="Leave blank until an official address exists"></div>
            </section>
            <section class="panel" data-settings-panel="commission" hidden>
              <h2>Commission Settings</h2>
              <div class="mb-3"><label class="form-label" for="directRate">Direct referral rate (%)</label><input class="form-control" id="directRate" name="directRate" type="number" min="0" step="0.1"></div>
              <p>Indirect generations in the published plan: 2%, 1%, 1%, 0.5%, 0.5%. Combined indirect total: 5%.</p>
            </section>
            <section class="panel" data-settings-panel="network" hidden>
              <h2>Network Settings</h2>
              <div class="mb-3"><label class="form-label" for="pairPv">PV required for one pair</label><input class="form-control" id="pairPv" name="pairPv" type="number" min="1"></div>
              <div class="switch-row mb-3">
                <div>
                  <strong>Require 1 Left + 1 Right Direct Referral Qualification</strong>
                  <p class="mb-0">When enabled, binary pairing matches are only credited to members who have personally sponsored at least 1 active member on their left leg and 1 on their right leg.</p>
                </div>
                <input class="form-check-input" id="binaryQualification" name="binaryQualification" type="checkbox">
              </div>
              <p class="mb-0 text-muted small">Standard published compensation plan requirement: 1 direct active partner on left team and 1 on right team.</p>
            </section>
            <section class="panel" data-settings-panel="payment" hidden>
              <h2>Payment Settings</h2>
              <div class="mb-3">
                <label class="form-label" for="minWithdrawal">Minimum withdrawal amount</label>
                <input class="form-control" id="minWithdrawal" name="minWithdrawal" type="number" min="0">
                <p class="mt-2 mb-0 text-muted small">Minimum amount a member is required to request when withdrawing from their wallet balance.</p>
              </div>
              <hr>
              <h3 class="h6">Store sales bonus</h3>
              <p class="text-muted small">When a store’s <strong>completed</strong> sales reach the target (default ₦3,000,000), the store owner is credited the bonus you set. The bonus repeats for every additional target reached (₦3M, ₦6M, …).</p>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="storeSalesTarget">Sales target (₦)</label>
                  <input class="form-control" id="storeSalesTarget" name="storeSalesTarget" type="number" min="1" step="1" value="3000000">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="storeSalesBonus">Bonus amount (₦)</label>
                  <input class="form-control" id="storeSalesBonus" name="storeSalesBonus" type="number" min="0" step="1" value="0">
                </div>
              </div>
              <p class="mt-2 mb-0 text-muted small">Set bonus to 0 to disable payouts until you configure it.</p>
            </section>
            <section class="panel" data-settings-panel="smtp" hidden>
              <h2>Email / SMTP · Hostinger</h2>
              <p class="text-muted small mb-3">Uses <strong>Hostinger webmail</strong> SMTP for wallet funding and login alerts. Create the mailbox in hPanel → Emails, then enter it below. Leave password blank to keep the current one.</p>
              <div class="alert alert-light border small mb-3" role="note">
                <strong>Hostinger settings</strong><br>
                Host: <code>smtp.hostinger.com</code><br>
                Recommended: port <code>465</code> + <code>SSL</code><br>
                Alternative: port <code>587</code> + <code>TLS</code><br>
                Username &amp; From email: full address (e.g. <code>noreply@yourdomain.com</code>)
              </div>
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label" for="smtpHost">SMTP host</label>
                  <input class="form-control" id="smtpHost" name="smtpHost" placeholder="smtp.hostinger.com" value="smtp.hostinger.com">
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="smtpPort">Port</label>
                  <input class="form-control" id="smtpPort" name="smtpPort" type="number" min="1" value="465">
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="smtpEncryption">Encryption</label>
                  <select class="form-select" id="smtpEncryption" name="smtpEncryption">
                    <option value="ssl" selected>SSL (Hostinger 465)</option>
                    <option value="tls">TLS (Hostinger 587)</option>
                    <option value="none">None</option>
                  </select>
                </div>
                <div class="col-md-8">
                  <label class="form-label" for="smtpUsername">Username (full email)</label>
                  <input class="form-control" id="smtpUsername" name="smtpUsername" placeholder="noreply@yourdomain.com" autocomplete="off">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="smtpPassword">Mailbox password</label>
                  <input class="form-control" id="smtpPassword" name="smtpPassword" type="password" placeholder="Leave blank to keep current" autocomplete="new-password">
                  <p class="text-muted small mb-0 mt-1" id="smtpPasswordHint" hidden>A password is already saved.</p>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="smtpFromEmail">From email</label>
                  <input class="form-control" id="smtpFromEmail" name="smtpFromEmail" type="email" placeholder="noreply@yourdomain.com">
                </div>
                <div class="col-md-12">
                  <label class="form-label" for="smtpFromName">From name</label>
                  <input class="form-control" id="smtpFromName" name="smtpFromName" placeholder="Cellvigor Biorevive LTD">
                </div>
              </div>
            </section>
            <section class="panel" data-settings-panel="notification" hidden>
              <h2>Notification Settings</h2>
              <div class="switch-row"><div><strong>Email alerts</strong><p class="mb-0">When SMTP is configured, wallet funding and login alerts are emailed to members and admins.</p></div><input class="form-check-input" id="emailAlerts" name="emailAlerts" type="checkbox"></div>
              <div class="switch-row"><div><strong>SMS alerts</strong><p class="mb-0">Preference only. No SMS is sent.</p></div><input class="form-check-input" id="smsAlerts" name="smsAlerts" type="checkbox"></div>
            </section>
            <button class="btn btn-brand mt-3" type="submit">Save settings</button>
          </form>
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
