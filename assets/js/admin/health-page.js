/**
 * Store Health Page JavaScript
 *
 * @package Assistify_For_WooCommerce
 */

(function ($) {
  "use strict";

  var AssistifyHealth = {
    /**
     * Initialize
     */
    init: function () {
      this.bindEvents();
      this.initScoreAnimation();
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      // Quick action buttons
      $(document).on(
        "click",
        ".assistify-action-button",
        this.handleAction.bind(this)
      );

      // Refresh button
      $("#assistify-refresh-health").on("click", this.refreshHealth.bind(this));

      // AI recommendations
      $("#assistify-get-recommendations").on(
        "click",
        this.getRecommendations.bind(this)
      );

      // Mark as resolved buttons
      $(document).on(
        "click",
        ".assistify-resolve-button",
        this.resolveIssue.bind(this)
      );
    },

    /**
     * Mark issue as resolved
     */
    resolveIssue: function (e) {
      e.preventDefault();

      var $button = $(e.currentTarget);
      var issueKey = $button.data("issue-key");
      var $issueItem = $button.closest(".assistify-issue-item");
      var issueTitle = $issueItem.find("h4").text();

      if (!issueKey || $button.hasClass("running")) {
        return;
      }

      // Show WordPress-style modal
      AssistifyHealth.showResolveModal(issueTitle, function () {
        $button.addClass("running").text("...");

        $.ajax({
          url: assistifyHealth.ajaxUrl,
          type: "POST",
          data: {
            action: "assistify_resolve_issue",
            issue_key: issueKey,
            nonce: assistifyHealth.nonce,
          },
          success: function (response) {
            if (response.success) {
              // Fade out the issue item
              $issueItem.slideUp(300, function () {
                $(this).remove();

                // Check if section is now empty
                $(".assistify-issues-list").each(function () {
                  if ($(this).children().length === 0) {
                    $(this)
                      .closest(".assistify-health-section")
                      .slideUp(300, function () {
                        $(this).remove();
                      });
                  }
                });

                // Show notice
                AssistifyHealth.showNotice(response.data.message, "success");
              });
            } else {
              $button.removeClass("running").text("Resolved");
              AssistifyHealth.showNotice(
                response.data.message || "Failed to mark as resolved",
                "error"
              );
            }
          },
          error: function () {
            $button.removeClass("running").text("Resolved");
            AssistifyHealth.showNotice(
              "Request failed. Please try again.",
              "error"
            );
          },
        });
      });
    },

    /**
     * Show WordPress-style resolve confirmation modal
     */
    showResolveModal: function (issueTitle, onConfirm) {
      // Remove existing modal if any
      $("#assistify-resolve-modal").remove();

      var modalHtml =
        '<div id="assistify-resolve-modal" class="assistify-modal-overlay">' +
        '<div class="assistify-modal">' +
        '<div class="assistify-modal-header">' +
        "<h2>Mark as Resolved</h2>" +
        '<button type="button" class="assistify-modal-close">&times;</button>' +
        "</div>" +
        '<div class="assistify-modal-body">' +
        "<p><strong>" +
        this.escapeHtml(issueTitle) +
        "</strong></p>" +
        "<p>This issue will be hidden from the health report. If new occurrences are detected, it will reappear.</p>" +
        "</div>" +
        '<div class="assistify-modal-footer">' +
        '<button type="button" class="button assistify-modal-cancel">Cancel</button>' +
        '<button type="button" class="button button-primary assistify-modal-confirm">Mark Resolved</button>' +
        "</div>" +
        "</div>" +
        "</div>";

      $("body").append(modalHtml);

      var $modal = $("#assistify-resolve-modal");

      // Show with animation
      setTimeout(function () {
        $modal.addClass("active");
      }, 10);

      // Bind events
      $modal.on(
        "click",
        ".assistify-modal-close, .assistify-modal-cancel",
        function () {
          $modal.removeClass("active");
          setTimeout(function () {
            $modal.remove();
          }, 200);
        }
      );

      $modal.on("click", ".assistify-modal-confirm", function () {
        $modal.removeClass("active");
        setTimeout(function () {
          $modal.remove();
        }, 200);
        onConfirm();
      });

      // Close on overlay click
      $modal.on("click", function (e) {
        if ($(e.target).hasClass("assistify-modal-overlay")) {
          $modal.removeClass("active");
          setTimeout(function () {
            $modal.remove();
          }, 200);
        }
      });

      // Close on Escape key
      $(document).on("keydown.assistifyModal", function (e) {
        if (e.key === "Escape") {
          $modal.removeClass("active");
          setTimeout(function () {
            $modal.remove();
          }, 200);
          $(document).off("keydown.assistifyModal");
        }
      });
    },

    /**
     * Initialize score animation
     */
    initScoreAnimation: function () {
      var $circle = $(".assistify-score-circle");
      if ($circle.length) {
        var score = parseInt(
          $circle.find(".assistify-score-number").text(),
          10
        );
        $circle.css("--score", score);
      }
    },

    /**
     * Handle action button click
     */
    handleAction: function (e) {
      e.preventDefault();

      var $button = $(e.currentTarget);
      var action = $button.data("action");

      if (!action || $button.hasClass("running")) {
        return;
      }

      var originalText = $button.text();
      $button.addClass("running").text(assistifyHealth.strings.running);

      $.ajax({
        url: assistifyHealth.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_health_action",
          health_action: action,
          nonce: assistifyHealth.nonce,
        },
        success: function (response) {
          if (response.success) {
            $button.text(assistifyHealth.strings.success);
            setTimeout(function () {
              $button.removeClass("running").text(originalText);
            }, 2000);

            // Show result message
            if (response.data && response.data.message) {
              AssistifyHealth.showNotice(response.data.message, "success");
            }

            // Refresh if score changed
            if (response.data && response.data.score !== undefined) {
              location.reload();
            }
          } else {
            $button.text(assistifyHealth.strings.error);
            setTimeout(function () {
              $button.removeClass("running").text(originalText);
            }, 2000);

            if (response.data && response.data.message) {
              AssistifyHealth.showNotice(response.data.message, "error");
            }
          }
        },
        error: function () {
          $button.removeClass("running").text(originalText);
          AssistifyHealth.showNotice(
            "Request failed. Please try again.",
            "error"
          );
        },
      });
    },

    /**
     * Refresh health data
     */
    refreshHealth: function (e) {
      e.preventDefault();

      var $button = $(e.currentTarget);
      var originalText = $button.text();

      $button
        .addClass("updating-message")
        .text(assistifyHealth.strings.refreshing);

      $.ajax({
        url: assistifyHealth.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_health_action",
          health_action: "refresh_health",
          nonce: assistifyHealth.nonce,
        },
        success: function () {
          location.reload();
        },
        error: function () {
          $button.removeClass("updating-message").text(originalText);
          AssistifyHealth.showNotice(
            "Failed to refresh. Please try again.",
            "error"
          );
        },
      });
    },

    /**
     * Get AI recommendations
     */
    getRecommendations: function (e) {
      e.preventDefault();

      var $button = $(e.currentTarget);
      var $container = $("#assistify-ai-recommendations");

      $button.hide();
      $container.html(
        '<div class="assistify-ai-loading">' +
          '<span class="spinner is-active" style="float:none;"></span>' +
          assistifyHealth.strings.analyzing +
          "</div>"
      );

      $.ajax({
        url: assistifyHealth.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_get_ai_recommendations",
          nonce: assistifyHealth.nonce,
        },
        success: function (response) {
          if (response.success && response.data.recommendations) {
            AssistifyHealth.renderRecommendations(
              response.data.recommendations,
              $container
            );
          } else {
            $container.html(
              '<p class="assistify-ai-error">Unable to generate recommendations. Please try again.</p>'
            );
            $button.show();
          }
        },
        error: function () {
          $container.html(
            '<p class="assistify-ai-error">Request failed. Please try again.</p>'
          );
          $button.show();
        },
      });
    },

    /**
     * Render AI recommendations
     */
    renderRecommendations: function (recommendations, $container) {
      if (!recommendations || !recommendations.length) {
        $container.html("<p>No specific recommendations at this time.</p>");
        return;
      }

      var html = '<ul class="assistify-ai-recommendations">';

      recommendations.forEach(function (rec) {
        var priorityClass = "priority-" + (rec.priority || "medium");
        var priorityLabel =
          rec.priority === "high"
            ? "High Priority"
            : rec.priority === "low"
            ? "Low Priority"
            : "Medium Priority";

        html +=
          '<li class="assistify-ai-recommendation ' +
          priorityClass +
          '">' +
          '<span class="assistify-rec-priority">' +
          priorityLabel +
          "</span>" +
          "<h4>" +
          AssistifyHealth.escapeHtml(rec.title || "Recommendation") +
          "</h4>";

        if (rec.action) {
          html +=
            '<p class="assistify-rec-action"><strong>Action:</strong> ' +
            AssistifyHealth.escapeHtml(rec.action) +
            "</p>";
        }

        if (rec.impact) {
          html +=
            '<p class="assistify-rec-impact"><strong>Impact:</strong> ' +
            AssistifyHealth.escapeHtml(rec.impact) +
            "</p>";
        }

        // Fallback for old format with message field.
        if (!rec.action && !rec.impact && rec.message) {
          html +=
            "<p>" + AssistifyHealth.escapeHtml(rec.message || "") + "</p>";
        }

        html += "</li>";
      });

      html += "</ul>";
      html +=
        '<button type="button" class="button" id="assistify-refresh-recommendations" style="margin-top: 15px;">Refresh</button>';

      $container.html(html);

      // Bind refresh button
      $("#assistify-refresh-recommendations").on("click", function () {
        $("#assistify-get-recommendations").show();
        $container.html(
          '<p class="assistify-ai-placeholder">Get personalized recommendations based on your store data.</p>'
        );
        $container.append($("#assistify-get-recommendations"));
      });
    },

    /**
     * Show admin notice
     */
    showNotice: function (message, type) {
      var $notice = $(
        '<div class="notice notice-' +
          type +
          ' is-dismissible"><p>' +
          this.escapeHtml(message) +
          "</p></div>"
      );

      $(".assistify-health-page h1").after($notice);

      // Auto dismiss after 5 seconds
      setTimeout(function () {
        $notice.fadeOut(function () {
          $(this).remove();
        });
      }, 5000);

      // Add dismiss button functionality
      $notice.on("click", ".notice-dismiss", function () {
        $notice.fadeOut(function () {
          $(this).remove();
        });
      });
    },

    /**
     * Escape HTML
     */
    escapeHtml: function (text) {
      var div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    AssistifyHealth.init();
  });
})(jQuery);
