/**
 * Assistify Conversion Tracker
 *
 * Tracks product page views for conversion analytics.
 * Uses AJAX to work with cached pages.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

(function ($) {
  "use strict";

  var AssistifyTracker = {
    /**
     * Session storage key to prevent duplicate views.
     */
    storageKey: "assistify_viewed_",

    /**
     * Initialize tracker.
     */
    init: function () {
      if (typeof assistifyTracker === "undefined") {
        return;
      }

      // Check if already viewed in this session.
      if (this.hasViewedInSession()) {
        return;
      }

      // Track the view.
      this.trackView();
    },

    /**
     * Check if product was already viewed in this session.
     */
    hasViewedInSession: function () {
      var key = this.storageKey + assistifyTracker.productId;

      try {
        return sessionStorage.getItem(key) === "1";
      } catch (e) {
        return false;
      }
    },

    /**
     * Mark product as viewed in this session.
     */
    markAsViewed: function () {
      var key = this.storageKey + assistifyTracker.productId;

      try {
        sessionStorage.setItem(key, "1");
      } catch (e) {
        // Session storage not available.
      }
    },

    /**
     * Send view tracking request.
     */
    trackView: function () {
      var self = this;

      $.ajax({
        url: assistifyTracker.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_track_view",
          nonce: assistifyTracker.nonce,
          product_id: assistifyTracker.productId,
        },
        success: function () {
          self.markAsViewed();
        },
      });
    },
  };

  // Initialize on DOM ready.
  $(document).ready(function () {
    AssistifyTracker.init();
  });
})(jQuery);
