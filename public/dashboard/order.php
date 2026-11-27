<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Order Products · Cellvigor Biorevive LTD</title>
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
        <a class="sidebar-link is-active" aria-current="page" href="order"><i class="bi bi-cart3"></i> Order products</a>
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
        <div class="topbar-title"><strong>Order products</strong><small>Delivery or pickup</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar" data-user-avatar>AO</span><span class="profile-meta"><strong data-user-name>Member</strong><small data-user-award-rank>Member</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile">Profile</a></li><li><a class="dropdown-item" href="../login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head"><div><h1>Order and pay for products</h1><p>Choose a product, pick a store, and pay with Paystack. The store is notified after payment succeeds.</p></div></div>
        <div class="alert alert-success" id="orderPaySuccess" hidden role="status"></div>
        <div class="alert alert-danger" id="orderPayFailed" hidden role="alert"></div>
        <section class="row g-3">
          <div class="col-lg-6">
            <form class="panel" id="productOrderForm" novalidate>
              <h2>Order details</h2>
              <div class="mb-3">
                <label class="form-label" for="orderProduct">Product</label>
                <select class="form-select" id="orderProduct" name="product" required></select>
                <p class="field-error" id="orderProduct-error"></p>
                <div class="d-flex align-items-center gap-3 mt-2">
                  <img id="orderProductImage" src="" alt="" class="product-image-preview" hidden>
                  <p class="text-muted small mb-0" id="orderProductMeta"></p>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="orderQuantity">Quantity</label>
                <input class="form-control" id="orderQuantity" name="quantity" type="number" min="1" value="1" required>
                <p class="text-muted small mb-0 mt-1" id="orderTotalMeta">Total: —</p>
              </div>
              <div class="mb-3">
                <label class="form-label" for="orderStore">Store</label>
                <select class="form-select" id="orderStore" name="storeId" required></select>
                <p class="field-error" id="orderStore-error"></p>
                <p class="text-muted small mb-0" id="orderStoreMeta"></p>
              </div>
              <fieldset class="mb-3">
                <legend class="form-label">Fulfillment</legend>
                <div class="side-selector">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="fulfillment" id="fulfillHome" value="home_delivery" checked>
                    <label class="form-check-label fw-bold" for="fulfillHome">Home delivery</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="fulfillment" id="fulfillPickup" value="pickup">
                    <label class="form-check-label fw-bold" for="fulfillPickup">Come and pick up</label>
                  </div>
                </div>
              </fieldset>
              <div class="mb-3"><label class="form-label" for="orderFullName">Full name</label><input class="form-control" id="orderFullName" name="fullName" required><p class="field-error" id="orderFullName-error"></p></div>
              <div class="mb-3"><label class="form-label" for="orderPhone">Phone</label><input class="form-control" id="orderPhone" name="phone" type="tel" required><p class="field-error" id="orderPhone-error"></p></div>
              <div class="mb-3"><label class="form-label" for="orderEmail">Email (for Paystack)</label><input class="form-control" id="orderEmail" name="email" type="email" required><p class="field-error" id="orderEmail-error"></p></div>
              <div id="homeDeliveryFields">
                <div class="mb-3"><label class="form-label" for="orderAddress">Delivery address</label><input class="form-control" id="orderAddress" name="deliveryAddress"><p class="field-error" id="orderAddress-error"></p></div>
                <div class="mb-3"><label class="form-label" for="orderCity">City</label><input class="form-control" id="orderCity" name="deliveryCity"><p class="field-error" id="orderCity-error"></p></div>
                <div class="mb-3"><label class="form-label" for="orderNotes">Delivery notes</label><textarea class="form-control" id="orderNotes" name="deliveryNotes" rows="2"></textarea></div>
              </div>
              <div id="pickupFields" hidden>
                <div class="mb-3"><label class="form-label" for="orderPickup">Preferred pickup time</label><input class="form-control" id="orderPickup" name="preferredPickupAt" placeholder="e.g. Tomorrow 2pm"></div>
                <div class="mb-3"><label class="form-label" for="orderPickupNotes">Pickup notes</label><textarea class="form-control" id="orderPickupNotes" name="deliveryNotesPickup" rows="2"></textarea></div>
              </div>
              <button class="btn btn-brand w-100" type="submit" id="orderSubmitBtn">Pay with Paystack</button>
              <p class="text-muted small mt-2 mb-0">You will be redirected to Paystack to complete payment for this order.</p>
            </form>
          </div>
          <div class="col-lg-6">
            <section class="panel">
              <h2>Your orders</h2>
              <div class="table-wrap">
                <table class="data">
                  <thead><tr><th>Date</th><th>Reference</th><th>Product</th><th>Store</th><th>Payment</th><th>Status</th><th></th></tr></thead>
                  <tbody id="memberOrderBody"><tr><td class="loading-row" colspan="7">Loading…</td></tr></tbody>
                </table>
              </div>
              <div class="empty-state" id="memberOrderEmpty" hidden>No product orders yet.</div>
            </section>
          </div>
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
