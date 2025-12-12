/**
 * Audit Log Admin JavaScript
 *
 * @package Assistify_For_WooCommerce
 * @since 1.1.0
 */

(function ($) {
  "use strict";

  const AuditLog = {
    currentPage: 1,
    totalPages: 1,
    perPage: 50,
    filters: {
      category: "",
      status: "",
      search: "",
    },

    /**
     * Initialize the audit log viewer.
     */
    init: function () {
      this.bindEvents();
      this.loadLogs();
    },

    /**
     * Bind event handlers.
     */
    bindEvents: function () {
      // Filter button.
      $("#assistify-filter-apply").on("click", () => {
        this.applyFilters();
      });

      // Search on enter.
      $("#assistify-filter-search").on("keypress", (e) => {
        if (e.which === 13) {
          this.applyFilters();
        }
      });

      // Pagination.
      $("#assistify-prev-page").on("click", () => {
        if (this.currentPage > 1) {
          this.currentPage--;
          this.loadLogs();
        }
      });

      $("#assistify-next-page").on("click", () => {
        if (this.currentPage < this.totalPages) {
          this.currentPage++;
          this.loadLogs();
        }
      });

      // Modal close.
      $(".assistify-modal-close").on("click", () => {
        this.closeModal();
      });

      // Close modal on backdrop click.
      $(".assistify-modal").on("click", (e) => {
        if ($(e.target).hasClass("assistify-modal")) {
          this.closeModal();
        }
      });

      // Close modal on escape.
      $(document).on("keydown", (e) => {
        if (e.key === "Escape") {
          this.closeModal();
        }
      });

      // View details click (delegated).
      $(document).on("click", ".assistify-view-details", (e) => {
        e.preventDefault();
        const logId = $(e.currentTarget).data("log-id");
        const logData = $(e.currentTarget).data("log");
        this.showDetails(logData);
      });
    },

    /**
     * Apply filters and reload.
     */
    applyFilters: function () {
      this.filters.category = $("#assistify-filter-category").val();
      this.filters.status = $("#assistify-filter-status").val();
      this.filters.search = $("#assistify-filter-search").val();
      this.currentPage = 1;
      this.loadLogs();
    },

    /**
     * Load logs from API.
     */
    loadLogs: function () {
      const $tbody = $("#assistify-audit-logs");
      $tbody.html(
        '<tr><td colspan="6" class="assistify-loading">' +
          assistifyAuditLog.i18n.loading +
          "</td></tr>"
      );

      const params = new URLSearchParams({
        page: this.currentPage,
        per_page: this.perPage,
      });

      if (this.filters.category) {
        params.append("category", this.filters.category);
      }
      if (this.filters.status) {
        params.append("status", this.filters.status);
      }
      if (this.filters.search) {
        params.append("search", this.filters.search);
      }

      $.ajax({
        url: assistifyAuditLog.restUrl + "?" + params.toString(),
        method: "GET",
        headers: {
          "X-WP-Nonce": assistifyAuditLog.nonce,
        },
        success: (response) => {
          this.renderLogs(response.logs);
          this.totalPages = response.total_pages;
          this.updatePagination(response);
        },
        error: () => {
          $tbody.html(
            '<tr><td colspan="6" class="assistify-loading">' +
              assistifyAuditLog.i18n.error +
              "</td></tr>"
          );
        },
      });
    },

    /**
     * Render log rows.
     */
    renderLogs: function (logs) {
      const $tbody = $("#assistify-audit-logs");

      if (!logs || logs.length === 0) {
        const emptyIcon =
          '<div class="assistify-no-logs-icon">' +
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">' +
          '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>' +
          "</svg>" +
          "</div>";
        $tbody.html(
          '<tr><td colspan="6" class="assistify-no-logs">' +
            emptyIcon +
            assistifyAuditLog.i18n.noLogs +
            "</td></tr>"
        );
        return;
      }

      let html = "";

      logs.forEach((log) => {
        const statusClass = "status-" + log.status;
        const statusLabel = this.getStatusLabel(log.status);

        html += `
          <tr>
            <td class="column-time">
              <span class="assistify-time">${log.time_ago} ago</span>
              <span class="assistify-time-full">${log.created_at}</span>
            </td>
            <td class="column-user">
              <div class="assistify-user-info">
                <span class="assistify-user-name">${this.escapeHtml(log.user_name)}</span>
              </div>
              <span class="assistify-user-type">${log.user_type}</span>
            </td>
            <td class="column-action">
              <span class="assistify-category-badge">${log.action_category}</span>
            </td>
            <td class="column-description">
              ${this.escapeHtml(log.description)}
              ${log.ability_id ? `<br><small style="color:#646970">${log.ability_id}</small>` : ""}
            </td>
            <td class="column-status">
              <span class="assistify-status-badge ${statusClass}">${statusLabel}</span>
            </td>
            <td class="column-details">
              <a href="#" class="assistify-view-details" 
                 data-log-id="${log.id}"
                 data-log='${JSON.stringify(log).replace(/'/g, "&#39;")}'>
                ${assistifyAuditLog.i18n.viewMore}
              </a>
            </td>
          </tr>
        `;
      });

      $tbody.html(html);
    },

    /**
     * Update pagination controls.
     */
    updatePagination: function (response) {
      const pageInfo = `Page ${response.page} of ${response.total_pages} (${response.total} total)`;
      $("#assistify-page-info").text(pageInfo);

      $("#assistify-prev-page").prop("disabled", response.page <= 1);
      $("#assistify-next-page").prop(
        "disabled",
        response.page >= response.total_pages
      );
    },

    /**
     * Get status label.
     */
    getStatusLabel: function (status) {
      const labels = {
        success: assistifyAuditLog.i18n.success,
        failed: assistifyAuditLog.i18n.failed,
        pending: assistifyAuditLog.i18n.pending,
      };
      return labels[status] || status;
    },

    /**
     * Show log details modal.
     */
    showDetails: function (log) {
      let html = "";

      html += `
        <div class="assistify-detail-row">
          <span class="assistify-detail-label">ID</span>
          <span class="assistify-detail-value">#${log.id}</span>
        </div>
        <div class="assistify-detail-row">
          <span class="assistify-detail-label">Time</span>
          <span class="assistify-detail-value">${log.created_at}</span>
        </div>
        <div class="assistify-detail-row">
          <span class="assistify-detail-label">User</span>
          <span class="assistify-detail-value">${this.escapeHtml(log.user_name)} (${log.user_type})</span>
        </div>
        <div class="assistify-detail-row">
          <span class="assistify-detail-label">Category</span>
          <span class="assistify-detail-value">${log.action_category}</span>
        </div>
        <div class="assistify-detail-row">
          <span class="assistify-detail-label">Action</span>
          <span class="assistify-detail-value">${log.action_type}</span>
        </div>
        <div class="assistify-detail-row">
          <span class="assistify-detail-label">Description</span>
          <span class="assistify-detail-value">${this.escapeHtml(log.description)}</span>
        </div>
        <div class="assistify-detail-row">
          <span class="assistify-detail-label">Status</span>
          <span class="assistify-detail-value">
            <span class="assistify-status-badge status-${log.status}">${this.getStatusLabel(log.status)}</span>
          </span>
        </div>
      `;

      if (log.ability_id) {
        html += `
          <div class="assistify-detail-row">
            <span class="assistify-detail-label">Ability ID</span>
            <span class="assistify-detail-value"><code>${log.ability_id}</code></span>
          </div>
        `;
      }

      if (log.ip_address) {
        html += `
          <div class="assistify-detail-row">
            <span class="assistify-detail-label">IP Address</span>
            <span class="assistify-detail-value">${log.ip_address}</span>
          </div>
        `;
      }

      if (log.object_type && log.object_id) {
        html += `
          <div class="assistify-detail-row">
            <span class="assistify-detail-label">Object</span>
            <span class="assistify-detail-value">${log.object_type} #${log.object_id}</span>
          </div>
        `;
      }

      if (log.parameters && Object.keys(log.parameters).length > 0) {
        html += `
          <div class="assistify-detail-row">
            <span class="assistify-detail-label">${assistifyAuditLog.i18n.parameters}</span>
            <span class="assistify-detail-value">
              <pre class="assistify-detail-code">${JSON.stringify(log.parameters, null, 2)}</pre>
            </span>
          </div>
        `;
      }

      if (log.result && Object.keys(log.result).length > 0) {
        html += `
          <div class="assistify-detail-row">
            <span class="assistify-detail-label">${assistifyAuditLog.i18n.result}</span>
            <span class="assistify-detail-value">
              <pre class="assistify-detail-code">${JSON.stringify(log.result, null, 2)}</pre>
            </span>
          </div>
        `;
      }

      $("#assistify-log-detail-content").html(html);
      $("#assistify-log-detail-modal").show();
    },

    /**
     * Close modal.
     */
    closeModal: function () {
      $("#assistify-log-detail-modal").hide();
    },

    /**
     * Escape HTML.
     */
    escapeHtml: function (text) {
      if (!text) return "";
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },
  };

  // Initialize on document ready.
  $(document).ready(function () {
    if ($("#assistify-audit-logs").length) {
      AuditLog.init();
    }
  });
})(jQuery);

