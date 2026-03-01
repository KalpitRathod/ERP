// ================================================================
//  ERP System – Shared JavaScript
//  /erp/assets/js/main.js
// ================================================================

// ----- Accordion ------------------------------------------------
document.querySelectorAll(".accordion-header").forEach(function (hdr) {
  hdr.addEventListener("click", function () {
    var body = this.nextElementSibling;
    if (body && body.classList.contains("accordion-body")) {
      body.classList.toggle("open");
    }
  });
});

// ----- Tabs -----------------------------------------------------
document.querySelectorAll(".tab-btn").forEach(function (btn) {
  btn.addEventListener("click", function () {
    var target = this.dataset.tab;
    document.querySelectorAll(".tab-btn").forEach(function (b) {
      b.classList.remove("active");
    });
    document.querySelectorAll(".tab-content").forEach(function (c) {
      c.classList.remove("active");
    });
    this.classList.add("active");
    var tc = document.getElementById(target);
    if (tc) tc.classList.add("active");
  });
});

// ----- Confirm delete / action ----------------------------------
document.querySelectorAll("[data-confirm]").forEach(function (el) {
  el.addEventListener("click", function (e) {
    if (!confirm(this.dataset.confirm || "Are you sure?")) {
      e.preventDefault();
    }
  });
});

// ----- Auto-dismiss flash messages after 5s -------------------
var flash = document.querySelector(".alert");
if (flash) {
  setTimeout(function () {
    flash.style.display = "none";
  }, 5000);
}

// ----- Generic inline form toggle ------------------------------
window.toggleSection = function (id) {
  var el = document.getElementById(id);
  if (el) el.style.display = el.style.display === "none" ? "block" : "none";
};
