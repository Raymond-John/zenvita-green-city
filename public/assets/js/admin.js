(function () {
  "use strict";

  var Z = window.Zenvita;
  if (!Z) {
    return;
  }

  var office = {
    members: [],
    commissions: [],
    withdrawals: [],
    transactions: [],
    packages: [],
    settings: {},
    stats: {},
    growth: { labels: [], values: [] },
    activity: [],
    tree: null
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

  function paginate(items, page, perPage) {
    var totalPages = Math.max(1, Math.ceil(items.length / perPage) || 1);
    var current = Math.min(Math.max(page, 1), totalPages);
    var start = (current - 1) * perPage;
    return { rows: items.slice(start, start + perPage), current: current, totalPages: totalPages, total: items.length, start: start };
  }

  function renderPager(container, state, onPage) {
    if (!state.total) {
      container.innerHTML = "";
      return;
    }
    container.innerHTML = "<span>Showing " + (state.start + 1) + "–" + Math.min(state.start + state.rows.length, state.total) + " of " + state.total + "</span><div class='d-flex gap-2'><button type='button' class='btn btn-soft btn-sm' data-dir='prev'>Previous</button><span class='align-self-center'>Page " + state.current + " of " + state.totalPages + "</span><button type='button' class='btn btn-soft btn-sm' data-dir='next'>Next</button></div>";
    container.querySelector("[data-dir='prev']").disabled = state.current === 1;
    container.querySelector("[data-dir='next']").disabled = state.current === state.totalPages;
    container.querySelectorAll("button").forEach(function (button) {
      button.addEventListener("click", function () {
        onPage(state.current + (button.getAttribute("data-dir") === "next" ? 1 : -1));
      });
    });
  }

  function confirmAction(title, text, onYes) {
    document.getElementById("confirmTitle").textContent = title;
    document.getElementById("confirmText").textContent = text;
    var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("confirmModal"));
    document.getElementById("confirmYes").onclick = function () {
      modal.hide();
      onYes();
    };
    modal.show();
  }

  function initShell() {
    var menu = document.getElementById("notifyMenu");
    if (!menu) {
      return;
    }
    var pending = office.stats.pending || 0;
    menu.innerHTML = '<div class="notify-item"><strong>Pending withdrawals</strong><p>' + pending + (pending === 1 ? " request is" : " requests are") + ' waiting in the database.</p></div><button type="button" class="btn btn-soft btn-sm w-100" id="markRead">Mark as read</button>';
    document.getElementById("markRead").addEventListener("click", function () {
      var dot = document.getElementById("notifyDot");
      if (dot) {
        dot.classList.add("is-hidden");
      }
      Z.showToast("Notifications marked as read.");
    });
  }

  function initDashboard() {
    if (!document.getElementById("statMembers")) {
      return;
    }
    var stats = office.stats;
    document.getElementById("statMembers").textContent = String(stats.members || 0);
    document.getElementById("statActive").textContent = String(stats.active || 0);
    document.getElementById("statPv").textContent = Number(stats.pv || 0).toLocaleString("en-NG");
    document.getElementById("statCommissions").textContent = Z.formatNaira(stats.commissions || 0);
    document.getElementById("statPending").textContent = String(stats.pending || 0);
    document.getElementById("statTransactions").textContent = String(stats.transactions || 0);
    document.getElementById("recentActivity").innerHTML = (office.activity.length ? office.activity : ["No account activity yet."]).map(function (item) {
      return "<li><i></i><span>" + Z.escapeHtml(item) + "</span></li>";
    }).join("");
    if (typeof Chart === "undefined") {
      return;
    }
    var growth = office.growth || { labels: [], values: [] };
    new Chart(document.getElementById("adminGrowthChart"), {
      type: "line",
      data: { labels: growth.labels, datasets: [{ label: "Members added", data: growth.values, borderColor: "#1f7a4d", tension: 0.35, fill: false }] },
      options: { responsive: true, maintainAspectRatio: false }
    });
    new Chart(document.getElementById("adminCommissionChart"), {
      type: "bar",
      data: { labels: ["Direct", "Indirect", "Pairing", "Upgrade"], datasets: [{ data: stats.mix || [0, 0, 0, 0], backgroundColor: ["#0e3b2e", "#1f7a4d", "#8c6e2f", "#b8923a"], borderRadius: 8 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
  }

  function initMembers() {
    var body = document.getElementById("adminMemberBody");
    if (!body) {
      return;
    }
    var page = 1;
    function members() {
      return (office.members || []).slice().sort(function (a, b) {
        var aTime = Date.parse(a.joinedAt || a.joined || "") || 0;
        var bTime = Date.parse(b.joinedAt || b.joined || "") || 0;
        if (aTime !== bTime) {
          return bTime - aTime;
        }
        return String(b.id || "").localeCompare(String(a.id || ""));
      });
    }
    function paint() {
      var query = document.getElementById("memberSearch").value.trim().toLowerCase();
      var status = document.getElementById("memberStatus").value;
      var selected = document.getElementById("memberPackage").value;
      var rows = members().filter(function (member) {
        var text = (member.name + " " + member.email + " " + member.id).toLowerCase();
        return text.indexOf(query) !== -1 && (!status || member.status === status) && (!selected || member.package === selected);
      });
      var state = paginate(rows, page, 6);
      page = state.current;
      body.innerHTML = state.rows.map(function (member) {
        var statusAction = member.status === "Suspended"
          ? '<button class="btn btn-brand btn-sm" data-activate="' + member.id + '">Activate</button>'
          : '<button class="btn btn-reject btn-sm" data-suspend="' + member.id + '">Suspend</button>';
        return "<tr><td>" + Z.escapeHtml(member.id) + '</td><td><span class="name-with-face"><span class="person-face" aria-hidden="true">' + Z.humanFace() + '</span>' + Z.escapeHtml(member.name) + "</span></td><td>" + Z.escapeHtml(member.email) + "</td><td>" + Z.escapeHtml(member.phone) + "</td><td>" + Z.escapeHtml(member.package) + "</td><td>" + member.pv + '</td><td><span class="' + Z.statusClass(member.status) + '">' + Z.escapeHtml(member.status) + "</span></td><td>" + Z.escapeHtml(member.joined) + '</td><td><div class="action-row"><button class="btn btn-soft btn-sm" data-view="' + member.id + '">View</button><button class="btn btn-soft btn-sm" data-edit="' + member.id + '">Edit</button>' + statusAction + '</div></td></tr>';
      }).join("");
      document.getElementById("memberEmpty").hidden = state.total > 0;
      document.getElementById("memberTableWrap").hidden = state.total === 0;
      renderPager(document.getElementById("memberPager"), state, function (next) { page = next; paint(); });
    }
    body.addEventListener("click", function (event) {
      var view = event.target.closest("[data-view]");
      var edit = event.target.closest("[data-edit]");
      var suspend = event.target.closest("[data-suspend]");
      var activate = event.target.closest("[data-activate]");
      var list = members();
      if (view) {
        var person = list.filter(function (item) { return item.id === view.getAttribute("data-view"); })[0];
        document.getElementById("viewMemberBody").innerHTML = ["Member ID", "Name", "Email", "Phone", "Package", "PV", "Status", "Join date", "Sponsor", "Parent", "Position"].map(function (label) {
          var key = label === "Member ID" ? "id" : label === "Join date" ? "joined" : label.toLowerCase();
          return "<p><strong>" + label + ":</strong> " + Z.escapeHtml(person[key]) + "</p>";
        }).join("");
        bootstrap.Modal.getOrCreateInstance(document.getElementById("viewMemberModal")).show();
      }
      if (edit) {
        var current = list.filter(function (item) { return item.id === edit.getAttribute("data-edit"); })[0];
        var form = document.getElementById("editMemberForm");
        form.memberId.value = current.id;
        form.name.value = current.name;
        form.email.value = current.email;
        form.phone.value = current.phone;
        form.package.value = current.package;
        form.status.value = current.status;
        bootstrap.Modal.getOrCreateInstance(document.getElementById("editMemberModal")).show();
      }
      if (suspend) {
        var id = suspend.getAttribute("data-suspend");
        var person = members().filter(function (item) { return item.id === id; })[0];
        confirmAction("Suspend member", "This marks the member suspended in the database.", function () {
          postJson("../api/admin-member.php", {
            memberCode: person.id,
            name: person.name,
            email: person.email,
            phone: person.phone,
            package: person.package,
            status: "Suspended"
          }).then(function (payload) {
            if (!payload.ok) {
              Z.showToast(payload.error || "The member could not be updated.", "error");
              return;
            }
            return reloadOffice();
          }).then(function () {
            paint();
            Z.showToast("Member marked suspended.");
          }).catch(function () {
            Z.showToast("The member server did not respond.", "error");
          });
        });
      }
      if (activate) {
        var actId = activate.getAttribute("data-activate");
        var actPerson = members().filter(function (item) { return item.id === actId; })[0];
        confirmAction("Activate member", "This marks the member as active in the database.", function () {
          postJson("../api/admin-member.php", {
            memberCode: actPerson.id,
            name: actPerson.name,
            email: actPerson.email,
            phone: actPerson.phone,
            package: actPerson.package,
            status: "Active"
          }).then(function (payload) {
            if (!payload.ok) {
              Z.showToast(payload.error || "The member could not be updated.", "error");
              return;
            }
            return reloadOffice();
          }).then(function () {
            paint();
            Z.showToast("Member marked active.");
          }).catch(function () {
            Z.showToast("The member server did not respond.", "error");
          });
        });
      }
    });
    document.getElementById("editMemberForm").addEventListener("submit", function (event) {
      event.preventDefault();
      var form = event.currentTarget;
      postJson("../api/admin-member.php", {
        memberCode: form.memberId.value,
        name: form.name.value.trim(),
        email: form.email.value.trim(),
        phone: form.phone.value.trim(),
        package: form.package.value,
        status: form.status.value
      }).then(function (payload) {
        if (!payload.ok) {
          Z.showToast(payload.error || "The member could not be updated.", "error");
          return;
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById("editMemberModal")).hide();
        return reloadOffice().then(function () {
          paint();
          Z.showToast("Member updated.");
        });
      }).catch(function () {
        Z.showToast("The member server did not respond.", "error");
      });
    });
    var addForm = document.getElementById("adminAddUserForm");
    if (addForm) {
      addForm.addEventListener("submit", function (event) {
        event.preventDefault();
        Z.clearFormErrors(addForm);
        var username = addForm.username.value.trim();
        var password = addForm.password.value;
        var confirmPassword = addForm.confirmPassword ? addForm.confirmPassword.value : "";
        var sideNode = addForm.querySelector("input[name='side']:checked");
        var side = sideNode ? sideNode.value : (addForm.side ? addForm.side.value : "left");
        var terms = addForm.terms ? addForm.terms.checked : true;
        var valid = true;
        if (!addForm.firstName.value.trim()) {
          Z.setFieldError(addForm.firstName, "Enter the first name.");
          valid = false;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(addForm.email.value.trim())) {
          Z.setFieldError(addForm.email, "Enter a valid email address.");
          valid = false;
        }
        if (Z.digitsOnly(addForm.phone.value).length < 10) {
          Z.setFieldError(addForm.phone, "Enter a phone number with at least 10 digits.");
          valid = false;
        }
        if (!/^[A-Za-z0-9._-]{3,60}$/.test(username)) {
          Z.setFieldError(addForm.username, "Use at least 3 letters or numbers.");
          valid = false;
        }
        if (password.length < 8) {
          Z.setFieldError(addForm.password, "Use at least 8 characters.");
          valid = false;
        }
        if (addForm.confirmPassword && password !== confirmPassword) {
          Z.setFieldError(addForm.confirmPassword, "Passwords do not match.");
          valid = false;
        }
        if (addForm.terms && !terms) {
          Z.setFieldError(addForm.terms, "Please accept the terms to continue.");
          valid = false;
        }
        if (!addForm.package.value) {
          Z.setFieldError(addForm.package, "Choose a package.");
          valid = false;
        }
        if (!valid) {
          Z.showToast("Please correct the highlighted fields.", "error");
          return;
        }
        var button = document.getElementById("adminRegisterSubmitBtn") || addForm.querySelector("[type='submit']");
        button.disabled = true;
        postJson("../api/add-user.php", {
          firstName: addForm.firstName.value.trim(),
          lastName: addForm.lastName.value.trim(),
          email: addForm.email.value.trim(),
          phone: addForm.phone.value.trim(),
          username: username,
          password: password,
          package: addForm.package.value,
          sponsor: addForm.sponsor.value.trim(),
          side: side
        }).then(function (payload) {
          button.disabled = false;
          if (!payload.ok) {
            Z.showToast(payload.error || "The account could not be created.", "error");
            return;
          }
          addForm.reset();
          if (addForm.querySelector("#adminSideLeft")) {
            addForm.querySelector("#adminSideLeft").checked = true;
          }
          if (addForm.terms) {
            addForm.terms.checked = true;
          }
          return reloadOffice().then(function () {
            paint();
            fillPackageSelects();
            Z.showToast(payload.username + " can sign in with the password you set.");
          });
        }).catch(function () {
          button.disabled = false;
          Z.showToast("The account server did not respond.", "error");
        });
      });
    }
    ["memberSearch", "memberStatus", "memberPackage"].forEach(function (id) {
      document.getElementById(id).addEventListener("input", function () { page = 1; paint(); });
    });
    paint();
  }

  function initNetwork() {
    var mount = document.getElementById("adminTree");
    if (!mount) {
      return;
    }
    function byId(id) {
      return office.members.filter(function (member) { return member.id === id; })[0];
    }
    var tree = office.tree;
    function show(id) {
      var member = byId(id);
      document.getElementById("adminInspector").innerHTML = [
        ["Sponsor", member.sponsor],
        ["Parent", member.parent],
        ["Position", member.position],
        ["Generation", member.generation],
        ["Left PV", member.leftPv],
        ["Right PV", member.rightPv],
        ["Status", member.status],
        ["Package", member.package]
      ].map(function (field) {
        return "<div><dt>" + field[0] + "</dt><dd>" + Z.escapeHtml(field[1]) + "</dd></div>";
      }).join("");
    }
    if (!tree || !tree.id) {
      mount.innerHTML = '<p class="mb-0">No members are stored yet.</p>';
      return;
    }
    Z.renderBinaryTree(mount, tree, show);
    show(tree.id);
    document.getElementById("adminTreeSearch").addEventListener("input", function (event) {
      var query = event.target.value.trim().toLowerCase();
      mount.querySelectorAll(".tree-node").forEach(function (button) {
        var member = byId(button.getAttribute("data-id"));
        var match = query && member && (member.name.toLowerCase().indexOf(query) !== -1 || member.id.toLowerCase().indexOf(query) !== -1);
        button.classList.toggle("is-match", !!match);
      });
    });

    var btnPair = document.getElementById("btnRunPairing");
    if (btnPair && !btnPair.dataset.bound) {
      btnPair.dataset.bound = "true";
      btnPair.addEventListener("click", function () {
        btnPair.disabled = true;
        btnPair.textContent = "Processing matches…";
        postJson("../api/admin-pairing.php", {})
          .then(function (data) {
            btnPair.disabled = false;
            btnPair.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Process All Pairing Matches';
            if (!data || !data.ok) {
              Z.showToast(data.error || "Pairing processing failed.", "error");
              return;
            }
            var alertBox = document.getElementById("pairingStatusAlert");
            if (alertBox) {
              alertBox.hidden = false;
              if (data.count > 0) {
                alertBox.className = "alert alert-success mt-2 mb-0 py-2";
                alertBox.textContent = "Successfully settled pairing matches for " + data.count + " member(s).";
              } else {
                alertBox.className = "alert alert-info mt-2 mb-0 py-2";
                alertBox.textContent = "No new binary pairing matches found at this time.";
              }
            }
            Z.showToast("Pairing processing complete: " + data.count + " member(s) settled.");
            reloadOffice().then(function (next) {
              if (next && next.ok) {
                office = next;
                initNetwork();
              }
            });
          })
          .catch(function () {
            btnPair.disabled = false;
            btnPair.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Process All Pairing Matches';
            Z.showToast("Could not communicate with pairing server.", "error");
          });
      });
    }
  }

  function initCommissions() {
    var body = document.getElementById("adminCommissionBody");
    if (!body) {
      return;
    }
    var page = 1;
    function paint() {
      var query = document.getElementById("adminCommissionSearch").value.trim().toLowerCase();
      var type = document.getElementById("adminCommissionType").value;
      var status = document.getElementById("adminCommissionStatus").value;
      var rows = office.commissions.filter(function (row) {
        var text = (row.member + " " + row.source + " " + row.type).toLowerCase();
        return text.indexOf(query) !== -1 && (!type || row.type === type) && (!status || row.status === status);
      });
      var state = paginate(rows, page, 5);
      page = state.current;
      body.innerHTML = state.rows.map(function (row) {
        return "<tr><td>" + Z.escapeHtml(row.date) + "</td><td>" + Z.escapeHtml(row.member) + "</td><td>" + Z.escapeHtml(row.source) + "</td><td>" + Z.escapeHtml(row.type) + "</td><td>" + Z.escapeHtml(row.package) + "</td><td>" + row.pv + "</td><td>" + Z.formatNaira(row.amount) + '</td><td><span class="' + Z.statusClass(row.status) + '">' + Z.escapeHtml(row.status) + "</span></td></tr>";
      }).join("");
      document.getElementById("adminCommissionEmpty").hidden = state.total > 0;
      document.getElementById("adminCommissionWrap").hidden = state.total === 0;
      renderPager(document.getElementById("adminCommissionPager"), state, function (next) { page = next; paint(); });
    }
    ["adminCommissionSearch", "adminCommissionType", "adminCommissionStatus"].forEach(function (id) {
      document.getElementById(id).addEventListener("input", function () { page = 1; paint(); });
    });
    paint();
  }

  function initWithdrawals() {
    var body = document.getElementById("adminWithdrawalBody");
    if (!body) {
      return;
    }
    function rows() {
      return office.withdrawals;
    }
    function paint() {
      var list = rows();
      body.innerHTML = list.map(function (row) {
        var actions = row.status === "Pending"
          ? '<button class="btn btn-approve btn-sm" data-approve="' + row.id + '">Approve</button><button class="btn btn-reject btn-sm" data-reject="' + row.id + '">Reject</button>'
          : '<span class="text-muted small px-1">Completed</span>';
        return "<tr><td>" + Z.escapeHtml(row.member) + "</td><td>" + Z.formatNaira(row.amount) + "</td><td>" + Z.formatNaira(row.reserved || 0) + "</td><td>" + Z.escapeHtml(row.bank) + "</td><td>" + Z.escapeHtml(row.account) + "</td><td>" + Z.escapeHtml(row.date) + '</td><td><span class="' + Z.statusClass(row.status) + '">' + Z.escapeHtml(row.status) + '</span></td><td><div class="action-row">' + actions + '<button class="btn btn-soft btn-sm" data-view="' + row.id + '">View</button></div></td></tr>';
      }).join("");
    }
    body.addEventListener("click", function (event) {
      var approve = event.target.closest("[data-approve]");
      var reject = event.target.closest("[data-reject]");
      var view = event.target.closest("[data-view]");
      var list = rows();
      if (view) {
        var item = list.filter(function (row) { return String(row.id) === String(view.getAttribute("data-view")); })[0];
        document.getElementById("viewWithdrawalBody").innerHTML = "<p><strong>" + Z.escapeHtml(item.id) + "</strong></p><p>" + Z.escapeHtml(item.member) + " requested cash " + Z.formatNaira(item.amount) + " to " + Z.escapeHtml(item.bank) + " account " + Z.escapeHtml(item.account) + ".</p><p>Eagle Lands reserve: " + Z.formatNaira(item.reserved || 0) + "</p><p>Status: " + Z.escapeHtml(item.status) + "</p>";
        bootstrap.Modal.getOrCreateInstance(document.getElementById("viewWithdrawalModal")).show();
      }
      function update(id, status) {
        confirmAction(status + " withdrawal", "This updates the stored request. Rejecting returns the amount to the member balance. No bank transfer is sent.", function () {
          postJson("../api/admin-withdrawal.php", { id: Number(id), status: status }).then(function (payload) {
            if (!payload.ok) {
              Z.showToast(payload.error || "The withdrawal could not be updated.", "error");
              return;
            }
            return reloadOffice().then(function () {
              paint();
              Z.showToast("Withdrawal marked " + status.toLowerCase() + ".");
            });
          }).catch(function () {
            Z.showToast("The withdrawal server did not respond.", "error");
          });
        });
      }
      if (approve) {
        update(approve.getAttribute("data-approve"), "Approved");
      }
      if (reject) {
        update(reject.getAttribute("data-reject"), "Rejected");
      }
    });
    paint();
  }

  function fillPackageSelects() {
    var options = (office.packages || []).map(function (item) {
      return "<option value=\"" + Z.escapeHtml(item.name) + "\">" + Z.escapeHtml(item.name) + "</option>";
    }).join("");
    ["memberPackage", "editPackage"].forEach(function (id) {
      var select = document.getElementById(id);
      if (!select) {
        return;
      }
      var current = select.value;
      var keepAll = select.querySelector('option[value=""]');
      select.innerHTML = (keepAll ? keepAll.outerHTML : "") + options;
      if (current) {
        select.value = current;
      }
    });
    // Keep admin register package select rich labels from PHP; sync only if empty.
    var adminPackage = document.getElementById("adminPackage");
    if (adminPackage && adminPackage.options.length < 1 && options) {
      adminPackage.innerHTML = options;
    }
  }

  function initPackages() {
    var body = document.getElementById("adminPackageBody");
    if (!body) {
      return;
    }
    function packages() {
      return office.packages || [];
    }
    function openForm(item) {
      var form = document.getElementById("packageForm");
      var title = document.getElementById("packageTitle");
      var saveBtn = document.getElementById("packageSaveBtn");
      if (item) {
        title.textContent = "Edit package";
        saveBtn.textContent = "Save changes";
        form.create.value = "0";
        form.key.value = item.key;
        form.name.value = item.name || "";
        form.description.value = item.description || "";
        form.price.value = item.price;
        form.pv.value = item.pv;
        form.bottles.value = item.bottles;
        form.referral.value = item.referral;
        form.pairing.value = item.pairing;
        form.dailyCap.value = item.dailyCap;
        form.matches.value = item.matches || 45;
      } else {
        title.textContent = "Add package";
        saveBtn.textContent = "Create package";
        form.reset();
        form.create.value = "1";
        form.key.value = "";
        form.matches.value = "45";
        form.referral.value = "30";
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById("packageModal")).show();
    }
    function paint() {
      var rows = packages();
      if (!rows.length) {
        body.innerHTML = '<tr><td class="empty-state" colspan="9">No packages yet. Add the first package.</td></tr>';
        return;
      }
      body.innerHTML = rows.map(function (item) {
        var desc = (item.description || "").trim();
        var shortDesc = desc.length > 80 ? desc.slice(0, 77) + "…" : desc;
        return "<tr><td>" + Z.escapeHtml(item.name) + "</td><td>" + (shortDesc ? Z.escapeHtml(shortDesc) : "<span class='text-muted'>—</span>") + "</td><td>" + Z.formatNaira(item.price) + "</td><td>" + item.pv + "</td><td>" + item.bottles + "</td><td>" + item.referral + "%</td><td>" + Z.formatNaira(item.pairing) + "</td><td>" + Z.formatNaira(item.dailyCap) + '</td><td><button class="btn btn-soft btn-sm" data-edit="' + Z.escapeHtml(item.key) + '">Edit</button></td></tr>';
      }).join("");
      fillPackageSelects();
    }
    var addBtn = document.getElementById("addPackageBtn");
    if (addBtn) {
      addBtn.addEventListener("click", function () {
        openForm(null);
      });
    }
    body.addEventListener("click", function (event) {
      var button = event.target.closest("[data-edit]");
      if (!button) {
        return;
      }
      var item = packages().filter(function (pkg) { return pkg.key === button.getAttribute("data-edit"); })[0];
      if (!item) {
        return;
      }
      openForm(item);
    });
    document.getElementById("packageForm").addEventListener("submit", function (event) {
      event.preventDefault();
      var form = event.currentTarget;
      var creating = form.create.value === "1" || !form.key.value;
      var saveBtn = document.getElementById("packageSaveBtn");
      saveBtn.disabled = true;
      postJson("../api/admin-package.php", {
        create: creating,
        key: form.key.value,
        name: form.name.value.trim(),
        description: form.description.value.trim(),
        price: Number(form.price.value),
        pv: Number(form.pv.value),
        bottles: Number(form.bottles.value),
        referral: Number(form.referral.value),
        pairing: Number(form.pairing.value),
        dailyCap: Number(form.dailyCap.value),
        matches: Number(form.matches.value) || 0
      }).then(function (payload) {
        saveBtn.disabled = false;
        if (!payload.ok) {
          Z.showToast(payload.error || "The package could not be saved.", "error");
          return;
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById("packageModal")).hide();
        return reloadOffice().then(function () {
          paint();
          Z.showToast(creating ? "Package created." : "Package saved.");
        });
      }).catch(function () {
        saveBtn.disabled = false;
        Z.showToast("The package server did not respond.", "error");
      });
    });
    paint();
  }

  function initTransactions() {
    var body = document.getElementById("adminTxBody");
    if (!body) {
      return;
    }
    var page = 1;
    function paint() {
      var query = document.getElementById("adminTxSearch").value.trim().toLowerCase();
      var type = document.getElementById("adminTxType").value;
      var status = document.getElementById("adminTxStatus").value;
      var rows = office.transactions.filter(function (row) {
        var text = (row.member + " " + row.id + " " + row.description).toLowerCase();
        return text.indexOf(query) !== -1 && (!type || row.type === type) && (!status || row.status === status);
      });
      var state = paginate(rows, page, 5);
      page = state.current;
      body.innerHTML = state.rows.map(function (row) {
        return "<tr><td>" + Z.escapeHtml(row.member) + "</td><td>" + Z.escapeHtml(row.date) + "</td><td>" + Z.escapeHtml(row.id) + "</td><td>" + Z.escapeHtml(row.type) + "</td><td>" + Z.escapeHtml(row.description) + "</td><td>" + Z.formatNaira(row.amount) + '</td><td><span class="' + Z.statusClass(row.status) + '">' + Z.escapeHtml(row.status) + "</span></td></tr>";
      }).join("");
      document.getElementById("adminTxEmpty").hidden = state.total > 0;
      document.getElementById("adminTxWrap").hidden = state.total === 0;
      renderPager(document.getElementById("adminTxPager"), state, function (next) { page = next; paint(); });
    }
    ["adminTxSearch", "adminTxType", "adminTxStatus"].forEach(function (id) {
      document.getElementById(id).addEventListener("input", function () { page = 1; paint(); });
    });
    paint();
  }

  function initSettings() {
    var form = document.getElementById("settingsForm");
    if (!form) {
      return;
    }
    var data = Object.assign({
      siteName: "Cellvigor Biorevive LTD",
      currency: "NGN",
      supportEmail: "",
      directRate: "30",
      pairPv: "60",
      binaryQualification: true,
      minWithdrawal: "1000",
      storeSalesTarget: "3000000",
      storeSalesBonus: "0",
      emailAlerts: true,
      smsAlerts: false,
      smtpProvider: "hostinger",
      smtpHost: "smtp.hostinger.com",
      smtpPort: "465",
      smtpEncryption: "ssl",
      smtpUsername: "",
      smtpPasswordSet: false,
      smtpFromEmail: "",
      smtpFromName: "Cellvigor Biorevive LTD"
    }, office.settings || {});
    form.siteName.value = data.siteName;
    form.currency.value = data.currency;
    form.supportEmail.value = data.supportEmail;
    form.directRate.value = data.directRate;
    form.pairPv.value = data.pairPv;
    if (form.binaryQualification) {
      form.binaryQualification.checked = !!data.binaryQualification;
    }
    form.minWithdrawal.value = data.minWithdrawal;
    if (form.storeSalesTarget) {
      form.storeSalesTarget.value = data.storeSalesTarget || "3000000";
    }
    if (form.storeSalesBonus) {
      form.storeSalesBonus.value = data.storeSalesBonus || "0";
    }
    form.emailAlerts.checked = !!data.emailAlerts;
    form.smsAlerts.checked = !!data.smsAlerts;
    if (form.smtpHost) {
      form.smtpHost.value = data.smtpHost || "smtp.hostinger.com";
      form.smtpPort.value = data.smtpPort || "465";
      form.smtpEncryption.value = data.smtpEncryption || "ssl";
      form.smtpUsername.value = data.smtpUsername || "";
      form.smtpPassword.value = "";
      form.smtpFromEmail.value = data.smtpFromEmail || "";
      form.smtpFromName.value = data.smtpFromName || "";
      var hint = document.getElementById("smtpPasswordHint");
      if (hint) {
        hint.hidden = !data.smtpPasswordSet;
      }
    }
    document.querySelectorAll("[data-settings-tab]").forEach(function (button) {
      button.addEventListener("click", function () {
        var target = button.getAttribute("data-settings-tab");
        document.querySelectorAll("[data-settings-tab]").forEach(function (item) {
          item.classList.toggle("is-active", item === button);
        });
        document.querySelectorAll("[data-settings-panel]").forEach(function (panel) {
          panel.hidden = panel.getAttribute("data-settings-panel") !== target;
        });
      });
    });
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var payload = {
        siteName: form.siteName.value.trim(),
        currency: form.currency.value,
        supportEmail: form.supportEmail.value.trim(),
        directRate: form.directRate.value,
        pairPv: form.pairPv.value,
        binaryQualification: form.binaryQualification ? form.binaryQualification.checked : true,
        minWithdrawal: form.minWithdrawal.value,
        storeSalesTarget: form.storeSalesTarget ? form.storeSalesTarget.value : "3000000",
        storeSalesBonus: form.storeSalesBonus ? form.storeSalesBonus.value : "0",
        emailAlerts: form.emailAlerts.checked,
        smsAlerts: form.smsAlerts.checked
      };
      if (form.smtpHost) {
        payload.smtpHost = form.smtpHost.value.trim();
        payload.smtpPort = form.smtpPort.value;
        payload.smtpEncryption = form.smtpEncryption.value;
        payload.smtpUsername = form.smtpUsername.value.trim();
        payload.smtpFromEmail = form.smtpFromEmail.value.trim();
        payload.smtpFromName = form.smtpFromName.value.trim();
        if (form.smtpPassword.value) {
          payload.smtpPassword = form.smtpPassword.value;
        }
      }
      postJson("../api/admin-settings.php", payload).then(function (result) {
        if (!result.ok) {
          Z.showToast(result.error || "Settings could not be saved.", "error");
          return;
        }
        if (form.smtpPassword) {
          form.smtpPassword.value = "";
        }
        var hint = document.getElementById("smtpPasswordHint");
        if (hint && form.smtpHost && (payload.smtpPassword || data.smtpPasswordSet)) {
          hint.hidden = false;
          data.smtpPasswordSet = true;
        }
        Z.showToast(result.smtpConfigured ? "Settings saved. SMTP is ready." : "Settings saved.");
      }).catch(function () {
        Z.showToast("The settings server did not respond.", "error");
      });
    });
  }

  function initProductsAdmin() {
    var productBody = document.getElementById("adminProductBody");
    if (!productBody) {
      return;
    }
    var featureBody = document.getElementById("adminLandingFeatureBody");
    var catalog = { products: [], features: [] };

    function loadCatalog() {
      return fetch("../api/admin-products.php", { credentials: "same-origin" })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data || !data.ok) {
            Z.showToast((data && data.error) || "Could not load products.", "error");
            return null;
          }
          catalog.products = data.products || [];
          catalog.features = data.features || [];
          paintProducts();
          paintFeatures();
          return data;
        })
        .catch(function () {
          Z.showToast("Products server did not respond.", "error");
          return null;
        });
    }

    function paintProducts() {
      if (!catalog.products.length) {
        productBody.innerHTML = '<tr><td class="empty-state" colspan="8">No products yet. Add the first product.</td></tr>';
        return;
      }
      productBody.innerHTML = catalog.products.map(function (item) {
        var featureCount = (item.features || []).length;
        var thumb = item.imageUrl
          ? '<img class="product-table-thumb" src="../' + Z.escapeHtml(item.imageUrl) + '" alt="">'
          : '<span class="text-muted">—</span>';
        return "<tr><td>" + thumb +
          "</td><td>" + Z.escapeHtml(item.name) +
          "</td><td>" + Z.formatNaira(item.price) +
          "</td><td>" + featureCount + " feature" + (featureCount === 1 ? "" : "s") +
          "</td><td>" + (item.showOnLanding ? "Yes" : "No") +
          '</td><td><span class="' + Z.statusClass(item.status) + '">' + Z.escapeHtml(item.status) +
          "</span></td><td>" + item.sortOrder +
          '</td><td><div class="action-row"><button class="btn btn-soft btn-sm" data-edit-product="' + item.id +
          '">Edit</button><button class="btn btn-reject btn-sm" data-delete-product="' + item.id +
          '">Remove</button></div></td></tr>';
      }).join("");
    }

    function paintFeatures() {
      if (!featureBody) {
        return;
      }
      if (!catalog.features.length) {
        featureBody.innerHTML = '<tr><td class="empty-state" colspan="4">No homepage features yet. Add the first feature.</td></tr>';
        return;
      }
      featureBody.innerHTML = catalog.features.map(function (item) {
        return "<tr><td>" + Z.escapeHtml(item.title) +
          "</td><td>" + item.sortOrder +
          '</td><td><span class="' + Z.statusClass(item.status) + '">' + Z.escapeHtml(item.status) +
          '</span></td><td><div class="action-row"><button class="btn btn-soft btn-sm" data-edit-feature="' + item.id +
          '">Edit</button><button class="btn btn-reject btn-sm" data-delete-feature="' + item.id +
          '">Delete</button></div></td></tr>';
      }).join("");
    }

    function setProductImagePreview(url) {
      var preview = document.getElementById("productImagePreview");
      var removeWrap = document.getElementById("productRemoveImageWrap");
      var remove = document.getElementById("productRemoveImage");
      if (!preview) {
        return;
      }
      if (url) {
        preview.src = url.indexOf("http") === 0 || url.indexOf("../") === 0 ? url : "../" + url;
        preview.hidden = false;
        if (removeWrap) removeWrap.hidden = false;
      } else {
        preview.removeAttribute("src");
        preview.hidden = true;
        if (removeWrap) removeWrap.hidden = true;
      }
      if (remove) remove.checked = false;
    }

    function openProduct(item) {
      var form = document.getElementById("productForm");
      var title = document.getElementById("productTitle");
      if (form.image) {
        form.image.value = "";
      }
      if (item) {
        title.textContent = "Edit product";
        form.id.value = item.id;
        form.name.value = item.name || "";
        form.description.value = item.description || "";
        form.features.value = (item.features || []).join("\n");
        form.price.value = item.price;
        form.unitLabel.value = item.unitLabel || "unit";
        form.sortOrder.value = item.sortOrder || 0;
        form.status.value = item.status || "active";
        form.showOnLanding.checked = !!item.showOnLanding;
        setProductImagePreview(item.imageUrl || "");
      } else {
        title.textContent = "Add product";
        form.reset();
        form.id.value = "";
        form.unitLabel.value = "unit";
        form.sortOrder.value = "0";
        form.status.value = "active";
        form.showOnLanding.checked = true;
        setProductImagePreview("");
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById("productModal")).show();
    }

    function openFeature(item) {
      var form = document.getElementById("landingFeatureForm");
      var title = document.getElementById("landingFeatureTitle");
      if (item) {
        title.textContent = "Edit feature";
        form.id.value = item.id;
        form.title.value = item.title || "";
        form.sortOrder.value = item.sortOrder || 0;
        form.status.value = item.status || "active";
      } else {
        title.textContent = "Add feature";
        form.reset();
        form.id.value = "";
        form.sortOrder.value = String((catalog.features.length || 0) + 1);
        form.status.value = "active";
      }
      bootstrap.Modal.getOrCreateInstance(document.getElementById("landingFeatureModal")).show();
    }

    document.getElementById("addProductBtn").addEventListener("click", function () {
      openProduct(null);
    });
    document.getElementById("addLandingFeatureBtn").addEventListener("click", function () {
      openFeature(null);
    });

    productBody.addEventListener("click", function (event) {
      var edit = event.target.closest("[data-edit-product]");
      var del = event.target.closest("[data-delete-product]");
      if (edit) {
        var item = catalog.products.filter(function (row) {
          return String(row.id) === edit.getAttribute("data-edit-product");
        })[0];
        if (item) openProduct(item);
      }
      if (del) {
        var removeId = Number(del.getAttribute("data-delete-product"));
        confirmAction("Remove product", "Inactive products with orders are deactivated. Unused products are deleted.", function () {
          postJson("../api/admin-product.php", { action: "delete", id: removeId }).then(function (payload) {
            if (!payload.ok) {
              Z.showToast(payload.error || "Could not remove product.", "error");
              return;
            }
            Z.showToast(payload.deactivated ? "Product deactivated." : "Product removed.");
            loadCatalog();
          });
        });
      }
    });

    if (featureBody) {
      featureBody.addEventListener("click", function (event) {
        var edit = event.target.closest("[data-edit-feature]");
        var del = event.target.closest("[data-delete-feature]");
        if (edit) {
          var item = catalog.features.filter(function (row) {
            return String(row.id) === edit.getAttribute("data-edit-feature");
          })[0];
          if (item) openFeature(item);
        }
        if (del) {
          var removeId = Number(del.getAttribute("data-delete-feature"));
          confirmAction("Delete feature", "This removes the feature from the homepage list.", function () {
            postJson("../api/admin-landing-feature.php", { action: "delete", id: removeId }).then(function (payload) {
              if (!payload.ok) {
                Z.showToast(payload.error || "Could not delete feature.", "error");
                return;
              }
              Z.showToast("Feature deleted.");
              loadCatalog();
            });
          });
        }
      });
    }

    var productImageInput = document.getElementById("productImage");
    if (productImageInput) {
      productImageInput.addEventListener("change", function () {
        var file = productImageInput.files && productImageInput.files[0];
        if (!file) {
          return;
        }
        var preview = document.getElementById("productImagePreview");
        if (!preview) {
          return;
        }
        preview.src = URL.createObjectURL(file);
        preview.hidden = false;
        var remove = document.getElementById("productRemoveImage");
        if (remove) remove.checked = false;
      });
    }

    document.getElementById("productForm").addEventListener("submit", function (event) {
      event.preventDefault();
      var form = event.currentTarget;
      var btn = document.getElementById("productSaveBtn");
      btn.disabled = true;
      var data = new FormData();
      data.append("action", "save");
      data.append("id", String(Number(form.id.value || 0)));
      data.append("name", form.name.value.trim());
      data.append("description", form.description.value.trim());
      data.append("features", form.features.value);
      data.append("price", String(Number(form.price.value)));
      data.append("unitLabel", form.unitLabel.value.trim());
      data.append("sortOrder", String(Number(form.sortOrder.value || 0)));
      data.append("status", form.status.value);
      data.append("showOnLanding", form.showOnLanding.checked ? "1" : "0");
      data.append("removeImage", form.removeImage && form.removeImage.checked ? "1" : "0");
      if (form.image && form.image.files && form.image.files[0]) {
        data.append("image", form.image.files[0]);
      }
      fetch("../api/admin-product.php", {
        method: "POST",
        credentials: "same-origin",
        body: data
      }).then(function (res) { return res.json(); }).then(function (payload) {
        btn.disabled = false;
        if (!payload.ok) {
          Z.showToast(payload.error || "Could not save product.", "error");
          return;
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById("productModal")).hide();
        Z.showToast(payload.created ? "Product created." : "Product saved.");
        loadCatalog();
      }).catch(function () {
        btn.disabled = false;
        Z.showToast("Could not save product.", "error");
      });
    });

    document.getElementById("landingFeatureForm").addEventListener("submit", function (event) {
      event.preventDefault();
      var form = event.currentTarget;
      postJson("../api/admin-landing-feature.php", {
        action: "save",
        id: Number(form.id.value || 0),
        title: form.title.value.trim(),
        sortOrder: Number(form.sortOrder.value || 0),
        status: form.status.value
      }).then(function (payload) {
        if (!payload.ok) {
          Z.showToast(payload.error || "Could not save feature.", "error");
          return;
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById("landingFeatureModal")).hide();
        Z.showToast(payload.created ? "Feature added." : "Feature saved.");
        loadCatalog();
      }).catch(function () {
        Z.showToast("Could not save feature.", "error");
      });
    });

    loadCatalog();
  }

  function initPayments() {
    var body = document.getElementById("paymentsBody");
    if (!body) {
      return;
    }
    var empty = document.getElementById("paymentsEmpty");
    var wrap = document.getElementById("paymentsTableWrap");

    function paint(payments) {
      if (!payments.length) {
        body.innerHTML = "";
        if (empty) empty.hidden = false;
        if (wrap) wrap.hidden = true;
        return;
      }
      if (empty) empty.hidden = true;
      if (wrap) wrap.hidden = false;
      body.innerHTML = payments.map(function (row) {
        var member = row.memberCode
          ? Z.escapeHtml(row.memberCode) + (row.memberName ? " · " + Z.escapeHtml(row.memberName) : "")
          : Z.escapeHtml(row.email);
        return "<tr><td>" + Z.escapeHtml(row.createdAt || "") + "</td><td>" + Z.escapeHtml(row.reference) +
          "</td><td>" + member + "</td><td>" + Z.escapeHtml(row.package) +
          "</td><td>" + Z.formatNaira(row.amount) +
          '</td><td><span class="' + Z.statusClass(row.status) + '">' + Z.escapeHtml(row.status) +
          "</span></td></tr>";
      }).join("");
    }

    function loadPayments() {
      return fetch("../api/admin-payments.php", { credentials: "same-origin" })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data || !data.ok) {
            Z.showToast((data && data.error) || "Could not load payments.", "error");
            return;
          }
          paint(data.payments || []);
        })
        .catch(function () {
          Z.showToast("Payments server did not respond.", "error");
        });
    }

    var refresh = document.getElementById("refreshPaymentsBtn");
    if (refresh) {
      refresh.addEventListener("click", loadPayments);
    }

    var fundForm = document.getElementById("manualFundForm");
    var fundSelect = document.getElementById("fundMember");
    if (fundSelect) {
      var fundMembers = (office.members || []).slice().sort(function (a, b) {
        var aTime = Date.parse(a.joinedAt || a.joined || "") || 0;
        var bTime = Date.parse(b.joinedAt || b.joined || "") || 0;
        if (aTime !== bTime) {
          return bTime - aTime;
        }
        return String(b.id || "").localeCompare(String(a.id || ""));
      });
      fundSelect.innerHTML = '<option value="">Choose a member…</option>' + fundMembers.map(function (member) {
        var label = Z.escapeHtml(member.id || "") + " — " + Z.escapeHtml(member.name || "") +
          (member.email ? " · " + Z.escapeHtml(member.email) : "") +
          (member.joined ? " · joined " + Z.escapeHtml(member.joined) : "");
        return '<option value="' + Z.escapeHtml(member.id || "") + '">' + label + "</option>";
      }).join("");
    }
    if (fundForm) {
      fundForm.addEventListener("submit", function (event) {
        event.preventDefault();
        var memberCode = fundForm.memberCode.value.trim();
        if (!memberCode) {
          Z.showToast("Select a member to fund.", "error");
          return;
        }
        postJson("../api/admin-fund-wallet.php", {
          action: "manual_fund",
          memberCode: memberCode,
          amount: fundForm.amount.value,
          note: fundForm.note.value.trim() || "Admin wallet deposit"
        }).then(function (payload) {
          if (!payload.ok) {
            Z.showToast(payload.error || "Manual funding failed.", "error");
            return;
          }
          var msg = "Funded " + Z.escapeHtml(payload.memberCode) + " with " + Z.formatNaira(payload.amount) + ".";
          if (payload.emailSent) {
            msg += " Email notice sent.";
            Z.showToast(msg);
          } else {
            Z.showToast(msg);
            if (payload.emailError) {
              Z.showToast("Email not sent: " + payload.emailError, "error");
            }
          }
          fundForm.reset();
          if (fundSelect) {
            fundSelect.value = "";
          }
          loadPayments();
        }).catch(function () {
          Z.showToast("Funding server did not respond.", "error");
        });
      });
    }

    loadPayments();
  }

  var storeOffice = { stores: [], orders: [] };

  function loadStoreOffice() {
    return fetch("../api/admin-orders.php", { credentials: "same-origin" }).then(function (res) {
      if (res.status === 401 || res.status === 403) {
        window.location.href = "login";
        return null;
      }
      return res.json();
    }).then(function (data) {
      if (!data || !data.ok) {
        return null;
      }
      storeOffice = data;
      return data;
    });
  }

  function initStores() {
    var body = document.getElementById("adminStoreBody");
    if (!body) {
      return;
    }
    function paint() {
      var rows = storeOffice.stores || [];
      if (!rows.length) {
        body.innerHTML = '<tr><td class="empty-state" colspan="9">No stores yet. Register the first store owner above.</td></tr>';
        return;
      }
      body.innerHTML = rows.map(function (store) {
        return "<tr><td>" + Z.escapeHtml(store.storeCode || "—") + "</td><td>" + Z.escapeHtml(store.name) + "</td><td>" + Z.escapeHtml(store.owner) + "</td><td>" + Z.escapeHtml(store.username) + "</td><td>" + Z.escapeHtml(store.city || "—") + "</td><td>" + Z.escapeHtml(store.phone || "—") + '</td><td><span class="' + Z.statusClass(store.status === "active" ? "Active" : "Inactive") + '">' + Z.escapeHtml(store.status) + "</span></td><td>" + Z.escapeHtml(store.created) + '</td><td><div class="action-row"><button class="btn btn-soft btn-sm" data-edit-store="' + store.id + '">Edit</button><button class="btn btn-soft btn-sm" data-toggle-store="' + store.id + '" data-next="' + (store.status === "active" ? "inactive" : "active") + '">' + (store.status === "active" ? "Deactivate" : "Activate") + "</button></div></td></tr>";
      }).join("");
    }
    paint();

    var createForm = document.getElementById("adminStoreForm");
    if (createForm) {
      createForm.addEventListener("submit", function (event) {
        event.preventDefault();
        var button = createForm.querySelector("[type='submit']");
        button.disabled = true;
        postJson("../api/admin-store.php", {
          action: "create",
          firstName: createForm.firstName.value.trim(),
          lastName: createForm.lastName.value.trim(),
          username: createForm.username.value.trim(),
          password: createForm.password.value,
          email: createForm.email.value.trim(),
          phone: createForm.phone.value.trim(),
          storeName: createForm.storeName.value.trim(),
          address: createForm.address.value.trim(),
          city: createForm.city.value.trim()
        }).then(function (payload) {
          button.disabled = false;
          if (!payload.ok) {
            Z.showToast(payload.error || "The store could not be created.", "error");
            return;
          }
          createForm.reset();
          return loadStoreOffice().then(function () {
            paint();
            var codeNote = payload.storeCode ? " Store ID: " + payload.storeCode + "." : "";
            Z.showToast("Store owner created." + codeNote + " They can sign in at store/login.");
          });
        }).catch(function () {
          button.disabled = false;
          Z.showToast("The store server did not respond.", "error");
        });
      });
    }

    body.addEventListener("click", function (event) {
      var editBtn = event.target.closest("[data-edit-store]");
      if (editBtn) {
        var store = (storeOffice.stores || []).filter(function (row) {
          return String(row.id) === editBtn.getAttribute("data-edit-store");
        })[0];
        if (!store) {
          return;
        }
        var form = document.getElementById("editStoreForm");
        form.storeId.value = store.id;
        form.storeName.value = store.name;
        form.address.value = store.address || "";
        form.city.value = store.city || "";
        form.phone.value = store.phone || "";
        form.status.value = store.status;
        bootstrap.Modal.getOrCreateInstance(document.getElementById("editStoreModal")).show();
        return;
      }
      var toggleBtn = event.target.closest("[data-toggle-store]");
      if (!toggleBtn) {
        return;
      }
      var next = toggleBtn.getAttribute("data-next");
      var id = Number(toggleBtn.getAttribute("data-toggle-store"));
      toggleBtn.disabled = true;
      postJson("../api/admin-store.php", { action: "status", storeId: id, status: next }).then(function (payload) {
        toggleBtn.disabled = false;
        if (!payload.ok) {
          Z.showToast(payload.error || "Could not update store status.", "error");
          return;
        }
        return loadStoreOffice().then(function () {
          paint();
          Z.showToast("Store marked " + next + ".");
        });
      }).catch(function () {
        toggleBtn.disabled = false;
        Z.showToast("The store server did not respond.", "error");
      });
    });

    var editForm = document.getElementById("editStoreForm");
    if (editForm) {
      editForm.addEventListener("submit", function (event) {
        event.preventDefault();
        postJson("../api/admin-store.php", {
          action: "update",
          storeId: Number(editForm.storeId.value),
          storeName: editForm.storeName.value.trim(),
          address: editForm.address.value.trim(),
          city: editForm.city.value.trim(),
          phone: editForm.phone.value.trim(),
          status: editForm.status.value
        }).then(function (payload) {
          if (!payload.ok) {
            Z.showToast(payload.error || "Could not save the store.", "error");
            return;
          }
          bootstrap.Modal.getOrCreateInstance(document.getElementById("editStoreModal")).hide();
          return loadStoreOffice().then(function () {
            paint();
            Z.showToast("Store saved.");
          });
        }).catch(function () {
          Z.showToast("The store server did not respond.", "error");
        });
      });
    }
  }

  function initAdminOrders() {
    var body = document.getElementById("adminOrderBody");
    if (!body) {
      return;
    }
    var search = document.getElementById("adminOrderSearch");
    var status = document.getElementById("adminOrderStatus");
    var empty = document.getElementById("adminOrderEmpty");
    function paint() {
      var q = (search.value || "").toLowerCase().trim();
      var st = status.value;
      var rows = (storeOffice.orders || []).filter(function (row) {
        var text = (row.reference + " " + row.member + " " + row.store + " " + row.package).toLowerCase();
        return (!q || text.indexOf(q) !== -1) && (!st || row.status === st);
      });
      empty.hidden = rows.length > 0;
      if (!rows.length) {
        body.innerHTML = "";
        return;
      }
      body.innerHTML = rows.map(function (row) {
        return "<tr><td>" + Z.escapeHtml(row.date) + "</td><td>" + Z.escapeHtml(row.reference) + "</td><td>" + Z.escapeHtml(row.member) + "</td><td>" + Z.escapeHtml(row.package) + "</td><td>" + row.quantity + "</td><td>" + Z.escapeHtml(row.fulfillmentLabel) + "</td><td>" + Z.escapeHtml(row.store) + '</td><td><span class="' + Z.statusClass(row.status) + '">' + Z.escapeHtml(row.status) + "</span></td></tr>";
      }).join("");
    }
    search.addEventListener("input", paint);
    status.addEventListener("change", paint);
    paint();
  }

  function reloadOffice() {
    return fetch("../api/admin.php", { credentials: "same-origin" }).then(function (res) {
      if (res.status === 401 || res.status === 403) {
        window.location.href = "login";
        return null;
      }
      return res.json();
    }).then(function (data) {
      if (!data || !data.ok) {
        throw new Error("office");
      }
      office = data;
      return data;
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".avatar").forEach(function (node) {
      node.innerHTML = window.Zenvita.humanFace();
    });
    fetch("../api/me.php", { credentials: "same-origin" })
      .then(function (res) {
        return res.ok ? res.json() : null;
      })
      .then(function (data) {
        if (!data || !data.ok || !data.user || data.user.role !== "admin") {
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
        initMembers();
        initNetwork();
        initCommissions();
        initWithdrawals();
        initPackages();
        fillPackageSelects();
        initTransactions();
        initSettings();
        initPayments();
        initProductsAdmin();
        var needsStores = document.getElementById("adminStoreBody") || document.getElementById("adminOrderBody");
        if (!needsStores) {
          return;
        }
        return loadStoreOffice().then(function () {
          initStores();
          initAdminOrders();
        });
      })
      .catch(function () {
        window.location.href = "login";
      });
  });
})();
