(function () {
  "use strict";

  var Z = window.Zenvita;
  if (!Z) {
    return;
  }

  var ORDER = ["vision", "silver", "gold", "diamond", "platinum"];

  var serverWallet = null;

  function session() {
    var user = window.__zenvitaUser;
    if (user) {
      return {
        firstName: user.first_name || "",
        lastName: user.last_name || "",
        email: user.email || "",
        phone: user.phone || "",
        package: (user.package_code || "vision").toLowerCase(),
        sponsor: user.sponsor_code || "",
        role: user.role || "member",
        memberCode: user.member_code || "",
        city: user.city || "",
        country: user.country || "Nigeria",
        bank: user.bank_name || "",
        accountName: user.account_name || "",
        accountNumber: user.account_number || ""
      };
    }
    return Z.readStore("zenvitaDemoSession", {
      firstName: "Adaeze",
      lastName: "Okonkwo",
      email: "adaeze.okonkwo@example.com",
      phone: "08000000000",
      package: "vision",
      sponsor: "",
      role: "member"
    });
  }

  function packageKey() {
    var key = (session().package || "vision").toLowerCase();
    return Z.packages[key] ? key : "vision";
  }

  function currentPackage() {
    return Z.packages[packageKey()];
  }

  var TREE = {
    id: "ZGV12345",
    name: "Adaeze Okonkwo",
    shortName: "You",
    package: "Vision",
    pv: 150,
    status: "active",
    position: "Root",
    generation: 0,
    sponsor: "—",
    parent: "—",
    leftPv: 1500,
    rightPv: 600,
    isYou: true,
    showEmpty: true,
    left: {
      id: "ZGV20811",
      name: "Chinedu Adeyemi",
      package: "Silver",
      pv: 300,
      status: "active",
      position: "Left",
      generation: 1,
      sponsor: "ZGV12345",
      parent: "ZGV12345",
      side: "left",
      showEmpty: true,
      left: {
        id: "ZGV30102",
        name: "Kwame Mensah",
        package: "Gold",
        pv: 600,
        status: "active",
        position: "Left",
        generation: 2,
        sponsor: "ZGV20811",
        parent: "ZGV20811",
        side: "left",
        showEmpty: true,
        left: { id: "ZGV41020", name: "Lerato Dlamini", package: "Vision", pv: 150, status: "active", position: "Left", generation: 3, sponsor: "ZGV30102", parent: "ZGV30102", side: "left" },
        right: { id: "ZGV41033", name: "Ibrahim Sani", package: "Silver", pv: 300, status: "active", position: "Right", generation: 3, sponsor: "ZGV30102", parent: "ZGV30102", side: "left" }
      },
      right: { id: "ZGV30118", name: "Ngozi Eze", package: "Vision", pv: 150, status: "inactive", position: "Right", generation: 2, sponsor: "ZGV20811", parent: "ZGV20811", side: "left" }
    },
    right: {
      id: "ZGV20844",
      name: "Fatima Bello",
      package: "Vision",
      pv: 150,
      status: "active",
      position: "Right",
      generation: 1,
      sponsor: "ZGV12345",
      parent: "ZGV12345",
      side: "right",
      showEmpty: true,
      left: { id: "ZGV30140", name: "Amina Yusuf", package: "Silver", pv: 300, status: "active", position: "Left", generation: 2, sponsor: "ZGV20844", parent: "ZGV20844", side: "right" },
      right: { id: "ZGV30155", name: "Tunde Bakare", package: "Vision", pv: 150, status: "inactive", position: "Right", generation: 2, sponsor: "ZGV20844", parent: "ZGV20844", side: "right" }
    }
  };

  function flatten(node, list) {
    list = list || [];
    if (!node) {
      return list;
    }
    list.push(node);
    flatten(node.left, list);
    flatten(node.right, list);
    return list;
  }

  var PEOPLE = flatten(TREE);
  var TEAM = PEOPLE.filter(function (person) { return person.id !== TREE.id; });

  var COMMISSIONS = [
    { date: "02 Oct 2026", member: "Chinedu Adeyemi", type: "Direct Referral", package: "Silver", pv: 300, amount: 90000, status: "Approved" },
    { date: "28 Sep 2026", member: "Fatima Bello", type: "Direct Referral", package: "Vision", pv: 150, amount: 45000, status: "Paid" },
    { date: "26 Sep 2026", member: "Kwame Mensah", type: "Indirect Referral", package: "Gold", pv: 600, amount: 12000, status: "Approved" },
    { date: "22 Sep 2026", member: "Amina Yusuf", type: "Indirect Referral", package: "Silver", pv: 300, amount: 3000, status: "Pending" },
    { date: "20 Sep 2026", member: "Left / Right match", type: "Pairing", package: "Vision", pv: 60, amount: 1440, status: "Paid" },
    { date: "18 Sep 2026", member: "Left / Right match", type: "Pairing", package: "Vision", pv: 120, amount: 2880, status: "Approved" },
    { date: "14 Sep 2026", member: "Lerato Dlamini", type: "Indirect Referral", package: "Vision", pv: 150, amount: 750, status: "Pending" },
    { date: "09 Sep 2026", member: "Package difference", type: "Upgrade", package: "Silver", pv: 150, amount: 45000, status: "Approved" }
  ];

  var TRANSACTIONS = [
    { date: "02 Oct 2026", id: "TX-24018", type: "Commission", description: "Sample direct referral entry", amount: 90000, status: "Approved" },
    { date: "28 Sep 2026", id: "TX-24002", type: "Commission", description: "Sample direct referral entry", amount: 45000, status: "Paid" },
    { date: "24 Sep 2026", id: "TX-23990", type: "Withdrawal", description: "Sample withdrawal request", amount: -20000, status: "Pending" },
    { date: "20 Sep 2026", id: "TX-23961", type: "Commission", description: "Sample pairing entry", amount: 1440, status: "Paid" },
    { date: "16 Sep 2026", id: "TX-23920", type: "Package", description: "Vision package selection", amount: -150000, status: "Approved" },
    { date: "12 Sep 2026", id: "TX-23888", type: "Commission", description: "Sample indirect referral entry", amount: 3000, status: "Pending" },
    { date: "08 Sep 2026", id: "TX-23840", type: "Withdrawal", description: "Sample withdrawal request", amount: -15000, status: "Approved" },
    { date: "04 Sep 2026", id: "TX-23811", type: "Commission", description: "Sample upgrade entry", amount: 45000, status: "Approved" },
    { date: "29 Aug 2026", id: "TX-23770", type: "Withdrawal", description: "Sample withdrawal request", amount: -10000, status: "Rejected" },
    { date: "21 Aug 2026", id: "TX-23702", type: "Commission", description: "Sample pairing entry", amount: 2880, status: "Paid" }
  ];

  function sumType(type) {
    return COMMISSIONS.reduce(function (sum, row) {
      return sum + (row.type === type ? Number(row.amount) : 0);
    }, 0);
  }

  function wallet() {
    if (serverWallet) {
      return serverWallet;
    }
    return Z.readStore("zenvitaWallet", {
      available: 42800,
      withdrawable: 17120,
      eagleLands: 0,
      withdrawalPolicy: { isWithdrawDay: false, cashPercent: 40, reservePercent: 60, today: "Demo", day: "Saturday", eagleLandsEligible: true, eagleLandsSplit: true },
      withdrawals: [
        { date: "24 Sep 2026", amount: 20000, reserved: 30000, bank: "Demo Bank", account: "0123456789", name: "Adaeze Okonkwo", status: "Pending" },
        { date: "08 Sep 2026", amount: 15000, reserved: 22500, bank: "Demo Bank", account: "0123456789", name: "Adaeze Okonkwo", status: "Approved" },
        { date: "29 Aug 2026", amount: 10000, reserved: 15000, bank: "Demo Bank", account: "0123456789", name: "Adaeze Okonkwo", status: "Rejected" }
      ]
    });
  }

  function saveWallet(data) {
    Z.writeStore("zenvitaWallet", data);
  }

  function extraTransactions() {
    return Z.readStore("zenvitaExtraTx", []);
  }

  function personById(id) {
    return PEOPLE.filter(function (person) { return person.id === id; })[0] || null;
  }

  function fillIdentity() {
    var person = session();
    var name = (person.firstName || "Adaeze") + " " + (person.lastName || "Okonkwo");
    document.querySelectorAll("[data-user-name]").forEach(function (node) {
      node.textContent = name;
    });
    document.querySelectorAll("[data-user-handle]").forEach(function (node) {
      node.textContent = "@" + (person.firstName || "member").toLowerCase();
    });
    document.querySelectorAll("[data-user-avatar]").forEach(function (node) {
      node.innerHTML = Z.humanFace();
    });
    var pkg = currentPackage();
    document.querySelectorAll("[data-user-package]").forEach(function (node) {
      node.textContent = pkg.name;
    });
  }

  function initNotifications() {
    var menu = document.getElementById("notifyMenu");
    var dot = document.getElementById("notifyDot");
    if (!menu) {
      return;
    }
    var pending = wallet().withdrawals.filter(function (row) { return row.status === "Pending"; }).length;
    var latest = COMMISSIONS[0];
    var items = [
      { title: "Latest commission", text: latest ? latest.date + " · " + latest.member + " · " + latest.type : "No commissions are stored yet." },
      { title: "Network", text: TEAM.length + " people are stored in your team." },
      { title: "Withdrawals", text: pending ? pending + " request" + (pending === 1 ? " is" : "s are") + " waiting." : "No withdrawal is waiting." }
    ];
    menu.innerHTML = items.map(function (item) {
      return '<div class="notify-item"><strong>' + Z.escapeHtml(item.title) + "</strong><p>" + Z.escapeHtml(item.text) + "</p></div>";
    }).join("") + '<button type="button" class="btn btn-soft btn-sm w-100" id="markRead">Mark as read</button>';
    var button = document.getElementById("markRead");
    if (button) {
      button.addEventListener("click", function () {
        if (dot) {
          dot.classList.add("is-hidden");
        }
        Z.showToast("Notifications marked as read.");
      });
    }
  }

  function renderDetail(container, person) {
    if (!container) {
      return;
    }
    if (!person) {
      container.innerHTML = '<p class="mb-0">Select a person in the tree.</p>';
      return;
    }
    var fields = [
      ["Name", person.name],
      ["Member ID", person.id],
      ["Package", person.package],
      ["PV", person.pv],
      ["Status", person.status],
      ["Position", person.position],
      ["Generation", person.generation],
      ["Sponsor", person.sponsor],
      ["Parent", person.parent],
      ["Left PV", person.leftPv == null ? "—" : person.leftPv],
      ["Right PV", person.rightPv == null ? "—" : person.rightPv]
    ];
    container.innerHTML = '<dl class="detail-list">' + fields.map(function (field) {
      return "<div><dt>" + Z.escapeHtml(field[0]) + "</dt><dd>" + Z.escapeHtml(field[1]) + "</dd></div>";
    }).join("") + "</dl>";
  }

  function initTree(searchId) {
    var mount = document.getElementById("memberTree");
    if (!mount) {
      return;
    }
    var detail = document.getElementById("treeDetail");
    Z.renderBinaryTree(mount, TREE, function (id) {
      renderDetail(detail, personById(id));
    });
    renderDetail(detail, TREE);
    var search = document.getElementById(searchId || "treeSearch");
    if (!search) {
      return;
    }
    search.addEventListener("input", function () {
      var query = search.value.trim().toLowerCase();
      mount.querySelectorAll(".tree-node").forEach(function (node) {
        var person = personById(node.getAttribute("data-id"));
        var match = query && person && (person.name.toLowerCase().indexOf(query) !== -1 || person.id.toLowerCase().indexOf(query) !== -1);
        node.classList.toggle("is-match", !!match);
        if (match) {
          node.scrollIntoView({ inline: "center", block: "nearest" });
        }
      });
    });
  }

  function initOverview() {
    if (!document.getElementById("statEarnings")) {
      return;
    }
    var money = wallet();
    var active = TEAM.filter(function (person) { return person.status === "active"; }).length;
    var direct = sumType("Direct Referral");
    var indirect = sumType("Indirect Referral");
    var pairing = sumType("Pairing");
    var upgrade = sumType("Upgrade");
    var totalPv = Number(TREE.leftPv || 0) + Number(TREE.rightPv || 0) + Number(TREE.pv || 0);
    var values = {
      statEarnings: Z.formatNaira(direct + indirect + pairing + upgrade),
      statBalance: Z.formatNaira(money.available),
      statWithdrawable: Z.formatNaira(money.withdrawable || 0),
      statEagleLands: Z.formatNaira(money.eagleLands || 0),
      statReserveRule: (money.withdrawalPolicy && money.withdrawalPolicy.eagleLandsSplit === false)
        ? "No 40/60 split"
        : "60% reserved (Vision–Platinum)",
      statPv: totalPv.toLocaleString("en-NG"),
      statDirect: String(TEAM.filter(function (person) { return person.generation === 1; }).length),
      statTeam: String(TEAM.length),
      statLeft: Number(TREE.leftPv || 0).toLocaleString("en-NG"),
      statRight: Number(TREE.rightPv || 0).toLocaleString("en-NG"),
      teamLeftCount: String(TEAM.filter(function (person) { return person.side === "left"; }).length),
      teamRightCount: String(TEAM.filter(function (person) { return person.side === "right"; }).length),
      teamActive: String(active),
      teamInactive: String(TEAM.length - active),
      sumDirect: Z.formatNaira(direct),
      sumIndirect: Z.formatNaira(indirect),
      sumPairing: Z.formatNaira(pairing),
      sumUpgrade: Z.formatNaira(upgrade)
    };
    Object.keys(values).forEach(function (id) {
      var node = document.getElementById(id);
      if (node) {
        node.textContent = values[id];
      }
    });
    var link = document.getElementById("referralLink");
    if (link) {
      var code = session().memberCode || (window.__zenvitaUser && window.__zenvitaUser.member_code) || "ZGV00002";
      var url = new URL("../register", window.location.href);
      url.searchParams.set("ref", code);
      link.value = url.href;
    }
    var body = document.getElementById("recentCommissionBody");
    if (body) {
      body.innerHTML = COMMISSIONS.slice(0, 5).map(commissionRow).join("");
    }
    initCharts();
  }

  function commissionRow(row) {
    return "<tr><td>" + Z.escapeHtml(row.date) + "</td><td>" + Z.escapeHtml(row.member) + "</td><td>" + Z.escapeHtml(row.type) + "</td><td>" + Z.escapeHtml(row.package) + "</td><td>" + Z.escapeHtml(row.pv) + "</td><td>" + Z.formatNaira(row.amount) + '</td><td><span class="' + Z.statusClass(row.status) + '">' + Z.escapeHtml(row.status) + "</span></td></tr>";
  }

  function initCharts() {
    if (typeof Chart === "undefined") {
      return;
    }
    Chart.defaults.font.family = "Outfit, sans-serif";
    Chart.defaults.color = "#5c6a64";
    var pv = document.getElementById("pvChart");
    var commission = document.getElementById("commissionChart");
    var team = document.getElementById("teamChart");
    var recorded = window.__zenvitaCharts || {};
    var pvSeries = recorded.pv || { labels: [], values: [] };
    var teamSeries = recorded.team || { labels: [], values: [] };
    if (pv) {
      new Chart(pv, {
        type: "line",
        data: {
          labels: pvSeries.labels,
          datasets: [{ label: "Recorded PV", data: pvSeries.values, borderColor: "#1f7a4d", backgroundColor: "rgba(31,122,77,.12)", fill: true, tension: 0.35 }]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });
    }
    if (commission) {
      new Chart(commission, {
        type: "bar",
        data: {
          labels: ["Direct", "Indirect", "Pairing", "Upgrade"],
          datasets: [{ label: "Recorded total", data: [sumType("Direct Referral"), sumType("Indirect Referral"), sumType("Pairing"), sumType("Upgrade")], backgroundColor: ["#0e3b2e", "#1f7a4d", "#8c6e2f", "#b8923a"], borderRadius: 8 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
      });
    }
    if (team) {
      new Chart(team, {
        type: "line",
        data: {
          labels: teamSeries.labels,
          datasets: [{ label: "Team size", data: teamSeries.values, borderColor: "#8c6e2f", tension: 0.3 }]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });
    }
  }

  function paginate(items, page, perPage) {
    var totalPages = Math.max(1, Math.ceil(items.length / perPage) || 1);
    var current = Math.min(Math.max(page, 1), totalPages);
    var start = (current - 1) * perPage;
    return { rows: items.slice(start, start + perPage), current: current, totalPages: totalPages, total: items.length, start: start };
  }

  function renderPager(container, state, onPage) {
    if (!container) {
      return;
    }
    if (!state.total) {
      container.innerHTML = "";
      return;
    }
    container.innerHTML = '<span>Showing ' + (state.start + 1) + "–" + Math.min(state.start + state.rows.length, state.total) + " of " + state.total + "</span><div class='d-flex gap-2'><button type='button' class='btn btn-soft btn-sm' data-dir='prev'>Previous</button><span class='align-self-center'>Page " + state.current + " of " + state.totalPages + "</span><button type='button' class='btn btn-soft btn-sm' data-dir='next'>Next</button></div>";
    container.querySelector("[data-dir='prev']").disabled = state.current === 1;
    container.querySelector("[data-dir='next']").disabled = state.current === state.totalPages;
    container.querySelectorAll("button").forEach(function (button) {
      button.addEventListener("click", function () {
        onPage(state.current + (button.getAttribute("data-dir") === "next" ? 1 : -1));
      });
    });
  }

  function initNetworkPage() {
    var grid = document.getElementById("memberGrid");
    if (!grid) {
      return;
    }
    var active = TEAM.filter(function (person) { return person.status === "active"; }).length;
    document.getElementById("teamLeftCount").textContent = String(TEAM.filter(function (person) { return person.side === "left"; }).length);
    document.getElementById("teamRightCount").textContent = String(TEAM.filter(function (person) { return person.side === "right"; }).length);
    document.getElementById("teamActive").textContent = String(active);
    document.getElementById("teamInactive").textContent = String(TEAM.length - active);
    var search = document.getElementById("memberSearch");
    var side = document.getElementById("sideFilter");
    var status = document.getElementById("statusFilter");
    var generation = "all";
    document.getElementById("genFilters").addEventListener("click", function (event) {
      var chip = event.target.closest("[data-gen]");
      if (!chip) {
        return;
      }
      generation = chip.getAttribute("data-gen");
      document.querySelectorAll("[data-gen]").forEach(function (item) {
        item.classList.toggle("is-active", item === chip);
      });
      paint();
    });
    function paint() {
      var query = search.value.trim().toLowerCase();
      var rows = TEAM.filter(function (person) {
        var text = (person.name + " " + person.id).toLowerCase();
        var genOk = generation === "all" || String(person.generation) === generation;
        var sideOk = !side.value || person.side === side.value;
        var statusOk = !status.value || person.status === status.value;
        return text.indexOf(query) !== -1 && genOk && sideOk && statusOk;
      });
      var empty = document.getElementById("memberEmpty");
      empty.hidden = rows.length > 0;
      grid.innerHTML = rows.map(function (person) {
        return '<article class="member-card"><div class="member-person"><span class="person-face" aria-hidden="true">' + Z.humanFace() + '</span><div><div class="d-flex justify-content-between gap-2"><h3>' + Z.escapeHtml(person.name) + '</h3><span class="' + Z.statusClass(person.status) + '">' + Z.escapeHtml(person.status) + '</span></div><p>' + Z.escapeHtml(person.id) + "</p><p>" + Z.escapeHtml(person.package) + " · " + person.pv + " PV</p><p>Generation " + person.generation + " · " + Z.escapeHtml(person.side) + " team</p></div></div></article>";
      }).join("");
    }
    [search, side, status].forEach(function (control) {
      control.addEventListener("input", paint);
    });
    paint();
  }

  function initCommissions() {
    var body = document.getElementById("commissionBody");
    if (!body) {
      return;
    }
    var type = "all";
    var page = 1;
    var hash = window.location.hash.replace("#", "");
    if (["direct", "indirect", "pairing", "upgrade"].indexOf(hash) !== -1) {
      type = hash;
    }
    function paint() {
      var query = document.getElementById("commissionSearch").value.trim().toLowerCase();
      var status = document.getElementById("commissionStatus").value;
      var selectedPackage = document.getElementById("commissionPackage").value;
      var rows = COMMISSIONS.filter(function (row) {
        var kind = row.type.toLowerCase();
        var typeOk = type === "all" || kind.indexOf(type) !== -1;
        var text = (row.member + " " + row.type + " " + row.package).toLowerCase();
        return typeOk && text.indexOf(query) !== -1 && (!status || row.status === status) && (!selectedPackage || row.package === selectedPackage);
      });
      var state = paginate(rows, page, 5);
      page = state.current;
      body.innerHTML = state.rows.map(commissionRow).join("");
      document.getElementById("commissionEmpty").hidden = state.total > 0;
      document.getElementById("commissionTableWrap").hidden = state.total === 0;
      renderPager(document.getElementById("commissionPager"), state, function (next) {
        page = next;
        paint();
      });
    }
    document.getElementById("commissionTabs").addEventListener("click", function (event) {
      var tab = event.target.closest("[data-tab]");
      if (!tab) {
        return;
      }
      type = tab.getAttribute("data-tab");
      page = 1;
      document.querySelectorAll("#commissionTabs [data-tab]").forEach(function (item) {
        item.classList.toggle("is-active", item === tab);
      });
      paint();
    });
    document.querySelectorAll("#commissionTabs [data-tab]").forEach(function (tab) {
      tab.classList.toggle("is-active", tab.getAttribute("data-tab") === type);
    });
    ["commissionSearch", "commissionStatus", "commissionPackage"].forEach(function (id) {
      document.getElementById(id).addEventListener("input", function () {
        page = 1;
        paint();
      });
    });
    paint();
  }

  function initPackagePage() {
    var root = document.getElementById("upgradeOptions");
    if (!root) {
      return;
    }
    var current = currentPackage();
    document.getElementById("currentPackageName").textContent = current.name;
    document.getElementById("currentPackagePrice").textContent = Z.formatNaira(current.price);
    document.getElementById("currentPackageMeta").textContent = current.bottles + " bottles · " + current.pv + " PV · Direct referral bonus " + Z.formatNaira(current.bonus);
    var benefitItems = [];
    if ((current.description || "").trim()) {
      benefitItems.push(current.description.trim());
    }
    benefitItems = benefitItems.concat([
      current.bottles + " bottles listed as the product quantity",
      current.pv + " personal PV",
      "Direct referral bonus stated at " + Z.formatNaira(current.bonus),
      current.matches + " daily pairing matches stated at " + Z.formatNaira(current.perMatch) + " each",
      "Stated maximum daily pairing payout of " + Z.formatNaira(current.dailyMax)
    ]);
    document.getElementById("packageBenefits").innerHTML = benefitItems.map(function (item) {
      return "<li>" + Z.escapeHtml(item) + "</li>";
    }).join("");
    var packageKeys = Object.keys(Z.packages).sort(function (a, b) {
      return (Z.packages[a].price || 0) - (Z.packages[b].price || 0);
    });
    var higher = packageKeys.filter(function (key) { return Z.packages[key].price > current.price; });
    if (!higher.length) {
      root.innerHTML = '<div class="empty-state">You are on the highest listed package. No upgrade difference is available.</div>';
      return;
    }
    root.innerHTML = higher.map(function (key) {
      var item = Z.packages[key];
      var difference = item.price - current.price;
      var desc = (item.description || "").trim();
      var descHtml = desc ? "<p class=\"package-desc\">" + Z.escapeHtml(desc) + "</p>" : "";
      return '<article class="upgrade-card"><h3>' + Z.escapeHtml(item.name) + "</h3><p>" + Z.formatNaira(item.price) + " · " + item.bottles + " bottles · " + item.pv + " PV</p>" + descHtml + "<p>Difference " + Z.formatNaira(difference) + '</p><button type="button" class="btn btn-brand btn-sm" data-upgrade="' + Z.escapeHtml(key) + '">Review upgrade</button></article>';
    }).join("");
    var modalEl = document.getElementById("upgradeModal");
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    root.addEventListener("click", function (event) {
      var button = event.target.closest("[data-upgrade]");
      if (!button || !modal) {
        return;
      }
      var next = Z.packages[button.getAttribute("data-upgrade")];
      document.getElementById("upgradeFrom").textContent = current.name + " — " + Z.formatNaira(current.price);
      document.getElementById("upgradeTo").textContent = next.name + " — " + Z.formatNaira(next.price);
      document.getElementById("upgradeDiff").textContent = Z.formatNaira(next.price - current.price);
      document.getElementById("confirmUpgrade").setAttribute("data-key", next.key);
      modal.show();
    });
    document.getElementById("confirmUpgrade").addEventListener("click", function (event) {
      var key = event.currentTarget.getAttribute("data-key");
      var btn = event.currentTarget;
      btn.disabled = true;

      fetch("../api/upgrade.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ packageKey: key })
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        btn.disabled = false;
        if (modal) {
          modal.hide();
        }
        if (!data || !data.ok) {
          Z.showToast(data.error || "Upgrade failed.", "error");
          return;
        }
        Z.showToast(data.message || "Package upgraded successfully!");
        window.setTimeout(function () {
          window.location.reload();
        }, 900);
      })
      .catch(function () {
        btn.disabled = false;
        if (modal) {
          modal.hide();
        }
        Z.showToast("Could not connect to upgrade service.", "error");
      });
    });
  }

  function initWithdrawals() {
    var form = document.getElementById("withdrawalForm");
    if (!form) {
      return;
    }
    var submitBtn = document.getElementById("withdrawSubmitBtn");
    var dayBadge = document.getElementById("withdrawDayBadge");
    var reservePreview = document.getElementById("reservePreview");
    var withdrawableEl = document.getElementById("withdrawableBalance");
    var eagleEl = document.getElementById("eagleLandsBalance");

    function policy() {
      return (wallet().withdrawalPolicy) || {
        isWithdrawDay: false,
        cashPercent: 100,
        reservePercent: 0,
        today: "",
        day: "Saturday",
        eagleLandsEligible: false,
        eagleLandsSplit: false
      };
    }

    function usesEagleSplit() {
      var p = policy();
      return !!(p.eagleLandsSplit || (p.eagleLandsEligible && Number(p.reservePercent) > 0));
    }

    function maxCash() {
      var data = wallet();
      if (typeof data.withdrawable === "number") {
        return data.withdrawable;
      }
      var pct = usesEagleSplit() ? (policy().cashPercent || 40) : 100;
      return Math.floor((Number(data.available) || 0) * (pct / 100));
    }

    function reservedFor(cashAmount) {
      if (!usesEagleSplit()) {
        return 0;
      }
      var p = policy();
      var cashPercent = Number(p.cashPercent);
      var reservePercent = Number(p.reservePercent);
      // Treat missing/NaN as unsafe; never coerce 0 → 40/60 via ||.
      if (!isFinite(cashPercent) || !isFinite(reservePercent) || cashPercent <= 0 || reservePercent <= 0) {
        return 0;
      }
      return Math.round(cashAmount * (reservePercent / cashPercent) * 100) / 100;
    }

    function paint() {
      var data = wallet();
      var p = policy();
      var splitOn = usesEagleSplit();
      document.getElementById("availableBalance").textContent = Z.formatNaira(data.available);
      if (withdrawableEl) {
        withdrawableEl.textContent = Z.formatNaira(maxCash());
      }
      if (eagleEl) {
        eagleEl.textContent = Z.formatNaira(data.eagleLands || 0);
      }
      var note = document.getElementById("withdrawPolicyNote");
      if (note) {
        note.textContent = splitOn
          ? "Cash withdrawals are allowed only on Saturdays. Vision–Platinum plans can withdraw up to 40% as cash; the matching 60% goes to Eagle Lands & Properties."
          : "Cash withdrawals are allowed only on Saturdays. Plans below Vision are not on the 40/60 Eagle Lands split — you can withdraw from your full available balance.";
      }
      var withdrawableCard = document.getElementById("withdrawableCardLabel");
      if (withdrawableCard) {
        withdrawableCard.textContent = splitOn ? "Withdrawable Now (40%)" : "Withdrawable Now";
      }
      var amountLabel = document.getElementById("withdrawAmountLabel");
      if (amountLabel) {
        amountLabel.textContent = splitOn ? "Cash Withdrawal Amount (40%)" : "Withdrawal Amount";
      }
      var cashHead = document.getElementById("withdrawCashHead");
      var eagleHead = document.getElementById("withdrawEagleHead");
      if (cashHead) cashHead.textContent = splitOn ? "Cash (40%)" : "Amount";
      if (eagleHead) eagleHead.textContent = splitOn ? "Eagle Lands (60%)" : "Eagle Lands";
      if (dayBadge) {
        dayBadge.textContent = p.isWithdrawDay
          ? "Today is Saturday — withdrawals are open."
          : ("Withdrawals open on Saturdays only. Today is " + (p.today || "not Saturday") + ".");
      }
      if (submitBtn) {
        submitBtn.disabled = !p.isWithdrawDay;
        submitBtn.textContent = p.isWithdrawDay ? "Submit Withdrawal Request" : "Withdrawals open on Saturday";
      }
      var body = document.getElementById("withdrawalBody");
      body.innerHTML = data.withdrawals.map(function (row) {
        return "<tr><td>" + Z.escapeHtml(row.date) + "</td><td>" + Z.formatNaira(row.amount) + "</td><td>" + Z.formatNaira(row.reserved || 0) + "</td><td>" + Z.escapeHtml(row.bank) + '</td><td><span class="' + Z.statusClass(row.status) + '">' + Z.escapeHtml(row.status) + "</span></td></tr>";
      }).join("");
      document.getElementById("withdrawalEmpty").hidden = data.withdrawals.length > 0;
      updateReservePreview();
    }

    function updateReservePreview() {
      if (!reservePreview) {
        return;
      }
      var amount = Number(form.amount.value) || 0;
      if (!usesEagleSplit()) {
        reservePreview.textContent = amount
          ? ("Full cash withdrawal " + Z.formatNaira(amount) + " · No Eagle Lands 40/60 split on plans below Vision.")
          : "Enter an amount. Plans below Vision withdraw without the Eagle Lands 40/60 reduction.";
        return;
      }
      if (!amount) {
        reservePreview.textContent = "Enter a cash amount. Matching 60% will move to Eagle Lands & Properties (Vision–Platinum).";
        return;
      }
      reservePreview.textContent = "Cash " + Z.formatNaira(amount) + " · Eagle Lands reserve " + Z.formatNaira(reservedFor(amount)) + " · Total deducted " + Z.formatNaira(amount + reservedFor(amount));
    }

    form.amount.addEventListener("input", updateReservePreview);

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      Z.clearFormErrors(form);
      var data = wallet();
      var p = policy();
      var amount = Number(form.amount.value);
      var valid = true;
      var minW = window.__zenvitaMinWithdrawal || 1000;
      if (!p.isWithdrawDay) {
        Z.setFieldError(form.amount, "Withdrawals are only allowed on Saturdays.");
        valid = false;
      }
      if (!amount || amount < minW) {
        Z.setFieldError(form.amount, "Enter at least " + Z.formatNaira(minW) + ".");
        valid = false;
      } else if (amount > maxCash()) {
        Z.setFieldError(
          form.amount,
          usesEagleSplit()
            ? ("You can withdraw only 40% of your balance (max " + Z.formatNaira(maxCash()) + ").")
            : ("Amount exceeds your available balance (max " + Z.formatNaira(maxCash()) + ").")
        );
        valid = false;
      }
      if (!form.bank.value.trim()) {
        Z.setFieldError(form.bank, "Enter a bank name.");
        valid = false;
      }
      if (!form.accountName.value.trim()) {
        Z.setFieldError(form.accountName, "Enter the account name.");
        valid = false;
      }
      if (Z.digitsOnly(form.accountNumber.value).length !== 10) {
        Z.setFieldError(form.accountNumber, "Enter a 10-digit account number.");
        valid = false;
      }
      if (!valid) {
        Z.showToast("Check the withdrawal form.", "error");
        return;
      }
      if (window.__zenvitaServer) {
        fetch("../api/withdrawal.php", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            amount: amount,
            bank: form.bank.value.trim(),
            accountName: form.accountName.value.trim(),
            accountNumber: Z.digitsOnly(form.accountNumber.value)
          })
        }).then(function (res) {
          return res.json();
        }).then(function (payload) {
          if (!payload.ok) {
            Z.showToast(payload.error || "The withdrawal could not be saved.", "error");
            return;
          }
          serverWallet.available = payload.available;
          serverWallet.withdrawable = payload.withdrawable;
          serverWallet.eagleLands = payload.eagleLands;
          serverWallet.withdrawals.unshift(payload.withdrawal);
          TRANSACTIONS.unshift(payload.transaction);
          if (payload.eagleTransaction) {
            TRANSACTIONS.unshift(payload.eagleTransaction);
          }
          form.reset();
          paint();
          Z.showToast(
            usesEagleSplit()
              ? "Withdrawal saved. 40% cash pending · 60% moved to Eagle Lands."
              : "Withdrawal saved. Full cash request pending (no Eagle Lands 40/60 split).",
            "info"
          );
        }).catch(function () {
          Z.showToast("The withdrawal server did not respond.", "error");
        });
        return;
      }
      var reserved = reservedFor(amount);
      data.available -= (amount + reserved);
      data.withdrawable = Math.floor(data.available * (usesEagleSplit() ? 0.4 : 1));
      data.eagleLands = (data.eagleLands || 0) + reserved;
      data.withdrawals.unshift({
        date: "06 Oct 2026",
        amount: amount,
        reserved: reserved,
        bank: form.bank.value.trim(),
        account: Z.digitsOnly(form.accountNumber.value),
        name: form.accountName.value.trim(),
        status: "Pending"
      });
      saveWallet(data);
      form.reset();
      paint();
      Z.showToast(
        usesEagleSplit()
          ? "Demo withdrawal saved with 40/60 Eagle Lands split."
          : "Demo withdrawal saved without Eagle Lands 40/60 split.",
        "info"
      );
    });
    paint();
  }

  function initEagleLands() {
    var eagle = document.getElementById("eagleLandsBalance");
    var walletEl = document.getElementById("eagleWalletBalance");
    var withdrawableEl = document.getElementById("eagleWithdrawable");
    var reserveBody = document.getElementById("eagleReserveBody");
    if (!eagle && !walletEl && !withdrawableEl && !reserveBody) {
      return;
    }
    var data = wallet();
    var eagleTotal = Number(data.eagleLands);
    if (!isFinite(eagleTotal)) {
      eagleTotal = 0;
    }
    if (eagle) {
      eagle.textContent = Z.formatNaira(eagleTotal);
    }
    if (walletEl) {
      walletEl.textContent = Z.formatNaira(data.available || 0);
    }
    if (withdrawableEl) {
      withdrawableEl.textContent = Z.formatNaira(data.withdrawable || 0);
    }
    if (reserveBody) {
      var rows = (data.withdrawals || []).filter(function (row) {
        return Number(row.reserved) > 0 && row.status !== "Rejected";
      });
      var empty = document.getElementById("eagleReserveEmpty");
      var wrap = document.getElementById("eagleReserveWrap");
      if (!rows.length) {
        reserveBody.innerHTML = "";
        if (empty) empty.hidden = false;
        if (wrap) wrap.hidden = true;
      } else {
        if (empty) empty.hidden = true;
        if (wrap) wrap.hidden = false;
        reserveBody.innerHTML = rows.map(function (row) {
          return "<tr><td>" + Z.escapeHtml(row.date) +
            "</td><td>" + Z.formatNaira(row.amount) +
            "</td><td>" + Z.formatNaira(row.reserved) +
            '</td><td><span class="' + Z.statusClass(row.status) + '">' + Z.escapeHtml(row.status) +
            "</span></td></tr>";
        }).join("");
      }
    }
  }

  function initTransactions() {
    var body = document.getElementById("txBody");
    if (!body) {
      return;
    }
    var page = 1;
    function paint() {
      var query = document.getElementById("txSearch").value.trim().toLowerCase();
      var type = document.getElementById("txType").value;
      var status = document.getElementById("txStatus").value;
      var rows = extraTransactions().concat(TRANSACTIONS).filter(function (row) {
        var text = (row.id + " " + row.description + " " + row.type).toLowerCase();
        return text.indexOf(query) !== -1 && (!type || row.type === type) && (!status || row.status === status);
      });
      var state = paginate(rows, page, 5);
      page = state.current;
      body.innerHTML = state.rows.map(function (row) {
        var sign = row.amount < 0 ? "−" : "";
        return "<tr><td>" + Z.escapeHtml(row.date) + "</td><td>" + Z.escapeHtml(row.id) + "</td><td>" + Z.escapeHtml(row.type) + "</td><td>" + Z.escapeHtml(row.description) + "</td><td>" + sign + Z.formatNaira(Math.abs(row.amount)) + '</td><td><span class="' + Z.statusClass(row.status) + '">' + Z.escapeHtml(row.status) + "</span></td></tr>";
      }).join("");
      document.getElementById("txEmpty").hidden = state.total > 0;
      document.getElementById("txTableWrap").hidden = state.total === 0;
      renderPager(document.getElementById("txPager"), state, function (next) {
        page = next;
        paint();
      });
    }
    ["txSearch", "txType", "txStatus"].forEach(function (id) {
      document.getElementById(id).addEventListener("input", function () {
        page = 1;
        paint();
      });
    });
    paint();
  }

  function initProfile() {
    var form = document.getElementById("profileForm");
    if (!form) {
      return;
    }
    var person = session();
    var bank = person.bank ? {
      bank: person.bank,
      accountName: person.accountName || ((person.firstName || "") + " " + (person.lastName || "")).trim(),
      accountNumber: person.accountNumber || ""
    } : Z.readStore("zenvitaBank", { bank: "Demo Bank", accountName: person.firstName + " " + person.lastName, accountNumber: "0123456789" });
    form.firstName.value = person.firstName || "";
    form.lastName.value = person.lastName || "";
    form.email.value = person.email || "";
    form.phone.value = person.phone || "";
    form.city.value = person.city || "Lagos";
    form.country.value = person.country || "Nigeria";
    form.referralId.value = person.memberCode || "ZGV12345";
    form.sponsor.value = person.sponsor || "Not supplied";
    form.bank.value = bank.bank;
    form.accountName.value = bank.accountName;
    form.accountNumber.value = bank.accountNumber;
    var editing = false;
    function setEditing(state) {
      editing = state;
      ["firstName", "lastName", "email", "phone", "city", "country", "bank", "accountName", "accountNumber"].forEach(function (name) {
        form[name].readOnly = !state;
      });
      document.getElementById("saveProfile").disabled = !state;
    }
    setEditing(false);
    document.getElementById("editProfile").addEventListener("click", function () {
      setEditing(true);
      form.firstName.focus();
    });
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      if (!editing) {
        return;
      }
      Z.clearFormErrors(form);
      var valid = true;
      if (!form.firstName.value.trim() || !form.lastName.value.trim()) {
        Z.setFieldError(form.firstName, "Enter your first and last name.");
        valid = false;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.value.trim())) {
        Z.setFieldError(form.email, "Enter a valid email address.");
        valid = false;
      }
      if (Z.digitsOnly(form.phone.value).length < 10) {
        Z.setFieldError(form.phone, "Enter a phone number with at least 10 digits.");
        valid = false;
      }
      if (Z.digitsOnly(form.accountNumber.value).length !== 10) {
        Z.setFieldError(form.accountNumber, "Enter a 10-digit account number.");
        valid = false;
      }
      if (!valid) {
        Z.showToast("Check the profile fields.", "error");
        return;
      }
      if (window.__zenvitaServer) {
        fetch("../api/profile.php", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            firstName: form.firstName.value.trim(),
            lastName: form.lastName.value.trim(),
            email: form.email.value.trim(),
            phone: form.phone.value.trim(),
            city: form.city.value.trim(),
            country: form.country.value.trim(),
            bank: form.bank.value.trim(),
            accountName: form.accountName.value.trim(),
            accountNumber: Z.digitsOnly(form.accountNumber.value)
          })
        }).then(function (res) {
          return res.json();
        }).then(function (payload) {
          if (!payload.ok) {
            Z.showToast(payload.error || "The profile could not be saved.", "error");
            return;
          }
          window.__zenvitaUser = payload.user;
          setEditing(false);
          fillIdentity();
          Z.showToast("Profile saved.");
        }).catch(function () {
          Z.showToast("The profile server did not respond.", "error");
        });
        return;
      }
      var next = session();
      next.firstName = form.firstName.value.trim();
      next.lastName = form.lastName.value.trim();
      next.email = form.email.value.trim();
      next.phone = form.phone.value.trim();
      next.city = form.city.value.trim();
      next.country = form.country.value.trim();
      Z.writeStore("zenvitaDemoSession", next);
      Z.writeStore("zenvitaBank", {
        bank: form.bank.value.trim(),
        accountName: form.accountName.value.trim(),
        accountNumber: Z.digitsOnly(form.accountNumber.value)
      });
      setEditing(false);
      fillIdentity();
      Z.showToast("Profile saved in this browser only.");
    });
    document.getElementById("passwordForm").addEventListener("submit", function (event) {
      event.preventDefault();
      var passwordForm = event.currentTarget;
      Z.clearFormErrors(passwordForm);
      var valid = true;
      if (passwordForm.currentPassword.value.length < 8) {
        Z.setFieldError(passwordForm.currentPassword, "Enter at least 8 characters.");
        valid = false;
      }
      if (passwordForm.newPassword.value.length < 8) {
        Z.setFieldError(passwordForm.newPassword, "Use at least 8 characters.");
        valid = false;
      }
      if (passwordForm.newPassword.value !== passwordForm.confirmNewPassword.value) {
        Z.setFieldError(passwordForm.confirmNewPassword, "Passwords do not match.");
        valid = false;
      }
      if (!valid) {
        return;
      }
      if (window.__zenvitaServer) {
        fetch("../api/password.php", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            currentPassword: passwordForm.currentPassword.value,
            newPassword: passwordForm.newPassword.value
          })
        }).then(function (res) {
          return res.json();
        }).then(function (payload) {
          if (!payload.ok) {
            Z.showToast(payload.error || "The password could not be changed.", "error");
            return;
          }
          passwordForm.reset();
          bootstrap.Modal.getOrCreateInstance(document.getElementById("passwordModal")).hide();
          Z.showToast("Password updated.");
        }).catch(function () {
          Z.showToast("The password server did not respond.", "error");
        });
        return;
      }
      passwordForm.reset();
      bootstrap.Modal.getOrCreateInstance(document.getElementById("passwordModal")).hide();
      Z.showToast("Password check passed. Nothing was stored, because this demo has no account server.", "info");
    });
  }

  function syncProfileTree() {
    var person = session();
    var pkg = currentPackage();
    TREE.name = (person.firstName || "Adaeze") + " " + (person.lastName || "Okonkwo");
    TREE.package = pkg.name;
    TREE.pv = pkg.pv;
  }

  function applyPayload(payload) {
    window.__zenvitaServer = true;
    window.__zenvitaUser = payload.user;
    window.__zenvitaMinWithdrawal = Number(payload.min_withdrawal) || 1000;
    if (payload.tree && payload.tree.id) {
      TREE = payload.tree;
      PEOPLE = flatten(TREE);
      TEAM = PEOPLE.filter(function (person) { return person.id !== TREE.id; });
    }
    if (payload.commissions) {
      COMMISSIONS = payload.commissions;
    }
    if (payload.transactions) {
      TRANSACTIONS = payload.transactions;
    }
    serverWallet = {
      available: Number(payload.available) || 0,
      withdrawable: Number(payload.withdrawable) || 0,
      eagleLands: Number(payload.eagleLands) || 0,
      withdrawalPolicy: payload.withdrawalPolicy || {
        isWithdrawDay: false,
        cashPercent: 100,
        reservePercent: 0,
        today: "",
        day: "Saturday",
        eagleLandsEligible: false,
        eagleLandsSplit: false
      },
      withdrawals: payload.withdrawals || []
    };
    window.__zenvitaCharts = payload.charts || {};
    (payload.packages || []).forEach(function (item) {
      Z.packages[item.key] = {
        key: item.key,
        name: item.name,
        description: item.description || "",
        price: Number(item.price),
        bottles: Number(item.bottles),
        pv: Number(item.pv),
        bonus: Number(item.bonus),
        matches: Number(item.matches),
        perMatch: Number(item.perMatch),
        dailyMax: Number(item.dailyMax)
      };
    });
    fillMemberPackageSelects(payload.packages || []);
  }

  function fillMemberPackageSelects(packages) {
    var options = packages.map(function (item) {
      return "<option value=\"" + Z.escapeHtml(item.name) + "\">" + Z.escapeHtml(item.name) + "</option>";
    }).join("");
    ["commissionPackage"].forEach(function (id) {
      var select = document.getElementById(id);
      if (!select || !options) {
        return;
      }
      var current = select.value;
      var keepAll = select.querySelector('option[value=""]');
      select.innerHTML = (keepAll ? keepAll.outerHTML : "") + options;
      if (current) {
        select.value = current;
      }
    });
  }

  function initAddUser() {
    var form = document.getElementById("addUserForm");
    if (!form) {
      return;
    }

    var code = session().memberCode || (window.__zenvitaUser && (window.__zenvitaUser.member_code || window.__zenvitaUser.username)) || "";
    var sponsorInput = document.getElementById("newSponsor");
    if (sponsorInput) {
      sponsorInput.value = code;
    }

    var base = new URL("../register", window.location.href);
    base.searchParams.set("ref", code || "");
    var leftUrl = new URL(base.href);
    leftUrl.searchParams.set("pt", "left");
    var rightUrl = new URL(base.href);
    rightUrl.searchParams.set("pt", "right");
    var leftLink = document.getElementById("addUserLeftLink");
    var rightLink = document.getElementById("addUserRightLink");
    var registerLink = document.getElementById("registerFormLink");
    if (leftLink) {
      leftLink.value = leftUrl.href;
    }
    if (rightLink) {
      rightLink.value = rightUrl.href;
    }
    if (registerLink) {
      registerLink.href = leftUrl.href;
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      Z.clearFormErrors(form);
      var username = form.username.value.trim();
      var password = form.password.value;
      var confirmPassword = form.confirmPassword ? form.confirmPassword.value : "";
      var sideNode = form.querySelector("input[name='side']:checked");
      var side = sideNode ? sideNode.value : "left";
      var terms = form.terms ? form.terms.checked : true;
      var valid = true;

      if (!form.firstName.value.trim()) {
        Z.setFieldError(form.firstName, "Enter the first name.");
        valid = false;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.value.trim())) {
        Z.setFieldError(form.email, "Enter a valid email address.");
        valid = false;
      }
      if (Z.digitsOnly(form.phone.value).length < 10) {
        Z.setFieldError(form.phone, "Enter a phone number with at least 10 digits.");
        valid = false;
      }
      if (!/^[A-Za-z0-9._-]{3,60}$/.test(username)) {
        Z.setFieldError(form.username, "Use at least 3 letters or numbers.");
        valid = false;
      }
      if (password.length < 8) {
        Z.setFieldError(form.password, "Use at least 8 characters.");
        valid = false;
      }
      if (form.confirmPassword && password !== confirmPassword) {
        Z.setFieldError(form.confirmPassword, "Passwords do not match.");
        valid = false;
      }
      if (form.terms && !terms) {
        Z.setFieldError(form.terms, "Please accept the terms to continue.");
        valid = false;
      }
      if (!form.package.value) {
        Z.setFieldError(form.package, "Choose a package.");
        valid = false;
      }
      if (!valid) {
        Z.showToast("Please correct the highlighted fields.", "error");
        return;
      }

      var button = document.getElementById("addUserSubmitBtn") || form.querySelector("[type='submit']");
      button.disabled = true;
      fetch("../api/add-user.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          sponsor: sponsorInput ? sponsorInput.value.trim() : code,
          firstName: form.firstName.value.trim(),
          lastName: form.lastName.value.trim(),
          email: form.email.value.trim(),
          phone: form.phone.value.trim(),
          username: username,
          password: password,
          package: form.package.value,
          side: side
        })
      }).then(function (res) {
        return res.json();
      }).then(function (payload) {
        button.disabled = false;
        if (!payload.ok) {
          Z.showToast(payload.error || "The account could not be created.", "error");
          return;
        }
        form.reset();
        if (sponsorInput) {
          sponsorInput.value = code;
        }
        if (form.querySelector("#newSideLeft")) {
          form.querySelector("#newSideLeft").checked = true;
        }
        if (form.terms) {
          form.terms.checked = true;
        }
        Z.showToast(payload.username + " can sign in with the password you set.");
        window.setTimeout(function () { window.location.reload(); }, 700);
      }).catch(function () {
        button.disabled = false;
        Z.showToast("The account server did not respond.", "error");
      });
    });
  }

  function initProductOrders() {
    var form = document.getElementById("productOrderForm");
    if (!form) {
      return;
    }
    var productSelect = document.getElementById("orderProduct");
    var productMeta = document.getElementById("orderProductMeta");
    var storeSelect = document.getElementById("orderStore");
    var storeMeta = document.getElementById("orderStoreMeta");
    var homeFields = document.getElementById("homeDeliveryFields");
    var pickupFields = document.getElementById("pickupFields");
    var historyBody = document.getElementById("memberOrderBody");
    var historyEmpty = document.getElementById("memberOrderEmpty");
    var catalog = { stores: [], products: [], orders: [], user: {} };

    function toggleFulfillment() {
      var mode = (form.querySelector("input[name='fulfillment']:checked") || {}).value;
      var isHome = mode === "home_delivery";
      homeFields.hidden = !isHome;
      pickupFields.hidden = isHome;
    }

    function paintHistory() {
      var rows = catalog.orders || [];
      historyEmpty.hidden = rows.length > 0;
      if (!rows.length) {
        historyBody.innerHTML = "";
        return;
      }
      historyBody.innerHTML = rows.map(function (row) {
        var productLabel = row.product || row.package || "";
        var priceLabel = row.unitPrice ? " · " + Z.formatNaira(row.unitPrice) : "";
        return "<tr><td>" + Z.escapeHtml(row.date) + "</td><td>" + Z.escapeHtml(row.reference) + "</td><td>" + Z.escapeHtml(productLabel) + priceLabel + "</td><td>" + Z.escapeHtml(row.store) + "</td><td>" + Z.escapeHtml(row.fulfillmentLabel) + '</td><td><span class="' + Z.statusClass(row.status) + '">' + Z.escapeHtml(row.status) + "</span></td></tr>";
      }).join("");
    }

    function paintSelects() {
      productSelect.innerHTML = (catalog.products || []).map(function (item) {
        return "<option value=\"" + Z.escapeHtml(item.key) + "\">" + Z.escapeHtml(item.name) + " · " + Z.formatNaira(item.price) + "</option>";
      }).join("") || "<option value=\"\">No products available</option>";
      storeSelect.innerHTML = (catalog.stores || []).map(function (item) {
        var label = item.storeCode
          ? item.storeCode + " · " + item.name
          : item.name;
        return "<option value=\"" + item.id + "\">" + Z.escapeHtml(label) + (item.city ? " · " + Z.escapeHtml(item.city) : "") + "</option>";
      }).join("") || "<option value=\"\">No active stores yet</option>";
      updateProductMeta();
      updateStoreMeta();
    }

    function updateProductMeta() {
      var product = (catalog.products || []).filter(function (item) {
        return item.key === productSelect.value;
      })[0];
      productMeta.textContent = product
        ? Z.formatNaira(product.price) + (product.description ? " · " + product.description : "")
        : "Choose Cellvigor or Vigormax capsule.";
      var img = document.getElementById("orderProductImage");
      if (img) {
        if (product && product.imageUrl) {
          img.src = "../" + product.imageUrl.replace(/^\.\.\//, "");
          img.alt = product.name || "";
          img.hidden = false;
        } else {
          img.removeAttribute("src");
          img.alt = "";
          img.hidden = true;
        }
      }
    }

    function updateStoreMeta() {
      var store = (catalog.stores || []).filter(function (item) {
        return String(item.id) === storeSelect.value;
      })[0];
      if (!store) {
        storeMeta.textContent = "Ask an admin to register an active store before booking.";
        return;
      }
      var details = [store.address, store.city, store.phone].filter(Boolean).join(" · ");
      storeMeta.textContent = store.storeCode
        ? ("Store ID " + store.storeCode + (details ? " · " + details : ""))
        : details;
    }

    function prefill() {
      var user = catalog.user || {};
      var sessionUser = window.__zenvitaUser || {};
      form.fullName.value = [user.first_name || sessionUser.first_name, user.last_name || sessionUser.last_name].filter(Boolean).join(" ").trim();
      form.phone.value = user.phone || sessionUser.phone || "";
      form.email.value = user.email || sessionUser.email || "";
      form.deliveryCity.value = user.city || sessionUser.city || "";
    }

    form.querySelectorAll("input[name='fulfillment']").forEach(function (input) {
      input.addEventListener("change", toggleFulfillment);
    });
    productSelect.addEventListener("change", updateProductMeta);
    storeSelect.addEventListener("change", updateStoreMeta);
    toggleFulfillment();

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      Z.clearFormErrors(form);
      var mode = (form.querySelector("input[name='fulfillment']:checked") || {}).value;
      var valid = true;
      if (!form.product.value) {
        Z.setFieldError(form.product, "Choose a product.");
        valid = false;
      }
      if (!form.storeId.value) {
        Z.setFieldError(form.storeId, "Choose a store.");
        valid = false;
      }
      if (!form.fullName.value.trim()) {
        Z.setFieldError(form.fullName, "Enter your full name.");
        valid = false;
      }
      if (!form.phone.value.trim()) {
        Z.setFieldError(form.phone, "Enter your phone number.");
        valid = false;
      }
      if (mode === "home_delivery") {
        if (!form.deliveryAddress.value.trim()) {
          Z.setFieldError(form.deliveryAddress, "Enter the delivery address.");
          valid = false;
        }
        if (!form.deliveryCity.value.trim()) {
          Z.setFieldError(form.deliveryCity, "Enter the city.");
          valid = false;
        }
      }
      if (!valid) {
        Z.showToast("Check the order form.", "error");
        return;
      }
      var button = document.getElementById("orderSubmitBtn");
      button.disabled = true;
      var notes = mode === "home_delivery"
        ? form.deliveryNotes.value.trim()
        : (document.getElementById("orderPickupNotes").value || "").trim();
      fetch("../api/order.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          product: form.product.value,
          storeId: Number(form.storeId.value),
          quantity: Number(form.quantity.value) || 1,
          fulfillment: mode,
          fullName: form.fullName.value.trim(),
          phone: form.phone.value.trim(),
          email: form.email.value.trim(),
          deliveryAddress: form.deliveryAddress.value.trim(),
          deliveryCity: form.deliveryCity.value.trim(),
          deliveryNotes: notes,
          preferredPickupAt: form.preferredPickupAt.value.trim()
        })
      }).then(function (res) {
        return res.json();
      }).then(function (payload) {
        button.disabled = false;
        if (!payload.ok) {
          Z.showToast(payload.error || "The booking could not be saved.", "error");
          return;
        }
        Z.showToast("Booking " + payload.order.reference + " submitted. The store was notified.");
        return loadOrders().then(function () {
          paintHistory();
        });
      }).catch(function () {
        button.disabled = false;
        Z.showToast("The order server did not respond.", "error");
      });
    });

    function loadOrders() {
      return fetch("../api/orders.php", { credentials: "same-origin" }).then(function (res) {
        if (res.status === 401) {
          window.location.href = "../login";
          return null;
        }
        return res.json();
      }).then(function (data) {
        if (!data || !data.ok) {
          throw new Error("orders");
        }
        catalog = data;
        paintSelects();
        prefill();
        paintHistory();
        return data;
      });
    }

    loadOrders().catch(function () {
      historyBody.innerHTML = '<tr><td class="empty-state" colspan="6">Could not load bookings.</td></tr>';
      Z.showToast("Could not load stores or products for ordering.", "error");
    });
  }

  function startPages() {
    syncProfileTree();
    fillIdentity();
    initNotifications();
    initOverview();
    initEagleLands();
    initTree("treeSearch");
    initNetworkPage();
    initCommissions();
    initPackagePage();
    initWithdrawals();
    initTransactions();
    initProfile();
    initAddUser();
    initProductOrders();
  }

  document.addEventListener("DOMContentLoaded", function () {
    fetch("../api/me.php", { credentials: "same-origin" })
      .then(function (res) {
        if (res.status === 401) {
          window.location.href = "../login";
          return null;
        }
        if (!res.ok) {
          throw new Error("session");
        }
        return res.json();
      })
      .then(function (me) {
        if (!me) {
          return null;
        }
        if (!me.ok || !me.user || me.user.role !== "member") {
          window.location.href = "../login";
          return null;
        }
        return fetch("../api/dashboard.php", { credentials: "same-origin" }).then(function (res) {
          if (res.status === 401) {
            window.location.href = "../login";
            return null;
          }
          return res.json();
        });
      })
      .then(function (dash) {
        if (!dash || !dash.ok) {
          if (dash) {
            window.location.href = "../login";
          }
          return;
        }
        applyPayload(dash);
        startPages();
      })
      .catch(function () {
        window.location.href = "../login";
      });
  });
})();
