<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Login · Cellvigor Biorevive LTD</title>
  <meta name="description" content="Sign in to Cellvigor Biorevive LTD.">
  <link rel="icon" href="assets/images/cellvigor-logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,560;0,9..144,640;1,9..144,560&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
  <a class="skip-link" href="#main">Skip to content</a>
  <div class="login-leaves" id="loginLeaves" aria-hidden="true"></div>
  <main id="main" class="login-screen">
    <form class="login-card" id="loginForm" data-api="api/login.php" novalidate>
      <img class="login-mark" src="assets/images/cellvigor-logo.png" alt="">
      <h1>Member Sign-In</h1>
      <p>Enter username and password</p>
      <div class="login-field">
        <label class="visually-hidden" for="identity">Username</label>
        <input class="form-control" id="identity" name="identity" placeholder="Enter username..." autocomplete="username" required>
        <p class="field-error" id="identity-error"></p>
      </div>
      <div class="login-field">
        <label class="visually-hidden" for="password">Password</label>
        <div class="password-field">
          <input class="form-control" id="password" name="password" type="password" placeholder="Enter Password..." autocomplete="current-password" required>
          <button class="password-toggle" type="button" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
        </div>
        <p class="field-error" id="password-error"></p>
      </div>
      <div class="login-row">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="remember" name="remember">
          <label class="form-check-label" for="remember">Save details</label>
        </div>
        <button class="login-reset" type="button" data-bs-toggle="modal" data-bs-target="#forgotModal">Reset Password?</button>
      </div>
      <button class="login-submit" type="submit">LOGIN</button>
      <p class="mt-3 mb-0 text-center" style="font-size:0.92rem;color:#314033;">Don't have an account? <a href="register" style="color:#006d03;font-weight:700;text-decoration:none;">Register here</a></p>
    </form>
  </main>
  <div class="modal fade" id="forgotModal" tabindex="-1" aria-labelledby="forgotTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h2 class="modal-title h5" id="forgotTitle">Forgot password</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p class="mb-0">Password reset is not connected. This screen cannot send an email or change a password.</p></div>
        <div class="modal-footer"><button class="btn btn-brand" type="button" data-bs-dismiss="modal">Close</button></div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    (function () {
      var box = document.getElementById("loginLeaves");
      if (!box || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
      for (var i = 0; i < 8; i += 1) {
        var leaf = document.createElement("img");
        leaf.src = "assets/images/leaf.png";
        leaf.alt = "";
        leaf.className = "login-leaf";
        leaf.style.left = Math.random() * 100 + "vw";
        leaf.style.animationDuration = (7 + Math.random() * 7) + "s";
        leaf.style.animationDelay = (-Math.random() * 8) + "s";
        leaf.style.width = (22 + Math.random() * 26) + "px";
        box.appendChild(leaf);
      }
    })();
  </script>
</body>
</html>
