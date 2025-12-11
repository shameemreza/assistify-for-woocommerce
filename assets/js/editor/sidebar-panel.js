/**
 * Assistify AI Panel for Block Editor.
 *
 * Uses PluginDocumentSettingPanel to integrate into the document sidebar.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

(function (wp) {
  const { registerPlugin } = wp.plugins;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const {
    Button,
    TextareaControl,
    Spinner,
    SelectControl,
    CheckboxControl,
    Modal,
  } = wp.components;
  const { useSelect, useDispatch } = wp.data;
  const { __, sprintf } = wp.i18n;
  const { useState } = wp.element;
  const { createBlock } = wp.blocks;
  const el = wp.element.createElement;

  // Get config from localized script.
  const config = window.assistifyEditor || {};
  const {
    ajaxUrl,
    nonce,
    strings = {},
    settings = {},
    imageSettings = {},
  } = config;

  /**
   * Main Assistify Panel Component.
   */
  function AssistifyPanel() {
    const [isGenerating, setIsGenerating] = useState(false);
    const [generationType, setGenerationType] = useState(null);
    const [showInstructions, setShowInstructions] = useState(false);
    const [instructions, setInstructions] = useState("");
    const [options, setOptions] = useState([]);
    const [error, setError] = useState(null);
    const [copiedIndex, setCopiedIndex] = useState(null);

    // Image modal state.
    const [showImageModal, setShowImageModal] = useState(false);
    const [imagePrompt, setImagePrompt] = useState("");
    const [imageSize, setImageSize] = useState(
      imageSettings.size || "1024x1024"
    );
    const [imageStyle, setImageStyle] = useState(
      imageSettings.style || "natural"
    );
    const [setAsFeatured, setSetAsFeatured] = useState(true);
    const [generatedImages, setGeneratedImages] = useState([]);
    const [isGeneratingImage, setIsGeneratingImage] = useState(false);
    const [imageError, setImageError] = useState(null);

    const tone = settings.defaultTone || "professional";
    const length = settings.defaultLength || 600;

    const { postId, postTitle, postContent, postType } = useSelect(function (
      select
    ) {
      const editor = select("core/editor");
      return {
        postId: editor.getCurrentPostId(),
        postTitle: editor.getEditedPostAttribute("title") || "",
        postContent: editor.getEditedPostAttribute("content") || "",
        postType: editor.getCurrentPostType(),
      };
    },
    []);

    const { editPost } = useDispatch("core/editor");
    const { resetBlocks } = useDispatch("core/block-editor");

    /**
     * Handle generate button click.
     */
    function handleGenerate(type) {
      // Image generation opens modal.
      if (type === "featured_image") {
        // Check if image generation is supported.
        if (imageSettings.enabled !== "yes") {
          var providerName = imageSettings.provider
            ? imageSettings.provider.charAt(0).toUpperCase() +
              imageSettings.provider.slice(1)
            : "Your current provider";
          setError(
            providerName +
              " does not support image generation. Please select OpenAI, Google, or xAI as your AI provider, and choose an image model in settings."
          );
          return;
        }
        setShowImageModal(true);
        setImageError(null);
        setGeneratedImages([]);
        return;
      }

      setGenerationType(type);
      setError(null);
      setOptions([]);
      setShowInstructions(true);
    }

    /**
     * Submit and generate.
     */
    function handleSubmit() {
      setShowInstructions(false);
      doGenerate(generationType, instructions);
    }

    /**
     * Cancel.
     */
    function handleCancel() {
      setShowInstructions(false);
      setInstructions("");
      setGenerationType(null);
    }

    /**
     * Generate content via AJAX.
     */
    function doGenerate(type, customPrompt) {
      setIsGenerating(true);
      setError(null);

      const formData = new FormData();
      formData.append("action", "assistify_generate_content");
      formData.append("nonce", nonce);
      formData.append("type", type);
      formData.append("post_id", postId);
      formData.append("tone", tone);
      formData.append("length", length);
      formData.append("custom_prompt", customPrompt);
      formData.append("generate_options", "1");

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          setIsGenerating(false);
          if (result.success) {
            setOptions(result.data.options || [result.data.generated]);
          } else {
            setError(
              result.data?.message ||
                __("Error generating content.", "assistify-for-woocommerce")
            );
          }
        })
        .catch(function () {
          setIsGenerating(false);
          setError(
            __("Error generating content.", "assistify-for-woocommerce")
          );
        });
    }

    /**
     * Generate image via AJAX.
     */
    function doGenerateImage() {
      setIsGeneratingImage(true);
      setImageError(null);
      setGeneratedImages([]);

      const formData = new FormData();
      formData.append("action", "assistify_generate_image");
      formData.append("nonce", nonce);
      formData.append("image_action", "featured_image");
      formData.append("prompt", imagePrompt);
      formData.append("post_id", postId);
      formData.append("size", imageSize);
      formData.append("style", imageStyle);
      formData.append("set_featured", setAsFeatured ? "true" : "false");

      fetch(ajaxUrl, {
        method: "POST",
        body: formData,
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (result) {
          setIsGeneratingImage(false);
          if (result.success && result.data.images) {
            setGeneratedImages(result.data.images);
          } else {
            setImageError(
              result.data?.message ||
                __("Error generating image.", "assistify-for-woocommerce")
            );
          }
        })
        .catch(function () {
          setIsGeneratingImage(false);
          setImageError(
            __("Error generating image.", "assistify-for-woocommerce")
          );
        });
    }

    /**
     * Copy to clipboard.
     */
    function copyToClipboard(text, index) {
      navigator.clipboard.writeText(text).then(function () {
        setCopiedIndex(index);
        setTimeout(function () {
          setCopiedIndex(null);
        }, 1500);
      });
    }

    /**
     * Apply selected option - creates proper paragraph blocks.
     */
    function applyOption(content) {
      var text = Array.isArray(content) ? content.join(", ") : content;

      switch (generationType) {
        case "title":
          editPost({ title: text });
          break;
        case "description":
          // Split by newlines for proper paragraphs.
          var paragraphs = text.split(/\n+/).filter(function (p) {
            return p.trim().length > 0;
          });

          var blocks = paragraphs.map(function (p) {
            return createBlock("core/paragraph", { content: p.trim() });
          });
          if (blocks.length > 0) {
            resetBlocks(blocks);
          }
          break;
        case "excerpt":
          editPost({ excerpt: text });
          break;
        case "meta_description":
          // Copy to clipboard for SEO plugins.
          navigator.clipboard.writeText(text);
          window.alert(
            __(
              "Copied to clipboard. Paste in your SEO plugin.",
              "assistify-for-woocommerce"
            )
          );
          break;
      }

      setOptions([]);
      setGenerationType(null);
      setInstructions("");
    }

    /**
     * Regenerate.
     */
    function handleRegenerate() {
      doGenerate(generationType, instructions);
    }

    /**
     * Close image modal.
     */
    function closeImageModal() {
      setShowImageModal(false);
      setImagePrompt("");
      setGeneratedImages([]);
      setImageError(null);
    }

    // Check if type needs copy button.
    var needsCopyButton =
      generationType === "meta_description" || generationType === "excerpt";

    // Build the panel content.
    var panelContent = [];

    // Description text.
    panelContent.push(
      el(
        "p",
        {
          key: "desc",
          style: { color: "#757575", marginTop: 0, marginBottom: "12px" },
        },
        sprintf(
          __("Generate AI content for this %s.", "assistify-for-woocommerce"),
          postType || "post"
        )
      )
    );

    // Generate buttons.
    var buttons = [
      {
        type: "title",
        label: __("Generate Title", "assistify-for-woocommerce"),
      },
      {
        type: "description",
        label: __("Generate Content", "assistify-for-woocommerce"),
      },
      {
        type: "excerpt",
        label: __("Generate Excerpt", "assistify-for-woocommerce"),
      },
      {
        type: "meta_description",
        label: __("Generate SEO Meta", "assistify-for-woocommerce"),
      },
      {
        type: "featured_image",
        label: __("Generate Image", "assistify-for-woocommerce"),
        icon: "format-image",
      },
    ];

    buttons.forEach(function (btn) {
      panelContent.push(
        el(
          Button,
          {
            key: btn.type,
            variant: "secondary",
            onClick: function () {
              handleGenerate(btn.type);
            },
            disabled: isGenerating,
            style: {
              marginBottom: "8px",
              width: "100%",
              justifyContent: "center",
            },
          },
          isGenerating && generationType === btn.type
            ? el(Spinner, { key: "spinner" })
            : btn.label
        )
      );
    });

    // Instructions panel - simple, no word count selector.
    if (showInstructions) {
      panelContent.push(
        el(
          "div",
          {
            key: "instructions",
            style: {
              marginTop: "12px",
              padding: "12px",
              background: "#f0f0f1",
              borderLeft: "4px solid #2271b1",
            },
          },
          el(
            "p",
            { style: { fontWeight: 600, marginTop: 0, marginBottom: "8px" } },
            __("Instructions (optional)", "assistify-for-woocommerce")
          ),
          el(
            "p",
            {
              style: {
                fontSize: "12px",
                color: "#757575",
                marginBottom: "8px",
              },
            },
            __(
              "Add context or leave empty to generate based on existing content.",
              "assistify-for-woocommerce"
            )
          ),
          el(TextareaControl, {
            value: instructions,
            onChange: setInstructions,
            placeholder: __(
              "e.g., Focus on benefits, use formal tone...",
              "assistify-for-woocommerce"
            ),
            rows: 3,
          }),
          el(
            "div",
            { style: { display: "flex", gap: "8px" } },
            el(
              Button,
              { variant: "primary", onClick: handleSubmit },
              __("Generate", "assistify-for-woocommerce")
            ),
            el(
              Button,
              { variant: "secondary", onClick: handleCancel },
              __("Cancel", "assistify-for-woocommerce")
            )
          )
        )
      );
    }

    // Error message.
    if (error) {
      panelContent.push(
        el(
          "div",
          {
            key: "error",
            style: {
              marginTop: "12px",
              padding: "8px",
              background: "#fcf0f1",
              borderLeft: "4px solid #d63638",
              color: "#d63638",
            },
          },
          error
        )
      );
    }

    // Options panel - FULL content with scroll per option.
    if (options.length > 0) {
      var optionElements = options.map(function (opt, index) {
        var display = Array.isArray(opt) ? opt.join(", ") : opt;

        return el(
          "div",
          {
            key: index,
            style: {
              marginBottom: "10px",
              padding: "10px",
              background: "#fff",
              border: "1px solid #c3c4c7",
              borderRadius: "4px",
            },
          },
          el(
            "strong",
            { style: { display: "block", marginBottom: "6px" } },
            __("Option", "assistify-for-woocommerce") + " " + (index + 1)
          ),
          el(
            "div",
            {
              style: {
                maxHeight: "150px",
                overflowY: "auto",
                fontSize: "13px",
                lineHeight: "1.5",
                marginBottom: "8px",
                padding: "8px",
                background: "#f9f9f9",
                borderRadius: "3px",
                whiteSpace: "pre-wrap",
              },
            },
            display
          ),
          el(
            "div",
            {
              style: {
                display: "flex",
                gap: "6px",
                paddingTop: "8px",
                borderTop: "1px solid #e0e0e0",
              },
            },
            needsCopyButton
              ? el(
                  Button,
                  {
                    variant: "secondary",
                    isSmall: true,
                    onClick: function (e) {
                      e.stopPropagation();
                      copyToClipboard(display, index);
                    },
                  },
                  copiedIndex === index
                    ? __("Copied!", "assistify-for-woocommerce")
                    : __("Copy", "assistify-for-woocommerce")
                )
              : null,
            el(
              Button,
              {
                variant: "primary",
                isSmall: true,
                onClick: function (e) {
                  e.stopPropagation();
                  applyOption(opt);
                },
              },
              __("Use", "assistify-for-woocommerce")
            )
          )
        );
      });

      panelContent.push(
        el(
          "div",
          {
            key: "options",
            style: {
              marginTop: "12px",
              padding: "12px",
              background: "#f0f6fc",
              borderLeft: "4px solid #2271b1",
            },
          },
          el(
            "p",
            { style: { fontWeight: 600, marginTop: 0, marginBottom: "8px" } },
            __("Choose an option", "assistify-for-woocommerce")
          ),
          el("div", null, optionElements),
          el(
            "div",
            { style: { display: "flex", gap: "8px", marginTop: "12px" } },
            el(
              Button,
              { variant: "secondary", onClick: handleRegenerate },
              __("Regenerate", "assistify-for-woocommerce")
            ),
            el(
              Button,
              {
                variant: "tertiary",
                onClick: function () {
                  setOptions([]);
                  setGenerationType(null);
                },
              },
              __("Close", "assistify-for-woocommerce")
            )
          )
        )
      );
    }

    // Image Modal.
    if (showImageModal) {
      panelContent.push(
        el(
          Modal,
          {
            key: "imageModal",
            title: __("Generate AI Image", "assistify-for-woocommerce"),
            onRequestClose: closeImageModal,
            style: { maxWidth: "500px" },
          },
          // Modal content.
          generatedImages.length === 0 && !isGeneratingImage
            ? el(
                "div",
                { style: { padding: "0" } },
                el(TextareaControl, {
                  label: __("Image Description", "assistify-for-woocommerce"),
                  value: imagePrompt,
                  onChange: setImagePrompt,
                  placeholder: __(
                    "Describe the image you want, or leave empty to generate from post content...",
                    "assistify-for-woocommerce"
                  ),
                  rows: 3,
                }),
                el(
                  "div",
                  {
                    style: {
                      display: "flex",
                      gap: "12px",
                      marginBottom: "16px",
                    },
                  },
                  el(
                    "div",
                    { style: { flex: 1 } },
                    el(SelectControl, {
                      label: __("Size", "assistify-for-woocommerce"),
                      value: imageSize,
                      options: [
                        { label: "1024×1024 (Square)", value: "1024x1024" },
                        { label: "1024×1536 (Portrait)", value: "1024x1536" },
                        { label: "1536×1024 (Landscape)", value: "1536x1024" },
                      ],
                      onChange: setImageSize,
                    })
                  ),
                  el(
                    "div",
                    { style: { flex: 1 } },
                    el(SelectControl, {
                      label: __("Style", "assistify-for-woocommerce"),
                      value: imageStyle,
                      options: [
                        { label: "Natural (Realistic)", value: "natural" },
                        { label: "Vivid (Artistic)", value: "vivid" },
                      ],
                      onChange: setImageStyle,
                    })
                  )
                ),
                el(CheckboxControl, {
                  label: __(
                    "Set as featured image",
                    "assistify-for-woocommerce"
                  ),
                  checked: setAsFeatured,
                  onChange: setSetAsFeatured,
                }),
                imageError &&
                  el(
                    "div",
                    {
                      style: {
                        marginTop: "12px",
                        padding: "8px",
                        background: "#fcf0f1",
                        borderLeft: "4px solid #d63638",
                        color: "#d63638",
                      },
                    },
                    imageError
                  ),
                el(
                  "div",
                  {
                    style: {
                      display: "flex",
                      justifyContent: "flex-end",
                      gap: "8px",
                      marginTop: "16px",
                    },
                  },
                  el(
                    Button,
                    { variant: "secondary", onClick: closeImageModal },
                    __("Cancel", "assistify-for-woocommerce")
                  ),
                  el(
                    Button,
                    { variant: "primary", onClick: doGenerateImage },
                    __("Generate Image", "assistify-for-woocommerce")
                  )
                )
              )
            : null,
          // Loading.
          isGeneratingImage
            ? el(
                "div",
                { style: { textAlign: "center", padding: "40px 20px" } },
                el(Spinner, { style: { width: 40, height: 40 } }),
                el(
                  "p",
                  { style: { marginTop: "16px", color: "#666" } },
                  __(
                    "Generating image... This may take up to 30 seconds.",
                    "assistify-for-woocommerce"
                  )
                )
              )
            : null,
          // Generated images.
          generatedImages.length > 0
            ? el(
                "div",
                null,
                el(
                  "div",
                  {
                    style: {
                      display: "grid",
                      gridTemplateColumns:
                        "repeat(auto-fill, minmax(150px, 1fr))",
                      gap: "15px",
                      marginBottom: "16px",
                    },
                  },
                  generatedImages.map(function (img, idx) {
                    return el(
                      "div",
                      {
                        key: idx,
                        style: {
                          border: "1px solid #ddd",
                          borderRadius: "6px",
                          overflow: "hidden",
                        },
                      },
                      el("img", {
                        src: img.thumbnail,
                        alt: "Generated " + (idx + 1),
                        style: {
                          width: "100%",
                          height: "150px",
                          objectFit: "cover",
                        },
                      }),
                      el(
                        "div",
                        {
                          style: {
                            padding: "8px",
                            display: "flex",
                            justifyContent: "center",
                          },
                        },
                        el(
                          Button,
                          {
                            variant: "secondary",
                            isSmall: true,
                            href: img.full,
                            target: "_blank",
                          },
                          __("View Full", "assistify-for-woocommerce")
                        )
                      )
                    );
                  })
                ),
                el(
                  "div",
                  {
                    style: {
                      background: "#d4edda",
                      color: "#155724",
                      padding: "12px",
                      borderRadius: "4px",
                      marginBottom: "16px",
                    },
                  },
                  __(
                    "Image(s) generated and added to Media Library!",
                    "assistify-for-woocommerce"
                  )
                ),
                el(
                  "div",
                  {
                    style: {
                      display: "flex",
                      justifyContent: "flex-end",
                      gap: "8px",
                    },
                  },
                  el(
                    Button,
                    {
                      variant: "secondary",
                      onClick: function () {
                        setGeneratedImages([]);
                      },
                    },
                    __("Generate More", "assistify-for-woocommerce")
                  ),
                  el(
                    Button,
                    { variant: "primary", onClick: closeImageModal },
                    __("Done", "assistify-for-woocommerce")
                  )
                )
              )
            : null
        )
      );
    }

    return el(
      PluginDocumentSettingPanel,
      {
        name: "assistify-ai",
        title: __("Assistify AI", "assistify-for-woocommerce"),
        initialOpen: false,
      },
      panelContent
    );
  }

  registerPlugin("assistify-ai", {
    render: AssistifyPanel,
    icon: "admin-generic",
  });
})(window.wp);
