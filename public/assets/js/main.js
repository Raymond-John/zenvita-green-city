(function () {
  "use strict";

  var PACKAGES = {
    vision: { key: "vision", name: "Vision", price: 150000, bottles: 8, pv: 150, bonus: 45000, matches: 45, perMatch: 1440, dailyMax: 64800 },
    silver: { key: "silver", name: "Silver", price: 300000, bottles: 16, pv: 300, bonus: 90000, matches: 60, perMatch: 1800, dailyMax: 108000 },
    gold: { key: "gold", name: "Gold", price: 600000, bottles: 32, pv: 600, bonus: 180000, matches: 70, perMatch: 2160, dailyMax: 151200 },
    diamond: { key: "diamond", name: "Diamond", price: 900000, bottles: 48, pv: 900, bonus: 270000, matches: 100, perMatch: 2520, dailyMax: 252000 },
    platinum: { key: "platinum", name: "Platinum", price: 1200000, bottles: 64, pv: 1200, bonus: 360000, matches: 120, perMatch: 2880, dailyMax: 345600 }
  };

  function formatNaira(value) {
    var amount = Number(value) || 0;
    return "\u20A6" + amount.toLocaleString("en-NG", { maximumFractionDigits: 0 });
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function ensureToastStack() {
    var stack = document.getElementById("toastStack");
    if (!stack) {
      stack = document.createElement("div");
      stack.id = "toastStack";
      stack.className = "toast-stack";
      stack.setAttribute("aria-live", "polite");
      document.body.appendChild(stack);
    }
    return stack;
  }

  function showToast(message, variant) {
    var stack = ensureToastStack();
    var toast = document.createElement("div");
    toast.className = "zg-toast" + (variant ? " is-" + variant : "");
    toast.textContent = message;
    stack.appendChild(toast);
    window.setTimeout(function () {
      toast.remove();
    }, 4200);
  }

  function readStore(key, fallback) {
    try {
      var raw = sessionStorage.getItem(key);
      return raw ? JSON.parse(raw) : fallback;
    } catch (error) {
      return fallback;
    }
  }

  function writeStore(key, value) {
    sessionStorage.setItem(key, JSON.stringify(value));
  }

  function setFieldError(input, message) {
    var error = document.getElementById(input.id + "-error");
    input.classList.add("is-invalid");
    input.setAttribute("aria-invalid", "true");
    if (error) {
      error.textContent = message;
    }
  }

  function clearFieldError(input) {
    var error = document.getElementById(input.id + "-error");
    input.classList.remove("is-invalid");
    input.removeAttribute("aria-invalid");
    if (error) {
      error.textContent = "";
    }
  }

  function clearFormErrors(form) {
    form.querySelectorAll("input, select, textarea").forEach(clearFieldError);
  }

  function isEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
  }

  function digitsOnly(value) {
    return String(value || "").replace(/\D/g, "");
  }

  function initHeader() {
    var header = document.querySelector(".site-header");
    if (!header) {
      return;
    }
    var onScroll = function () {
      header.classList.toggle("is-scrolled", window.scrollY > 8);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  function initPasswordToggles() {
    document.querySelectorAll("[data-toggle-password]").forEach(function (button) {
      button.addEventListener("click", function () {
        var input = document.getElementById(button.getAttribute("data-toggle-password"));
        if (!input) {
          return;
        }
        var show = input.type === "password";
        input.type = show ? "text" : "password";
        button.setAttribute("aria-label", show ? "Hide password" : "Show password");
        button.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
      });
    });
  }

  function initPlaceholderLinks() {
    document.querySelectorAll("[data-placeholder]").forEach(function (link) {
      link.addEventListener("click", function (event) {
        event.preventDefault();
        showToast(link.getAttribute("data-placeholder"), "info");
      });
    });
  }

  function initLogout() {
    document.querySelectorAll("[data-logout]").forEach(function (link) {
      link.addEventListener("click", function (event) {
        event.preventDefault();
        var href = link.getAttribute("href") || "login";
        sessionStorage.removeItem("zenvitaDemoSession");
        fetch(link.getAttribute("data-logout-api") || "../api/logout.php", {
          method: "POST",
          credentials: "same-origin"
        }).finally(function () {
          window.location.href = href;
        });
      });
    });
  }

  function closeSidebar() {
    var sidebar = document.getElementById("appSidebar");
    var backdrop = document.getElementById("sidebarBackdrop");
    var toggle = document.getElementById("sidebarToggle");
    if (!sidebar) {
      return;
    }
    sidebar.classList.remove("is-open");
    if (backdrop) {
      backdrop.classList.remove("is-open");
    }
    document.body.classList.remove("sidebar-open");
    if (toggle) {
      toggle.setAttribute("aria-expanded", "false");
    }
  }

  function initSidebar() {
    var sidebar = document.getElementById("appSidebar");
    var toggle = document.getElementById("sidebarToggle");
    var backdrop = document.getElementById("sidebarBackdrop");
    var close = document.getElementById("sidebarClose");
    if (!sidebar || !toggle) {
      return;
    }
    toggle.addEventListener("click", function () {
      var open = !sidebar.classList.contains("is-open");
      sidebar.classList.toggle("is-open", open);
      if (backdrop) {
        backdrop.classList.toggle("is-open", open);
      }
      document.body.classList.toggle("sidebar-open", open);
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    if (backdrop) {
      backdrop.addEventListener("click", closeSidebar);
    }
    if (close) {
      close.addEventListener("click", closeSidebar);
    }
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeSidebar();
      }
    });
    sidebar.querySelectorAll(".sidebar-toggle").forEach(function (button) {
      button.addEventListener("click", function () {
        var group = button.closest(".sidebar-group");
        var open = !group.classList.contains("is-open");
        group.classList.toggle("is-open", open);
        button.setAttribute("aria-expanded", open ? "true" : "false");
      });
    });
    sidebar.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        if (window.innerWidth < 992) {
          closeSidebar();
        }
      });
    });
    window.addEventListener("resize", function () {
      if (window.innerWidth >= 992) {
        closeSidebar();
      }
    });
  }

  function packageFromQuery() {
    var params = new URLSearchParams(window.location.search);
    var key = (params.get("package") || "").toLowerCase();
    return PACKAGES[key] ? key : "";
  }

  function describePackage(key) {
    var item = PACKAGES[key];
    if (!item) {
      return "Select a package to see the stated price, product quantity, PV, and direct referral bonus.";
    }
    return item.name + " · " + formatNaira(item.price) + " · " + item.bottles + " bottles · " + item.pv + " PV · Referral bonus " + formatNaira(item.bonus);
  }

  function initPackageSelection() {
    var select = document.getElementById("package");
    var summary = document.getElementById("packageSummary");
    if (!select) {
      return;
    }
    var preset = packageFromQuery();
    if (preset) {
      select.value = preset;
    }
    var paint = function () {
      document.querySelectorAll("[data-package-choice]").forEach(function (button) {
        button.classList.toggle("is-selected", button.getAttribute("data-package-choice") === select.value);
      });
      if (summary) {
        summary.textContent = describePackage(select.value);
      }
    };
    document.querySelectorAll("[data-package-choice]").forEach(function (button) {
      button.addEventListener("click", function () {
        select.value = button.getAttribute("data-package-choice");
        clearFieldError(select);
        paint();
      });
    });
    select.addEventListener("change", function () {
      clearFieldError(select);
      paint();
    });
    paint();
  }

  function initRegister() {
    var form = document.getElementById("registerForm");
    if (!form) {
      return;
    }
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      clearFormErrors(form);
      var data = {
        referral: form.referral.value.trim(),
        firstName: form.firstName.value.trim(),
        lastName: form.lastName.value.trim(),
        email: form.email.value.trim(),
        phone: form.phone.value.trim(),
        password: form.password.value,
        confirmPassword: form.confirmPassword.value,
        package: form.package.value,
        terms: form.terms.checked
      };
      var valid = true;
      if (data.referral && !/^[a-z0-9]+$/i.test(data.referral)) {
        setFieldError(form.referral, "Use letters and numbers only, or leave this blank.");
        valid = false;
      }
      if (!data.firstName) {
        setFieldError(form.firstName, "Enter your first name.");
        valid = false;
      }
      if (!data.lastName) {
        setFieldError(form.lastName, "Enter your last name.");
        valid = false;
      }
      if (!isEmail(data.email)) {
        setFieldError(form.email, "Enter a valid email address.");
        valid = false;
      }
      if (digitsOnly(data.phone).length < 10) {
        setFieldError(form.phone, "Enter a phone number with at least 10 digits.");
        valid = false;
      }
      if (data.password.length < 8) {
        setFieldError(form.password, "Use at least 8 characters.");
        valid = false;
      }
      if (data.password !== data.confirmPassword) {
        setFieldError(form.confirmPassword, "Passwords do not match.");
        valid = false;
      }
      if (!PACKAGES[data.package]) {
        setFieldError(form.package, "Select a package.");
        valid = false;
      }
      if (!data.terms) {
        setFieldError(form.terms, "Accept the terms to continue.");
        valid = false;
      }
      if (!valid) {
        showToast("Check the highlighted fields.", "error");
        return;
      }
      writeStore("zenvitaDemoSession", {
        firstName: data.firstName,
        lastName: data.lastName,
        email: data.email,
        phone: data.phone,
        package: data.package,
        sponsor: data.referral,
        role: "member"
      });
      showToast("Registration checked on this device. Opening the demonstration dashboard.");
      window.setTimeout(function () {
        window.location.href = "dashboard/index";
      }, 700);
    });
  }

  function initLogin() {
    var form = document.getElementById("loginForm");
    if (!form) {
      return;
    }
    var rememberKey = form.getAttribute("data-remember") || "zenvitaRemember";
    var remembered = localStorage.getItem(rememberKey) || "";
    if (remembered && form.identity) {
      form.identity.value = remembered;
      form.remember.checked = true;
    }
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      clearFormErrors(form);
      var identity = form.identity.value.trim();
      var password = form.password.value;
      var valid = true;
      var phone = digitsOnly(identity);
      if (!(isEmail(identity) || phone.length >= 10 || identity.length >= 3)) {
        setFieldError(form.identity, "Enter your username, email, or phone number.");
        valid = false;
      }
      if (password.length < 8) {
        setFieldError(form.password, "Use at least 8 characters.");
        valid = false;
      }
      if (!valid) {
        showToast("Check the highlighted fields.", "error");
        return;
      }
      if (form.remember.checked) {
        localStorage.setItem(rememberKey, identity);
      } else {
        localStorage.removeItem(rememberKey);
      }
      var role = form.getAttribute("data-role") || "member";
      var endpoint = form.getAttribute("data-api") || "api/login.php";
      var submit = form.querySelector("[type='submit']");
      if (submit) {
        submit.disabled = true;
      }
      fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ identity: identity, password: password, role: role })
      }).then(function (res) {
        return res.json().then(function (data) {
          return data || { ok: false, error: "Sign-in failed." };
        });
      }).then(function (data) {
        if (submit) {
          submit.disabled = false;
        }
        if (!data.ok) {
          showToast(data.error || "Those sign-in details do not match an account.", "error");
          return;
        }
        sessionStorage.removeItem("zenvitaDemoSession");
        window.location.href = form.getAttribute("data-next") || "dashboard/index";
      }).catch(function () {
        if (submit) {
          submit.disabled = false;
        }
        showToast("The sign-in server did not respond. Open this site through XAMPP.", "error");
      });
    });
  }

  function initContact() {
    var form = document.getElementById("contactForm");
    if (!form) {
      return;
    }
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      clearFormErrors(form);
      var valid = true;
      if (!form.name.value.trim()) {
        setFieldError(form.name, "Enter your name.");
        valid = false;
      }
      if (!isEmail(form.email.value.trim())) {
        setFieldError(form.email, "Enter a valid email address.");
        valid = false;
      }
      if (digitsOnly(form.phone.value).length < 10) {
        setFieldError(form.phone, "Enter a phone number with at least 10 digits.");
        valid = false;
      }
      if (!form.subject.value.trim()) {
        setFieldError(form.subject, "Enter a subject.");
        valid = false;
      }
      if (form.message.value.trim().length < 12) {
        setFieldError(form.message, "Enter a message of at least 12 characters.");
        valid = false;
      }
      if (!valid) {
        showToast("Check the highlighted fields.", "error");
        return;
      }
      form.reset();
      var note = document.getElementById("contactResult");
      if (note) {
        note.hidden = false;
      }
      showToast("Message checked locally. It was not sent, because no mail server is connected.", "info");
    });
  }

  function initCopyButtons() {
    document.querySelectorAll("[data-copy-target]").forEach(function (button) {
      button.addEventListener("click", function () {
        var input = document.getElementById(button.getAttribute("data-copy-target"));
        if (!input) {
          return;
        }
        var value = (input.value || input.textContent) + (button.getAttribute("data-copy-suffix") || "");
        var done = function () {
          showToast("Referral link copied.");
        };
        var fallback = function () {
          var area = document.createElement("textarea");
          area.value = value;
          area.setAttribute("readonly", "");
          area.style.position = "fixed";
          area.style.left = "-999px";
          document.body.appendChild(area);
          area.select();
          document.execCommand("copy");
          area.remove();
          done();
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(value).then(done).catch(fallback);
        } else {
          fallback();
        }
      });
    });
  }

  function statusClass(status) {
    return "badge-status is-" + String(status || "").toLowerCase().replace(/\s+/g, "-");
  }

  function humanFace() {
    return '<svg class="human-face" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><circle cx="32" cy="32" r="32" fill="#d9f0df"/><ellipse cx="32" cy="62" rx="26" ry="16" fill="#178a34"/><rect x="27" y="38" width="10" height="12" rx="4" fill="#efc09a"/><ellipse cx="18.5" cy="28" rx="3.2" ry="4.2" fill="#efc09a"/><ellipse cx="45.5" cy="28" rx="3.2" ry="4.2" fill="#efc09a"/><circle cx="32" cy="26" r="14" fill="#f3c7a4"/><path d="M18 27c.6-12 6.4-18 14-18s13.4 6 14 18c-1.4-6.2-6.4-9.2-14-9.2S19.4 20.8 18 27z" fill="#2c211b"/><ellipse cx="26.5" cy="27" rx="2.2" ry="2.5" fill="#241c18"/><ellipse cx="37.5" cy="27" rx="2.2" ry="2.5" fill="#241c18"/><circle cx="27.3" cy="26.2" r="0.75" fill="#fff"/><circle cx="38.3" cy="26.2" r="0.75" fill="#fff"/><path d="M27 33.2c1.6 2.1 3.2 3 5 3s3.4-.9 5-3" fill="none" stroke="#c4725c" stroke-width="1.8" stroke-linecap="round"/></svg>';
  }

  function renderBinaryTree(container, root, onSelect) {
    if (!container || !root) {
      return;
    }
    function branch(person) {
      if (!person) {
        return '<li><div class="tree-empty">Open</div></li>';
      }
      var children = "";
      if (person.left || person.right || person.showEmpty) {
        children = "<ul>" + branch(person.left) + branch(person.right) + "</ul>";
      }
      return '<li><button type="button" class="tree-node' + (person.isYou ? " is-you" : "") + '" data-id="' + escapeHtml(person.id) + '"><span class="person-face" aria-hidden="true">' + humanFace() + '</span><span>' + escapeHtml(person.shortName || person.name) + "</span><small>" + escapeHtml(person.packageName || person.package || "") + "</small></button>" + children + "</li>";
    }
    container.innerHTML = '<ul class="org-tree">' + branch(root) + "</ul>";
    container.querySelectorAll(".tree-node").forEach(function (button) {
      button.addEventListener("click", function () {
        container.querySelectorAll(".tree-node").forEach(function (node) {
          node.classList.remove("is-selected");
        });
        button.classList.add("is-selected");
        if (onSelect) {
          onSelect(button.getAttribute("data-id"));
        }
      });
    });
  }

  function initPairingChart() {
    var canvas = document.getElementById("pairingChart");
    if (!canvas || typeof Chart === "undefined") {
      return;
    }
    var items = [PACKAGES.vision, PACKAGES.silver, PACKAGES.gold, PACKAGES.diamond, PACKAGES.platinum];
    new Chart(canvas, {
      type: "bar",
      data: {
        labels: items.map(function (item) { return item.name; }),
        datasets: [{
          label: "Stated maximum daily pairing payout",
          data: items.map(function (item) { return item.dailyMax; }),
          backgroundColor: ["#1f7a4d", "#0e3b2e", "#8c6e2f", "#b8923a"],
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (context) { return formatNaira(context.parsed.y); }
            }
          }
        },
        scales: {
          y: {
            ticks: {
              callback: function (value) { return "\u20A6" + Number(value).toLocaleString("en-NG"); }
            }
          }
        }
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initHeader();
    initPasswordToggles();
    initPlaceholderLinks();
    initLogout();
    initSidebar();
    initPackageSelection();
    initRegister();
    initLogin();
    initContact();
    initCopyButtons();
    initPairingChart();
  });

  window.Zenvita = {
    packages: PACKAGES,
    formatNaira: formatNaira,
    escapeHtml: escapeHtml,
    showToast: showToast,
    readStore: readStore,
    writeStore: writeStore,
    setFieldError: setFieldError,
    clearFieldError: clearFieldError,
    clearFormErrors: clearFormErrors,
    digitsOnly: digitsOnly,
    statusClass: statusClass,
    renderBinaryTree: renderBinaryTree,
    humanFace: humanFace
  };
})();
