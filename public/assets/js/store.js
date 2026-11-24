(function () {
  "use strict";

  var Z = window.Zenvita;
  if (!Z) {
    return;
  }

  var office = {
    store: null,
    owner: null,
    stats: {},
    orders: [],
    notifications: []
  };

  function postJson(url, body) {
    return fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body)
    }).then(function (res) {
      return res.json();
    });
  }

  function statusActions(order) {
    var buttons = [];
    if (order.status === "Pending") {
      buttons.push("<button class='btn btn-approve btn-sm' data-status='Accepted' data-id='" + order.id + "'>Accept</button>");
      buttons.push("<button class='btn btn-reject btn-sm' data-status='Cancelled' data-id='" + order.id + "'>Cancel</button>");
    } else if (order.status === "Accepted") {
      buttons.push("<button class='btn btn-approve btn-sm' data-status='Ready' data-id='" + order.id + "'>Mark ready</button>");
      buttons.push("<button class='btn btn-reject btn-sm' data-status='Cancelled' data-id='" + order.id + "'>Cancel</button>");
    } else if (order.status === "Ready") {
      buttons.push("<button class='btn btn-approve btn-sm' data-status='Completed' data-id='" + order.id + "'>Complete</button>");
    }
    buttons.push("<button class='btn btn-soft btn-sm' data-view='" + order.id + "'>View</button>");
    return "<div class='action-row'>" + buttons.join("") + "</div>";
  }

  function detailHtml(order) {
    var lines = [
      ["Reference", order.reference],
      ["Member", order.member + (order.memberCode ? " (" + order.memberCode + ")" : "")],
      ["Product", order.product || order.package],
      ["Quantity", String(order.quantity)],
      ["Fulfillment", order.fulfillmentLabel],
      ["Phone", order.phone || "—"],
      ["Email", order.email || "—"],
      ["Status", order.status],
      ["Date", order.date]
    ];
    if (order.fulfillment === "home_delivery") {
      lines.push(["Address", order.deliveryAddress || "—"]);
      lines.push(["City", order.deliveryCity || "—"]);
      lines.push(["Notes", order.deliveryNotes || "—"]);
    } else {
      lines.push(["Preferred pickup", order.preferredPickupAt || "—"]);
      lines.push(["Notes", order.deliveryNotes || "—"]);
    }
    return "<dl class='detail-list'>" + lines.map(function (pair) {
      return "<div><dt>" + Z.escapeHtml(pair[0]) + "</dt><dd>" + Z.escapeHtml(pair[1]) + "</dd></div>";
    }).join("") + "</dl>";
  }

  function initShell() {
    var storeCode = (office.owner && office.owner.storeCode) || "";
    if (office.owner) {
      var nameEl = document.getElementById("storeOwnerName");
      var avatar = document.getElementById("storeAvatar");
      var roleEl = document.getElementById("storeOwnerId");
      if (nameEl) {
        nameEl.textContent = office.owner.name || "Store";
      }
      if (roleEl) {
        roleEl.textContent = storeCode ? "ID " + storeCode : "Store owner";
      }
      if (avatar) {
        avatar.innerHTML = Z.humanFace();
      }
    }
    if (office.store) {
      var title = document.getElementById("storeTitle");
      var heading = document.getElementById("storeHeading");
      var intro = document.getElementById("storeIntro");
      var sub = document.getElementById("storeSubtitle");
      if (title) {
        title.textContent = office.store.name;
      }
      if (heading) {
        heading.textContent = office.store.name;
      }
      if (sub) {
        sub.textContent = [storeCode, office.store.city || "Store", office.store.status].filter(Boolean).join(" · ");
      }
      if (intro) {
        var location = [office.store.address, office.store.city, office.store.phone].filter(Boolean).join(" · ");
        intro.textContent = storeCode
          ? ("Store ID " + storeCode + (location ? " · " + location : ""))
          : (location || "Store bookings");
      }
    }

    var menu = document.getElementById("notifyMenu");
    var dot = document.getElementById("notifyDot");
    if (!menu) {
      return;
    }
    var unread = office.stats.unread || 0;
    if (dot) {
      dot.classList.toggle("is-hidden", unread < 1);
    }
    var items = (office.notifications || []).slice(0, 5).map(function (note) {
      return '<div class="notify-item"><strong>' + Z.escapeHtml(note.reference) + "</strong><p>" + Z.escapeHtml(note.text) + "</p><small>" + Z.escapeHtml(note.date) + "</small></div>";
    }).join("");
    if (!items) {
      items = '<div class="notify-item"><strong>No bookings yet</strong><p>New member orders will appear here.</p></div>';
    }
    menu.innerHTML = items + "<button type='button' class='btn btn-soft btn-sm w-100' id='markStoreRead'>Mark as read</button>";
    var mark = document.getElementById("markStoreRead");
    if (mark) {
      mark.addEventListener("click", function () {
        postJson("../api/store-order.php", { action: "mark_read" }).then(function () {
          if (dot) {
            dot.classList.add("is-hidden");
          }
          Z.showToast("Notifications marked as read.");
        }).catch(function () {
          Z.showToast("Could not update notifications.", "error");
        });
      });
    }
  }

  function initDashboard() {
    var body = document.getElementById("storeRecentBody");
    if (!body) {
      return;
    }
    document.getElementById("statPending").textContent = String(office.stats.pending || 0);
    document.getElementById("statAccepted").textContent = String(office.stats.accepted || 0);
    document.getElementById("statReady").textContent = String(office.stats.ready || 0);
    document.getElementById("statCompleted").textContent = String(office.stats.completed || 0);
    var rows = (office.orders || []).slice(0, 8);
    if (!rows.length) {
      body.innerHTML = '<tr><td class="empty-state" colspan="6">No bookings yet.</td></tr>';
      return;
    }
    body.innerHTML = rows.map(function (order) {
      return "<tr><td>" + Z.escapeHtml(order.date) + "</td><td>" + Z.escapeHtml(order.reference) + "</td><td>" + Z.escapeHtml(order.member) + "</td><td>" + Z.escapeHtml(order.package) + "</td><td>" + Z.escapeHtml(order.fulfillmentLabel) + '</td><td><span class="' + Z.statusClass(order.status) + '">' + Z.escapeHtml(order.status) + "</span></td></tr>";
    }).join("");
  }

  function initOrders() {
    var body = document.getElementById("storeOrderBody");
    if (!body) {
      return;
    }
    var search = document.getElementById("storeOrderSearch");
    var status = document.getElementById("storeOrderStatus");
    var empty = document.getElementById("storeOrderEmpty");

    function filtered() {
      var q = (search.value || "").toLowerCase().trim();
      var st = status.value;
      return (office.orders || []).filter(function (order) {
        var text = (order.reference + " " + order.member + " " + order.package).toLowerCase();
        return (!q || text.indexOf(q) !== -1) && (!st || order.status === st);
      });
    }

    function paint() {
      var rows = filtered();
      empty.hidden = rows.length > 0;
      if (!rows.length) {
        body.innerHTML = "";
        return;
      }
      body.innerHTML = rows.map(function (order) {
        var details = order.fulfillment === "home_delivery"
          ? Z.escapeHtml([order.deliveryAddress, order.deliveryCity].filter(Boolean).join(", ") || "—")
          : Z.escapeHtml(order.preferredPickupAt || "Pickup");
        return "<tr><td>" + Z.escapeHtml(order.date) + "</td><td>" + Z.escapeHtml(order.reference) + "</td><td>" + Z.escapeHtml(order.member) + "</td><td>" + Z.escapeHtml(order.package) + "</td><td>" + order.quantity + "</td><td>" + Z.escapeHtml(order.fulfillmentLabel) + "</td><td>" + details + '</td><td><span class="' + Z.statusClass(order.status) + '">' + Z.escapeHtml(order.status) + "</span></td><td>" + statusActions(order) + "</td></tr>";
      }).join("");
    }

    body.addEventListener("click", function (event) {
      var viewBtn = event.target.closest("[data-view]");
      if (viewBtn) {
        var order = office.orders.filter(function (row) { return String(row.id) === viewBtn.getAttribute("data-view"); })[0];
        if (!order) {
          return;
        }
        document.getElementById("storeOrderTitle").textContent = order.reference;
        document.getElementById("storeOrderBodyDetail").innerHTML = detailHtml(order);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("storeOrderModal")).show();
        return;
      }
      var statusBtn = event.target.closest("[data-status]");
      if (!statusBtn) {
        return;
      }
      var next = statusBtn.getAttribute("data-status");
      var id = Number(statusBtn.getAttribute("data-id"));
      statusBtn.disabled = true;
      postJson("../api/store-order.php", { action: "status", orderId: id, status: next }).then(function (payload) {
        statusBtn.disabled = false;
        if (!payload.ok) {
          Z.showToast(payload.error || "Could not update the order.", "error");
          return;
        }
        return reloadOffice().then(function () {
          paint();
          initShell();
          Z.showToast("Order marked " + next + ".");
        });
      }).catch(function () {
        statusBtn.disabled = false;
        Z.showToast("The store order server did not respond.", "error");
      });
    });

    search.addEventListener("input", paint);
    status.addEventListener("change", paint);
    paint();
  }

  function reloadOffice() {
    return fetch("../api/store.php", { credentials: "same-origin" }).then(function (res) {
      if (res.status === 401 || res.status === 403) {
        window.location.href = "login";
        return null;
      }
      return res.json();
    }).then(function (data) {
      if (!data || !data.ok) {
        throw new Error("store");
      }
      office = data;
      return data;
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    fetch("../api/me.php", { credentials: "same-origin" })
      .then(function (res) {
        return res.ok ? res.json() : null;
      })
      .then(function (data) {
        if (!data || !data.ok || !data.user || data.user.role !== "store") {
          window.location.href = "login";
          return null;
        }
        return reloadOffice();
      })
      .then(function (data) {
        if (!data) {
          return;
        }
        initShell();
        initDashboard();
        initOrders();
      })
      .catch(function () {
        window.location.href = "login";
      });
  });
})();
