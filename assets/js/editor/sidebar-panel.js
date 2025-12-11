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
  const { Button, TextareaControl, Spinner } = wp.components;
  const { useSelect, useDispatch } = wp.data;
  const { __, sprintf } = wp.i18n;
  const { useState } = wp.element;
  const { createBlock } = wp.blocks;
  const el = wp.element.createElement;

  // Get config from localized script.
  const config = window.assistifyEditor || {};
  const { ajaxUrl, nonce, strings = {}, settings = {} } = config;

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
      // Image generation coming soon.
      if (type === "featured_image") {
        window.alert(
          __("Image generation coming soon!", "assistify-for-woocommerce")
        );
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
