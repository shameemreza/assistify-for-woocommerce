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
      currentImageAction: null,
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
      this.createImageModal();
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

      // Featured image generation.
      $(document).on("click", ".assistify-featured-image-btn", function (e) {
        e.preventDefault();
        if ($(this).hasClass("assistify-image-unsupported")) {
          self.showImageUnsupportedNotice();
          return;
        }
        self.state.currentImageAction = "featured_image";
        var isProduct = $("#post_type").val() === "product";
        self.openImageModal(isProduct ? "Product Image" : "Featured Image");
      });

      // Gallery image generation.
      $(document).on("click", ".assistify-gallery-btn", function (e) {
        e.preventDefault();
        if ($(this).hasClass("assistify-image-unsupported")) {
          self.showImageUnsupportedNotice();
          return;
        }
        self.state.currentImageAction = "gallery";
        self.openImageModal("Product Gallery");
      });
    },

    /**
     * Show notice when image generation is not supported.
     */
    showImageUnsupportedNotice: function () {
      var provider = this.config.imageSettings
        ? this.config.imageSettings.provider
        : "your current provider";
      var message =
        "Image generation is not available for " +
        provider.charAt(0).toUpperCase() +
        provider.slice(1) +
        ". Please select OpenAI, Google, or xAI as your AI provider, and choose an image model in settings.";

      // Use WordPress admin notice style.
      if (!$("#assistify-image-notice").length) {
        $(".wrap h1")
          .first()
          .after(
            '<div id="assistify-image-notice" class="notice notice-warning is-dismissible" style="display:none;"><p></p></div>'
          );
      }

      $("#assistify-image-notice p").text(message);
      $("#assistify-image-notice").slideDown();

      // Auto dismiss after 8 seconds.
      setTimeout(function () {
        $("#assistify-image-notice").slideUp();
      }, 8000);
    },

    /**
     * Add image generation buttons.
     */
    addImageButtons: function () {
      var self = this;
      var imageEnabled =
        self.config.imageSettings &&
        self.config.imageSettings.enabled === "yes";

      // Product Image metabox - add button after existing content.
      var $productImageInside = $("#postimagediv .inside");
      if (
        $productImageInside.length &&
        !$productImageInside.find(".assistify-featured-image-btn").length
      ) {
        var btnClass = imageEnabled
          ? "button assistify-featured-image-btn"
          : "button assistify-featured-image-btn assistify-image-unsupported";

        $productImageInside.append(
          '<p class="hide-if-no-js" style="clear: both; padding-top: 10px;">' +
            '<button type="button" class="' +
            btnClass +
            '">' +
            '<span class="dashicons dashicons-format-image" style="margin-right: 4px; vertical-align: middle;"></span>' +
            "Generate with AI" +
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
        var galBtnClass = imageEnabled
          ? "button assistify-gallery-btn"
          : "button assistify-gallery-btn assistify-image-unsupported";

        $galleryLink
          .parent()
          .after(
            '<p class="hide-if-no-js" style="margin: 10px 0 0;">' +
              '<button type="button" class="' +
              galBtnClass +
              '">' +
              '<span class="dashicons dashicons-images-alt2" style="margin-right: 4px; vertical-align: middle;"></span>' +
              "Generate Gallery with AI" +
              "</button>" +
              "</p>"
          );
      }
    },

    /**
     * Create image generation modal.
     */
    createImageModal: function () {
      if ($("#assistify-image-modal").length) {
        return;
      }

      var imageSettings = this.config.imageSettings || {};
      var supportsReference = imageSettings.supportsReferenceImage || false;
      var removeBgEnabled = imageSettings.removeBgEnabled || false;

      var referenceHtml = supportsReference
        ? '<div class="assistify-form-row assistify-reference-section">' +
          "<label>Reference Image (Optional)</label>" +
          '<div class="assistify-reference-upload">' +
          '<button type="button" class="button" id="assistify-reference-upload-btn">' +
          '<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span> Upload Reference' +
          "</button>" +
          '<span id="assistify-reference-filename" style="margin-left: 10px; color: #666;"></span>' +
          '<input type="hidden" id="assistify-reference-image-url" value="">' +
          '<button type="button" class="button" id="assistify-reference-clear-btn" style="display:none; margin-left: 5px;">Clear</button>' +
          "</div>" +
          '<p class="description">Upload an image to use as reference for editing or variations.</p>' +
          "</div>"
        : "";

      var removeBgHtml = removeBgEnabled
        ? '<div class="assistify-form-row assistify-removebg-section" style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 15px;">' +
          '<label style="font-weight: 600;">Background Removal</label>' +
          '<p class="description" style="margin-bottom: 10px;">Remove background from existing product/post images using Remove.bg.</p>' +
          '<button type="button" class="button" id="assistify-removebg-btn">' +
          '<span class="dashicons dashicons-image-filter" style="vertical-align: middle;"></span> Select Image to Remove Background' +
          "</button>" +
          "</div>"
        : "";

      var modalHtml =
        '<div id="assistify-image-modal" class="assistify-modal" style="display: none;">' +
        '<div class="assistify-modal-backdrop"></div>' +
        '<div class="assistify-modal-content">' +
        '<div class="assistify-modal-header">' +
        '<h2 id="assistify-image-modal-title">Generate AI Image</h2>' +
        '<button type="button" class="assistify-modal-close">&times;</button>' +
        "</div>" +
        '<div class="assistify-modal-body">' +
        '<div class="assistify-image-form">' +
        '<div class="assistify-form-row">' +
        '<label for="assistify-image-prompt">Image Description</label>' +
        '<textarea id="assistify-image-prompt" rows="3" placeholder="Describe the image you want to generate, or leave empty to generate from product/post content..."></textarea>' +
        "</div>" +
        referenceHtml +
        '<div class="assistify-form-row assistify-form-row-inline">' +
        '<div class="assistify-form-col">' +
        '<label for="assistify-image-size">Size</label>' +
        '<select id="assistify-image-size">' +
        '<option value="1024x1024">1024×1024 (Square)</option>' +
        '<option value="1024x1536">1024×1536 (Portrait)</option>' +
        '<option value="1536x1024">1536×1024 (Landscape)</option>' +
        "</select>" +
        "</div>" +
        '<div class="assistify-form-col">' +
        '<label for="assistify-image-style">Style</label>' +
        '<select id="assistify-image-style">' +
        '<option value="natural">Natural (Realistic)</option>' +
        '<option value="vivid">Vivid (Artistic)</option>' +
        "</select>" +
        "</div>" +
        '<div class="assistify-form-col" id="assistify-gallery-count-col" style="display: none;">' +
        '<label for="assistify-image-count">Count</label>' +
        '<select id="assistify-image-count">' +
        '<option value="1">1 Image</option>' +
        '<option value="2">2 Images</option>' +
        '<option value="3">3 Images</option>' +
        '<option value="4" selected>4 Images</option>' +
        "</select>" +
        "</div>" +
        "</div>" +
        '<div class="assistify-form-row">' +
        "<label>" +
        '<input type="checkbox" id="assistify-image-set-featured" checked> ' +
        '<span id="assistify-image-action-label">Set as featured image</span>' +
        "</label>" +
        "</div>" +
        removeBgHtml +
        "</div>" +
        '<div class="assistify-image-loading" style="display: none;">' +
        '<div class="assistify-spinner"></div>' +
        "<p>Generating image... This may take up to 30 seconds.</p>" +
        "</div>" +
        '<div class="assistify-image-preview" style="display: none;">' +
        '<div id="assistify-generated-images"></div>' +
        "</div>" +
        "</div>" +
        '<div class="assistify-modal-footer">' +
        '<button type="button" class="button assistify-modal-cancel">Cancel</button>' +
        '<button type="button" class="button button-primary" id="assistify-image-generate">Generate Image</button>' +
        "</div>" +
        "</div>" +
        "</div>";

      $("body").append(modalHtml);

      // Bind modal events.
      this.bindImageModalEvents();
    },

    /**
     * Bind image modal events.
     */
    bindImageModalEvents: function () {
      var self = this;

      // Close modal.
      $(document).on(
        "click",
        ".assistify-modal-close, .assistify-modal-cancel, .assistify-modal-backdrop",
        function (e) {
          e.preventDefault();
          self.closeImageModal();
        }
      );

      // Generate button.
      $(document).on("click", "#assistify-image-generate", function (e) {
        e.preventDefault();
        self.doGenerateImage();
      });

      // Reference image upload.
      $(document).on("click", "#assistify-reference-upload-btn", function (e) {
        e.preventDefault();
        self.openMediaUploader(function (attachment) {
          $("#assistify-reference-image-url").val(attachment.url);
          $("#assistify-reference-filename").text(
            attachment.filename || "Image selected"
          );
          $("#assistify-reference-clear-btn").show();
        });
      });

      // Clear reference image.
      $(document).on("click", "#assistify-reference-clear-btn", function (e) {
        e.preventDefault();
        $("#assistify-reference-image-url").val("");
        $("#assistify-reference-filename").text("");
        $(this).hide();
      });

      // Remove background button.
      $(document).on("click", "#assistify-removebg-btn", function (e) {
        e.preventDefault();
        self.openMediaUploader(function (attachment) {
          self.doRemoveBackground(attachment.url);
        });
      });

      // ESC key to close.
      $(document).on("keydown", function (e) {
        if (e.key === "Escape" && $("#assistify-image-modal").is(":visible")) {
          self.closeImageModal();
        }
      });
    },

    /**
     * Open WordPress media uploader.
     *
     * @param {Function} callback Callback with selected attachment.
     */
    openMediaUploader: function (callback) {
      var mediaUploader = wp.media({
        title: "Select Image",
        button: {
          text: "Use this image",
        },
        multiple: false,
        library: {
          type: "image",
        },
      });

      mediaUploader.on("select", function () {
        var attachment = mediaUploader
          .state()
          .get("selection")
          .first()
          .toJSON();
        callback(attachment);
      });

      mediaUploader.open();
    },

    /**
     * Remove background from image.
     *
     * @param {string} imageUrl Image URL to process.
     */
    doRemoveBackground: function (imageUrl) {
      var self = this;

      // Show loading state.
      $(".assistify-image-form").hide();
      $(".assistify-image-loading")
        .show()
        .find("p")
        .text("Removing background... This may take a moment.");

      $.ajax({
        url: self.config.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_generate_image",
          nonce: self.config.nonce,
          image_action: "remove_background",
          image_url: imageUrl,
          post_id: self.config.postId,
        },
        success: function (response) {
          $(".assistify-image-loading").hide();
          if (response.success && response.data.images) {
            self.showGeneratedImages(
              response.data.images,
              "background_removed"
            );
            if (response.data.credits_charged) {
              self.showNotice(
                "Background removed! (" +
                  response.data.credits_charged +
                  " credit used)",
                "success"
              );
            }
          } else {
            self.showNotice(
              response.data?.message || "Failed to remove background.",
              "error"
            );
            $(".assistify-image-form").show();
          }
        },
        error: function () {
          $(".assistify-image-loading").hide();
          $(".assistify-image-form").show();
          self.showNotice("Connection error. Please try again.", "error");
        },
      });
    },

    /**
     * Open image generation modal.
     *
     * @param {string} title Modal title.
     */
    openImageModal: function (title) {
      var self = this;
      var isGallery = self.state.currentImageAction === "gallery";
      var isProduct = $("#post_type").val() === "product";

      $("#assistify-image-modal-title").text("Generate " + title);
      $("#assistify-image-prompt").val("");

      // Reset reference image.
      $("#assistify-reference-image-url").val("");
      $("#assistify-reference-filename").text("");
      $("#assistify-reference-clear-btn").hide();

      // Show/hide gallery-specific options and set appropriate label.
      if (isGallery) {
        $("#assistify-gallery-count-col").show();
        $("#assistify-image-action-label").text("Add to product gallery");
      } else {
        $("#assistify-gallery-count-col").hide();
        // Use "Set as product image" for products, "Set as featured image" for posts/pages.
        var label = isProduct
          ? "Set as product image"
          : "Set as featured image";
        $("#assistify-image-action-label").text(label);
      }

      // Reset state.
      $(".assistify-image-form").show();
      $(".assistify-image-loading").hide();
      $(".assistify-image-preview").hide();
      $("#assistify-image-generate")
        .text("Generate Image")
        .prop("disabled", false);

      $("#assistify-image-modal").fadeIn(200);
    },

    /**
     * Close image modal.
     */
    closeImageModal: function () {
      $("#assistify-image-modal").fadeOut(200);
    },

    /**
     * Generate image via AJAX.
     */
    doGenerateImage: function () {
      var self = this;
      var action = self.state.currentImageAction;
      var isGallery = action === "gallery";

      var prompt = $("#assistify-image-prompt").val();
      var size = $("#assistify-image-size").val();
      var style = $("#assistify-image-style").val();
      var setFeatured = $("#assistify-image-set-featured").is(":checked");
      var count = isGallery ? parseInt($("#assistify-image-count").val()) : 1;
      var referenceImageUrl = $("#assistify-reference-image-url").val();

      // If reference image is provided, switch to edit action.
      var finalAction = action;
      if (referenceImageUrl && prompt) {
        finalAction = "edit";
      } else if (referenceImageUrl && !prompt) {
        finalAction = "variations";
      }

      // Show loading.
      $(".assistify-image-form").hide();
      $(".assistify-image-loading").show();
      $(".assistify-image-preview").hide();
      $("#assistify-image-generate")
        .text("Generating...")
        .prop("disabled", true);

      $.ajax({
        url: ajaxurl,
        type: "POST",
        timeout: 300000, // 5 minutes timeout for gpt-image-1.
        data: {
          action: "assistify_generate_image",
          nonce: self.config.nonce,
          image_action: finalAction,
          prompt: prompt,
          post_id: self.config.postId,
          size: size,
          style: style,
          count: count,
          set_featured: setFeatured ? "true" : "false",
          add_to_gallery: setFeatured ? "true" : "false",
          image_url: referenceImageUrl,
        },
        success: function (response) {
          $(".assistify-image-loading").hide();

          if (response.success && response.data.images) {
            self.showGeneratedImages(response.data.images, isGallery);
            // Clear reference image after successful generation.
            $("#assistify-reference-image-url").val("");
            $("#assistify-reference-filename").text("");
            $("#assistify-reference-clear-btn").hide();
          } else {
            window.alert(
              response.data?.message ||
                "Error generating image. Please try again."
            );
            $(".assistify-image-form").show();
            $("#assistify-image-generate")
              .text("Generate Image")
              .prop("disabled", false);
          }
        },
        error: function (xhr, status, error) {
          $(".assistify-image-loading").hide();
          $(".assistify-image-form").show();
          $("#assistify-image-generate")
            .text("Generate Image")
            .prop("disabled", false);

          var errorMsg = "Error generating image. Please try again.";
          if (status === "timeout") {
            errorMsg =
              "Request timed out. Try using DALL-E 3 model for faster generation.";
          }
          window.alert(errorMsg);
        },
      });
    },

    /**
     * Show a notice message.
     *
     * @param {string} message Notice message.
     * @param {string} type    Notice type (success, error, warning).
     */
    showNotice: function (message, type) {
      var noticeClass = "notice-" + (type || "info");
      var $notice = $(
        '<div class="notice ' +
          noticeClass +
          ' is-dismissible" style="margin: 10px 0;"><p>' +
          message +
          "</p></div>"
      );

      // Add to modal body.
      $(".assistify-modal-body .assistify-image-form").before($notice);

      // Auto-dismiss after 5 seconds.
      setTimeout(function () {
        $notice.slideUp(200, function () {
          $(this).remove();
        });
      }, 5000);
    },

    /**
     * Show generated images preview.
     *
     * @param {Array} images   Generated images data.
     * @param {boolean} isGallery Whether this is gallery generation.
     */
    showGeneratedImages: function (images, isGallery) {
      var self = this;
      var $container = $("#assistify-generated-images");
      var isProduct = self.config.postType === "product";
      $container.empty();

      images.forEach(function (image, index) {
        var $item = $(
          '<div class="assistify-image-item" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px;">'
        );

        // Use url or full URL for display.
        var imageUrl = image.url || image.full || image.thumbnail;
        var thumbnailUrl = image.thumbnail || imageUrl;

        $item.append(
          '<img src="' +
            thumbnailUrl +
            '" alt="Generated Image ' +
            (index + 1) +
            '" style="max-width: 200px; display: block; margin-bottom: 10px; border-radius: 4px;">'
        );

        var $actions = $(
          '<div class="assistify-image-actions" style="display: flex; flex-wrap: wrap; gap: 5px;">'
        );

        // View full size - only if URL exists.
        if (imageUrl) {
          $actions.append(
            '<a href="' +
              imageUrl +
              '" target="_blank" class="button button-small"><span class="dashicons dashicons-external" style="vertical-align: middle;"></span> View Full</a>'
          );
        }

        // For products, show product-specific options.
        if (isProduct) {
          // Set as Product Image.
          $actions.append(
            '<button type="button" class="button button-small button-primary assistify-set-product-image" data-id="' +
              image.id +
              '"><span class="dashicons dashicons-format-image" style="vertical-align: middle;"></span> Set as Product Image</button>'
          );

          // Add to Gallery.
          $actions.append(
            '<button type="button" class="button button-small assistify-add-to-gallery" data-id="' +
              image.id +
              '"><span class="dashicons dashicons-images-alt2" style="vertical-align: middle;"></span> Add to Gallery</button>'
          );
        } else {
          // For posts/pages - Set as Featured Image.
          $actions.append(
            '<button type="button" class="button button-small button-primary assistify-set-featured" data-id="' +
              image.id +
              '"><span class="dashicons dashicons-format-image" style="vertical-align: middle;"></span> Set as Featured</button>'
          );
        }

        $item.append($actions);
        $container.append($item);
      });

      // Bind action button events.
      $container.find(".assistify-set-product-image").on("click", function () {
        var id = $(this).data("id");
        self.setAsProductImage(id);
        $(this).text("✓ Set!").prop("disabled", true);
      });

      $container.find(".assistify-add-to-gallery").on("click", function () {
        var id = $(this).data("id");
        self.addToProductGallery(id);
        $(this).text("✓ Added!").prop("disabled", true);
      });

      $container.find(".assistify-set-featured").on("click", function () {
        var id = $(this).data("id");
        self.setAsFeatured(id);
        $(this).text("✓ Set!").prop("disabled", true);
      });

      if (images.length > 0) {
        $container.append(
          '<p class="assistify-image-success" style="color: #46b450; margin-top: 10px;">' +
            '<span class="dashicons dashicons-yes-alt"></span> ' +
            images.length +
            " image(s) saved to Media Library" +
            (isGallery ? " and Product Gallery" : "") +
            "!</p>"
        );
      }

      $(".assistify-image-preview").show();
      $("#assistify-image-generate")
        .text("Generate More")
        .prop("disabled", false)
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          $(".assistify-image-form").show();
          $(".assistify-image-preview").hide();
        });
    },

    /**
     * Set image as product image (WooCommerce).
     *
     * @param {number} attachmentId Attachment ID.
     */
    setAsProductImage: function (attachmentId) {
      var self = this;

      // Use custom Assistify AJAX handler.
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "assistify_set_product_image",
          nonce: self.config.nonce,
          post_id: self.config.postId,
          attachment_id: attachmentId,
        },
        success: function (response) {
          if (response.success) {
            // Update the hidden input for form submission.
            $("#_thumbnail_id").val(attachmentId);

            // Update the visual preview.
            self.updateProductImagePreview(
              attachmentId,
              response.data.thumbnail_url
            );
            self.showNotice("Product image set successfully!", "success");
          } else {
            self.showNotice(
              response.data?.message || "Failed to set product image.",
              "error"
            );
          }
        },
        error: function () {
          self.showNotice("Failed to set product image.", "error");
        },
      });
    },

    /**
     * Update product image preview in the metabox.
     *
     * @param {number} attachmentId Attachment ID.
     * @param {string} thumbnailUrl Optional thumbnail URL.
     */
    updateProductImagePreview: function (attachmentId, thumbnailUrl) {
      // Set hidden input.
      $("#_thumbnail_id").val(attachmentId);

      // Try to update the visual in the product image metabox.
      var $productImageWrap = $("#product_images .woocommerce-product-images");
      var $postimagediv = $("#postimagediv .inside");

      if (thumbnailUrl) {
        // Update WooCommerce product image if visible.
        if ($productImageWrap.length) {
          var $imgPlaceholder = $productImageWrap.find(
            ".woocommerce-placeholder, img"
          );
          if ($imgPlaceholder.length) {
            $imgPlaceholder.attr("src", thumbnailUrl);
          }
        }
      }

      // Try WordPress featured image metabox.
      if ($postimagediv.length) {
        $.ajax({
          url: ajaxurl,
          type: "POST",
          data: {
            action: "get-post-thumbnail-html",
            post_id: this.config.postId,
            thumbnail_id: attachmentId,
            _wpnonce: this.config.thumbnailNonce || this.config.nonce,
          },
          success: function (html) {
            if (html && html !== "0" && html !== "-1") {
              $postimagediv.html(html);
            }
          },
        });
      }
    },

    /**
     * Add image to product gallery (WooCommerce).
     *
     * @param {number} attachmentId Attachment ID.
     */
    addToProductGallery: function (attachmentId) {
      var self = this;

      // Use custom Assistify AJAX handler.
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "assistify_add_to_gallery",
          nonce: self.config.nonce,
          post_id: self.config.postId,
          attachment_id: attachmentId,
        },
        success: function (response) {
          if (response.success) {
            // Update the hidden input.
            var $galleryInput = $("#product_image_gallery");
            if ($galleryInput.length && response.data.gallery) {
              $galleryInput.val(response.data.gallery.join(","));
            }

            // Add visual thumbnail to gallery container.
            var $galleryContainer = $(
              "#product_images_container ul.product_images"
            );
            if ($galleryContainer.length && response.data.thumbnail_url) {
              var $newItem = $(
                '<li class="image" data-attachment_id="' +
                  attachmentId +
                  '">' +
                  '<img src="' +
                  response.data.thumbnail_url +
                  '" />' +
                  '<ul class="actions">' +
                  '<li><a href="#" class="delete tips" data-tip="Delete"></a></li>' +
                  "</ul>" +
                  "</li>"
              );
              $galleryContainer.append($newItem);
            }

            self.showNotice(response.data.message, "success");
          } else {
            self.showNotice(
              response.data?.message || "Failed to add to gallery.",
              "error"
            );
          }
        },
        error: function () {
          self.showNotice("Failed to add to gallery.", "error");
        },
      });
    },

    /**
     * Set image as featured image (posts/pages).
     *
     * @param {number} attachmentId Attachment ID.
     */
    setAsFeatured: function (attachmentId) {
      var self = this;

      // Use custom Assistify AJAX handler (same as product image).
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "assistify_set_product_image",
          nonce: self.config.nonce,
          post_id: self.config.postId,
          attachment_id: attachmentId,
        },
        success: function (response) {
          if (response.success) {
            // Set the hidden input.
            $("#_thumbnail_id").val(attachmentId);

            // Update visual if using wp.media.
            if (
              typeof wp !== "undefined" &&
              wp.media &&
              wp.media.featuredImage
            ) {
              wp.media.featuredImage.set(attachmentId);
            }

            // Update the metabox preview.
            self.updateProductImagePreview(
              attachmentId,
              response.data.thumbnail_url
            );
            self.showNotice("Featured image set!", "success");
          } else {
            self.showNotice(
              response.data?.message || "Failed to set featured image.",
              "error"
            );
          }
        },
        error: function () {
          // Fallback - just set hidden input.
          $("#_thumbnail_id").val(attachmentId);
          self.showNotice(
            "Featured image may be set. Save post to confirm.",
            "info"
          );
        },
      });
    },

    /**
     * Show a notice message in the modal.
     *
     * @param {string} message The message to show.
     * @param {string} type    Type: 'success', 'error', 'info'.
     */
    showNotice: function (message, type) {
      var $container = $("#assistify-generated-images");
      var bgColor = "#46b450";
      var icon = "dashicons-yes-alt";

      if (type === "error") {
        bgColor = "#dc3232";
        icon = "dashicons-warning";
      } else if (type === "info") {
        bgColor = "#00a0d2";
        icon = "dashicons-info";
      }

      var $notice = $(
        '<div class="assistify-notice" style="padding: 8px 12px; margin: 10px 0; background: ' +
          bgColor +
          '; color: #fff; border-radius: 4px; display: flex; align-items: center; gap: 8px;">' +
          '<span class="dashicons ' +
          icon +
          '"></span>' +
          "<span>" +
          message +
          "</span>" +
          "</div>"
      );

      // Remove existing notices.
      $container.find(".assistify-notice").remove();

      // Add new notice.
      $container.prepend($notice);

      // Auto-remove after 4 seconds.
      setTimeout(function () {
        $notice.fadeOut(function () {
          $(this).remove();
        });
      }, 4000);
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

  // Make accessible globally for inline handlers.
  window.AssistifyClassicEditor = AssistifyClassicEditor;

  // Initialize on document ready.
  $(document).ready(function () {
    AssistifyClassicEditor.init();
  });
})(jQuery);
