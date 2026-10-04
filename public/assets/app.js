/**
 * PowerDNS-Admin-PHP Core JavaScript (Vanilla ES6+)
 * Zero dependencies, high performance, cross-browser compatible.
 * Enhanced with 2026 Dark/Light theme switching & network tools utilities.
 */
document.addEventListener("DOMContentLoaded", () => {
  // Theme Switcher Controller (2026 Dark Mode Default)
  const themeToggleBtns = document.querySelectorAll(".theme-toggle-btn");

  const updateThemeUI = (theme) => {
    themeToggleBtns.forEach((btn) => {
      const icon = btn.querySelector("i");
      const label = btn.querySelector(".theme-label");
      if (theme === "light") {
        if (icon) icon.className = "fa-solid fa-moon text-warning";
        if (label) label.textContent = "Light Mode";
        btn.setAttribute("aria-label", "Switch to Dark Mode");
      } else {
        if (icon) icon.className = "fa-solid fa-sun text-warning";
        if (label) label.textContent = "Dark Mode";
        btn.setAttribute("aria-label", "Switch to Light Mode");
      }
    });
  };

  const currentTheme =
    document.documentElement.dataset.theme ||
    localStorage.getItem("pdns_theme") ||
    "dark";
  updateThemeUI(currentTheme);

  themeToggleBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const active = document.documentElement.dataset.theme || "dark";
      const nextTheme = active === "dark" ? "light" : "dark";
      document.documentElement.dataset.theme = nextTheme;
      localStorage.setItem("pdns_theme", nextTheme);
      updateThemeUI(nextTheme);
    });
  });

  // Mobile sidebar toggle for small screens / Xiaomi / Redmi / Poco
  const toggleBtn = document.getElementById("sidebar-toggle");
  const sidebar = document.getElementById("app-sidebar");
  const backdrop = document.getElementById("sidebar-backdrop");

  const closeSidebar = () => {
    if (sidebar) sidebar.classList.remove("show");
    if (toggleBtn) toggleBtn.setAttribute("aria-expanded", "false");
    document.body.classList.remove("sidebar-open");
  };

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", () => {
      const isOpen = sidebar.classList.toggle("show");
      toggleBtn.setAttribute("aria-expanded", String(isOpen));
      document.body.classList.toggle("sidebar-open", isOpen);
    });
  }

  if (backdrop) {
    backdrop.addEventListener("click", closeSidebar);
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && sidebar?.classList.contains("show")) {
      closeSidebar();
    }
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth >= 992) {
      closeSidebar();
    }
  });

  // Copy to clipboard utility for tools
  const copyButtons = document.querySelectorAll(".btn-copy-target");
  copyButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      const targetId = btn.dataset.target;
      if (!targetId) return;
      const targetEl = document.getElementById(targetId);
      if (!targetEl) return;
      const textToCopy =
        targetEl.tagName === "INPUT" || targetEl.tagName === "TEXTAREA"
          ? targetEl.value
          : targetEl.textContent || "";

      navigator.clipboard
        .writeText(textToCopy.trim())
        .then(() => {
          const originalHtml = btn.innerHTML;
          btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
          setTimeout(() => {
            btn.innerHTML = originalHtml;
          }, 2000);
        })
        .catch(() => {
          // Fallback if clipboard API is restricted
          console.warn("Clipboard access denied");
        });
    });
  });

  // Add DNS record row from template
  const addRowBtn = document.getElementById("add-row");
  const recordTable = document.getElementById("record-table");
  const template = document.getElementById("row-template");
  if (addRowBtn && recordTable && template) {
    addRowBtn.addEventListener("click", () => {
      const tbody = recordTable.querySelector("tbody");
      if (tbody) {
        const clone = template.content.cloneNode(true);
        tbody.appendChild(clone);
      }
    });
  }

  // Delete row delegation
  if (recordTable) {
    recordTable.addEventListener("click", (e) => {
      const target = e.target;
      const btn = target?.closest(".rm-row");
      if (btn) {
        const tr = btn.closest("tr");
        if (tr) {
          tr.remove();
        }
      }
    });
  }

  // Real-time record search filtering
  const filterInput = document.getElementById("record-filter");
  if (filterInput && recordTable) {
    filterInput.addEventListener("input", () => {
      const q = filterInput.value.trim().toLowerCase();
      const rows = recordTable.querySelectorAll("tbody tr");
      rows.forEach((tr) => {
        const inputs = Array.from(tr.querySelectorAll("input, select"))
          .map((el) => el.value)
          .join(" ");
        const text = (tr.textContent + " " + inputs).toLowerCase();
        tr.style.display = text.includes(q) ? "" : "none";
      });
    });
  }

  // Record form submission: synchronize indices to prevent checkbox drift
  const recordForm = document.getElementById("record-form");
  if (recordForm && recordTable) {
    recordForm.addEventListener("submit", () => {
      const rows = recordTable.querySelectorAll("tbody tr");
      rows.forEach((tr, idx) => {
        const nameInput = tr.querySelector('input[name^="r_name"]');
        const typeSelect = tr.querySelector('select[name^="r_type"]');
        const ttlInput = tr.querySelector('input[name^="r_ttl"]');
        const contentInput = tr.querySelector('input[name^="r_content"]');
        const commentInput = tr.querySelector('input[name^="r_comment"]');
        const checkbox = tr.querySelector('input[type="checkbox"]');

        if (nameInput) nameInput.name = `r_name[${idx}]`;
        if (typeSelect) typeSelect.name = `r_type[${idx}]`;
        if (ttlInput) ttlInput.name = `r_ttl[${idx}]`;
        if (contentInput) contentInput.name = `r_content[${idx}]`;
        if (commentInput) commentInput.name = `r_comment[${idx}]`;

        if (checkbox) {
          checkbox.name = `r_disabled[${idx}]`;
          checkbox.value = "1";
          // Clean existing hidden disabled inputs in this row to avoid collision
          tr.querySelectorAll(
            'input[type="hidden"][name^="r_disabled"]',
          ).forEach((el) => el.remove());
          if (!checkbox.checked) {
            const hidden = document.createElement("input");
            hidden.type = "hidden";
            hidden.name = `r_disabled[${idx}]`;
            hidden.value = "0";
            if (checkbox.parentNode) {
              checkbox.parentNode.insertBefore(hidden, checkbox);
            }
          }
        }
      });
    });
  }
});
