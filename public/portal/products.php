<?php
require_once __DIR__ . '/../api/bootstrap.php';
ensure_product_landing_columns();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Products · Portal · Cellvigor Biorevive LTD</title>
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
        <a class="sidebar-link is-active" aria-current="page" href="products"><i class="bi bi-capsule"></i> Products</a>
        <a class="sidebar-link" href="stores"><i class="bi bi-shop"></i> Stores</a>
        <a class="sidebar-link" href="orders"><i class="bi bi-bag-check"></i> Orders</a>
        <a class="sidebar-link" href="payments"><i class="bi bi-credit-card"></i> Payments</a>
        <a class="sidebar-link" href="commissions"><i class="bi bi-cash-stack"></i> Commissions</a>
        <a class="sidebar-link" href="withdrawals"><i class="bi bi-bank"></i> Withdrawals</a>
        <a class="sidebar-link" href="transactions"><i class="bi bi-receipt"></i> Transactions</a>
        <a class="sidebar-link" href="settings"><i class="bi bi-gear"></i> Settings</a>
      </nav>
      <div class="sidebar-foot"><a class="sidebar-link" href="login" data-logout><i class="bi bi-box-arrow-left"></i> Logout</a></div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="app-main">
      <header class="app-topbar">
        <button class="icon-btn menu-toggle" id="sidebarToggle" type="button" aria-expanded="false" aria-controls="appSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
        <div class="topbar-title"><strong>Products</strong><small>Homepage catalogue</small></div>
        <div class="dropdown"><button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications"><i class="bi bi-bell"></i><span class="notify-dot" id="notifyDot"></span></button><div class="dropdown-menu dropdown-menu-end notify-menu" id="notifyMenu"></div></div>
        <div class="dropdown"><button class="profile-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar">AD</span><span class="profile-meta"><strong>Admin</strong><small>PORTAL</small></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="settings">Settings</a></li><li><a class="dropdown-item" href="login" data-logout>Logout</a></li></ul></div>
      </header>
      <main class="app-content" id="main">
        <div class="page-head">
          <div>
            <h1>Products &amp; homepage features</h1>
            <p>Manage products shown on the public index page, including each product’s feature list. Also add or edit homepage benefit features.</p>
          </div>
          <button class="btn btn-brand" type="button" id="addProductBtn">Add product</button>
        </div>

        <section class="panel mb-3">
          <div class="panel-head"><h2>Products</h2></div>
          <div class="table-wrap">
            <table class="data">
              <thead>
                <tr>
                  <th>Image</th>
                  <th>Name</th>
                  <th>Price</th>
                  <th>Features</th>
                  <th>On homepage</th>
                  <th>Status</th>
                  <th>Order</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="adminProductBody"><tr><td class="loading-row" colspan="8">Loading products…</td></tr></tbody>
            </table>
          </div>
        </section>

        <section class="panel">
          <div class="panel-head d-flex justify-content-between align-items-center gap-2">
            <h2 class="mb-0">Homepage features</h2>
            <button class="btn btn-soft btn-sm" type="button" id="addLandingFeatureBtn">Add feature</button>
          </div>
          <p class="text-muted small">These appear in the “Why people choose Cellvigor” section on the index page.</p>
          <div class="table-wrap">
            <table class="data">
              <thead>
                <tr>
                  <th>Feature</th>
                  <th>Order</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="adminLandingFeatureBody"><tr><td class="loading-row" colspan="4">Loading features…</td></tr></tbody>
            </table>
          </div>
        </section>
      </main>
      <footer class="app-footer">Cellvigor Biorevive LTD</footer>
    </div>
  </div>

  <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <form class="modal-content" id="productForm">
        <div class="modal-header">
          <h2 class="modal-title h5" id="productTitle">Edit product</h2>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="">
          <div class="mb-3">
            <label class="form-label" for="productName">Product name</label>
            <input class="form-control" id="productName" name="name" maxlength="120" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="productDescription">Description</label>
            <textarea class="form-control" id="productDescription" name="description" rows="3" maxlength="4000" placeholder="Shown on the homepage product card"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label" for="productImage">Product image</label>
            <input class="form-control" id="productImage" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
            <p class="text-muted small mb-2">Upload from this portal. JPG, PNG, WEBP, or GIF up to 3&nbsp;MB.</p>
            <div class="d-flex align-items-center gap-3">
              <img id="productImagePreview" src="" alt="" class="product-image-preview" hidden>
              <div class="form-check" id="productRemoveImageWrap" hidden>
                <input class="form-check-input" type="checkbox" id="productRemoveImage" name="removeImage">
                <label class="form-check-label" for="productRemoveImage">Remove current image</label>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="productFeatures">Features (one per line)</label>
            <textarea class="form-control" id="productFeatures" name="features" rows="5" placeholder="Boosts immunity&#10;Enhances energy"></textarea>
            <p class="text-muted small mb-0">Add or edit product features here. Each line becomes one feature on the homepage.</p>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="productPrice">Price (₦)</label>
              <input class="form-control" id="productPrice" name="price" type="number" min="0" step="0.01" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="productUnit">Unit label</label>
              <input class="form-control" id="productUnit" name="unitLabel" maxlength="40" value="unit">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="productSort">Sort order</label>
              <input class="form-control" id="productSort" name="sortOrder" type="number" step="1" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="productStatus">Status</label>
              <select class="form-select" id="productStatus" name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="productShowLanding" name="showOnLanding" checked>
                <label class="form-check-label" for="productShowLanding">Show on homepage</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-brand" type="submit" id="productSaveBtn">Save</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="landingFeatureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" id="landingFeatureForm">
        <div class="modal-header">
          <h2 class="modal-title h5" id="landingFeatureTitle">Add feature</h2>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="">
          <div class="mb-3">
            <label class="form-label" for="landingFeatureText">Feature text</label>
            <input class="form-control" id="landingFeatureText" name="title" maxlength="160" required>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="landingFeatureSort">Sort order</label>
              <input class="form-control" id="landingFeatureSort" name="sortOrder" type="number" step="1" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="landingFeatureStatus">Status</label>
              <select class="form-select" id="landingFeatureStatus" name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-soft" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-brand" type="submit">Save feature</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="confirmTitle">Confirm</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><p id="confirmText"></p></div>
      <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-brand" id="confirmYes">Confirm</button></div>
    </div></div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>
