/**
 * Assistify Traffic Tracker
 *
 * Captures UTM parameters and referrer for attribution analytics.
 * Works with cached pages via AJAX.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

(function ($) {
  "use strict";

  var AssistifyTrafficTracker = {
    /**
     * Storage key to prevent duplicate captures.
     */
    storageKey: "assistify_traffic_captured",

    /**
     * UTM parameters to track.
     */
    utmParams: [
      "utm_source",
      "utm_medium",
      "utm_campaign",
      "utm_term",
      "utm_content",
    ],

    /**
     * Initialize tracker.
     */
    init: function () {
      if (typeof assistifyTraffic === "undefined") {
        return;
      }

      // Check if already captured in this session.
      if (this.hasCapuredInSession()) {
        return;
      }

      // Check if we have UTM params or external referrer.
      var trafficData = this.getTrafficData();

      if (Object.keys(trafficData).length > 0) {
        this.sendTrafficData(trafficData);
      }
    },

    /**
     * Check if traffic was already captured.
     */
    hasCapuredInSession: function () {
      try {
        return sessionStorage.getItem(this.storageKey) === "1";
      } catch (e) {
        return false;
      }
    },

    /**
     * Mark traffic as captured.
     */
    markAsCaptured: function () {
      try {
        sessionStorage.setItem(this.storageKey, "1");
      } catch (e) {
        // Session storage not available.
      }
    },

    /**
     * Get traffic data from URL and document.
     */
    getTrafficData: function () {
      var data = {};
      var urlParams = new URLSearchParams(window.location.search);

      // Capture UTM parameters.
      for (var i = 0; i < this.utmParams.length; i++) {
        var param = this.utmParams[i];
        if (urlParams.has(param)) {
          data[param] = urlParams.get(param);
        }
      }

      // Capture referrer if no UTM params.
      if (Object.keys(data).length === 0 && document.referrer) {
        var referrerHost = this.getHostFromUrl(document.referrer);
        var currentHost = window.location.hostname;

        // Only track external referrers.
        if (referrerHost && referrerHost !== currentHost) {
          data.referrer = document.referrer;
        }
      }

      // Add landing page.
      if (Object.keys(data).length > 0) {
        data.landing_page = window.location.pathname;
      }

      return data;
    },

    /**
     * Extract hostname from URL.
     */
    getHostFromUrl: function (url) {
      try {
        return new URL(url).hostname;
      } catch (e) {
        return null;
      }
    },

    /**
     * Send traffic data to server.
     */
    sendTrafficData: function (data) {
      var self = this;

      data.action = "assistify_capture_traffic";
      data.nonce = assistifyTraffic.nonce;

      $.ajax({
        url: assistifyTraffic.ajaxUrl,
        type: "POST",
        data: data,
        success: function () {
          self.markAsCaptured();
        },
      });
    },
  };

  // Initialize on DOM ready.
  $(document).ready(function () {
    AssistifyTrafficTracker.init();
  });
})(jQuery);
