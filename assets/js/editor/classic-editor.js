/**
 * Assistify AI Classic Editor JavaScript.
 *
 * Handles content generation in Classic Editor for posts, pages, and products.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

/* global jQuery, ajaxurl, assistifyEditor */
(function ($) {
  "use strict";

  var AssistifyClassicEditor = {
    config: {},
    state: {
      currentType: null,
    },

    /**
     * Initialize the editor.
     */
    init: function () {
      if (typeof assistifyEditor === "undefined") {
        return;
      }

      this.config = assistifyEditor;
      this.bindEvents();
      this.addImageButtons();
    },

    /**
     * Bind event handlers.
     */
    bindEvents: function () {
      var self = this;

      // Generate button click - always show instructions.
      $(document).on("click", ".assistify-generate-btn", function (e) {
        e.preventDefault();
        self.state.currentType = $(this).data("type");
        var label =
          self.config.typeLabels[self.state.currentType] ||
          self.state.currentType;
        $("#assistify-instructions-label").text(
          label + " - " + self.config.strings.instructionsOptional
        );
        $("#assistify-instructions").show();
        $("#assistify-prompt").focus();
      });

      // Submit - instructions are optional.
      $(document).on("click", "#assistify-submit", function (e) {
        e.preventDefault();
        var prompt = $("#assistify-prompt").val();
        $("#assistify-instructions").hide();
        self.doGenerate(prompt);
      });

      // Cancel.
      $(document).on("click", "#assistify-cancel", function (e) {
        e.preventDefault();
        $("#assistify-instructions").hide();
        $("#assistify-prompt").val("");
      });

      // Regenerate.
      $(document).on("click", "#assistify-regenerate", function (e) {
        e.preventDefault();
        $("#assistify-instructions").show();
        $("#assistify-preview").hide();
      });

      // Close.
      $(document).on("click", "#assistify-close", function (e) {
        e.preventDefault();
        $("#assistify-preview").hide();
        $("#assistify-prompt").val("");
      });

      // Image generation placeholder.
      $(document).on(
        "click",
        ".assistify-featured-image-btn, .assistify-gallery-btn",
        function (e) {
          e.preventDefault();
          window.alert(self.config.strings.imageComingSoon);
        }
      );
    },

    /**
     * Add image generation buttons.
     */
    addImageButtons: function () {
      var self = this;

      // Product Image metabox - add button after existing content.
      var $productImageInside = $("#postimagediv .inside");
      if (
        $productImageInside.length &&
        !$productImageInside.find(".assistify-featured-image-btn").length
      ) {
        $productImageInside.append(
          '<p class="hide-if-no-js" style="clear: both; padding-top: 10px;">' +
            '<button type="button" class="button assistify-featured-image-btn">' +
            self.config.strings.generateWithAssistify +
            "</button>" +
            "</p>"
        );
      }

      // Product Gallery metabox - add button AFTER the "Add product gallery images" link.
      var $galleryLink = $("#woocommerce-product-images .add_product_images");
      if (
        $galleryLink.length &&
        !$("#woocommerce-product-images").find(".assistify-gallery-btn").length
      ) {
        $galleryLink
          .parent()
          .after(
            '<p class="hide-if-no-js" style="margin: 10px 0 0;">' +
              '<button type="button" class="button assistify-gallery-btn">' +
              self.config.strings.generateWithAssistify +
              "</button>" +
              "</p>"
          );
      }
    },

    /**
     * Generate content via AJAX.
     *
     * @param {string} customPrompt Custom prompt from user.
     */
    doGenerate: function (customPrompt) {
      var self = this;

      $("#assistify-preview").hide();
      $("#assistify-loading").show();

      $.post(
        ajaxurl,
        {
          action: "assistify_generate_content",
          nonce: self.config.nonce,
          type: self.state.currentType,
          post_id: self.config.postId,
          tone: self.config.tone,
          length: self.config.length,
          custom_prompt: customPrompt,
          generate_options: 1,
        },
        function (response) {
          $("#assistify-loading").hide();
          if (response.success) {
            self.showOptions(response.data);
          } else {
            window.alert(response.data?.message || self.config.strings.error);
          }
        }
      ).fail(function () {
        $("#assistify-loading").hide();
        window.alert(self.config.strings.error);
      });
    },

    /**
     * Show generated options.
     *
     * @param {Object} data Response data.
     */
    showOptions: function (data) {
      var self = this;
      var $options = $("#assistify-options");
      $options.empty();

      var options = data.options || [data.generated];
      var needsCopyButton =
        self.state.currentType === "tags" ||
        self.state.currentType === "meta_description";

      options.forEach(function (opt, index) {
        var display = Array.isArray(opt) ? opt.join(", ") : opt;
        var $opt = $('<div class="assistify-option">');

        $opt.append(
          "<strong>" +
            self.config.strings.option +
            " " +
            (index + 1) +
            ":</strong>"
        );

        // Full content with scroll.
        var $content = $(
          '<div class="assistify-option-content" style="max-height: 150px; overflow-y: auto; margin: 8px 0; padding: 8px; background: #f9f9f9; border-radius: 3px; white-space: pre-wrap; line-height: 1.5;"></div>'
        );
        $content.text(display);
        $opt.append($content);

        // Add buttons row.
        var $buttons = $('<div class="assistify-option-buttons">');

        if (needsCopyButton) {
          var $copyBtn = $(
            '<button type="button" class="button button-small">' +
              self.config.strings.copy +
              "</button>"
          )
            .data("content", display)
            .on("click", function (e) {
              e.stopPropagation();
              self.copyToClipboard($(this).data("content"), $(this));
            });
          $buttons.append($copyBtn);
        }

        // Use button.
        var $useBtn = $(
          '<button type="button" class="button button-small button-primary">' +
            self.config.strings.use +
            "</button>"
        )
          .data("content", opt)
          .on("click", function (e) {
            e.stopPropagation();
            self.applyContent($(this).data("content"));
          });
        $buttons.append($useBtn);

        $opt.append($buttons);
        $options.append($opt);
      });

      $("#assistify-preview").show();
    },

    /**
     * Copy text to clipboard.
     *
     * @param {string} text    Text to copy.
     * @param {jQuery} $btn    Button element.
     */
    copyToClipboard: function (text, $btn) {
      var self = this;
      navigator.clipboard.writeText(text).then(function () {
        var originalText = $btn.text();
        $btn.text(self.config.strings.copied);
        setTimeout(function () {
          $btn.text(originalText);
        }, 1500);
      });
    },

    /**
     * Apply content to appropriate field.
     *
     * @param {string|Array} content Content to apply.
     */
    applyContent: function (content) {
      var self = this;
      var text = Array.isArray(content) ? content.join(", ") : content;
      var isProduct = self.config.postType === "product";

      switch (self.state.currentType) {
        case "title":
          var $title = $("#title");
          $title.val(text);
          $title.trigger("input").trigger("change").trigger("keyup");
          $("#title-prompt-text").addClass("screen-reader-text");
          break;

        case "description":
          // Format content with proper paragraphs.
          var formattedText = self.formatContentWithParagraphs(text);
          if (
            typeof window.tinymce !== "undefined" &&
            window.tinymce.get("content")
          ) {
            window.tinymce.get("content").setContent(formattedText);
          }
          $("#content").val(formattedText);
          break;

        case "short_description":
          if (
            isProduct &&
            typeof window.tinymce !== "undefined" &&
            window.tinymce.get("excerpt")
          ) {
            window.tinymce.get("excerpt").setContent(text);
          }
          $("#excerpt").val(text);
          break;

        case "tags":
          // Try common tag input methods.
          if ($(".tagchecklist").length && window.tagBox) {
            $("#new-tag-product_tag").val(text);
            $(".tagadd").trigger("click");
          } else {
            navigator.clipboard.writeText(text);
          }
          break;

        case "meta_description":
          // Try common SEO plugins.
          var applied = false;
          if ($("#yoast_wpseo_metadesc").length) {
            $("#yoast_wpseo_metadesc").val(text).trigger("input");
            applied = true;
          }
          if ($('textarea[name="rank_math_description"]').length) {
            $('textarea[name="rank_math_description"]')
              .val(text)
              .trigger("input");
            applied = true;
          }
          if ($("#aioseo-post-settings-meta-description").length) {
            $("#aioseo-post-settings-meta-description")
              .val(text)
              .trigger("input");
            applied = true;
          }
          if (!applied) {
            navigator.clipboard.writeText(text);
          }
          break;
      }

      $("#assistify-preview").hide();
      $("#assistify-prompt").val("");
    },

    /**
     * Format content with proper HTML paragraphs.
     *
     * @param {string} text Raw text content.
     * @return {string} Formatted HTML with paragraphs.
     */
    formatContentWithParagraphs: function (text) {
      // Split by newlines.
      var paragraphs = text.split(/\n+/).filter(function (p) {
        return p.trim().length > 0;
      });

      // Wrap each paragraph in <p> tags.
      var formatted = paragraphs
        .map(function (p) {
          return "<p>" + p.trim() + "</p>";
        })
        .join("\n");

      return formatted;
    },
  };

  // Initialize on document ready.
  $(document).ready(function () {
    AssistifyClassicEditor.init();
  });
})(jQuery);
