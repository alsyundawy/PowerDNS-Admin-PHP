/**
 * PowerDNS-Admin-PHP Core JavaScript (Vanilla ES6+)
 * Zero dependencies, high performance, cross-browser compatible.
 */
document.addEventListener("DOMContentLoaded", () => {
  // Mobile sidebar toggle for small screens / Xiaomi / Redmi / Poco
  const toggleBtn = document.getElementById("sidebar-toggle");
  const sidebar = document.getElementById("app-sidebar");
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", () => {
      const isOpen = sidebar.classList.toggle("show");
      toggleBtn.setAttribute("aria-expanded", String(isOpen));
    });
  }

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
      if (target?.classList.contains("rm-row")) {
        const tr = target.closest("tr");
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
