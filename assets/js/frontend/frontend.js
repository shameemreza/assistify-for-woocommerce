/**
 * Assistify for WooCommerce - Frontend JavaScript
 *
 * Customer chat widget with streaming, sessions, and smart features.
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

/* global jQuery, assistifyFrontend */

(function ($) {
  "use strict";

  /**
   * Assistify Frontend Chat
   */
  const AssistifyChat = {
    /**
     * Whether user has consented to chat
     */
    hasConsented: false,

    /**
     * Current session ID
     */
    sessionId: null,

    /**
     * Message history for context
     */
    messageHistory: [],

    /**
     * Streaming state
     */
    isStreaming: false,
    streamingSpeed: 15,

    /**
     * Sound enabled
     */
    soundEnabled: true,

    /**
     * Idle timer for auto-open
     */
    idleTimer: null,
    idleTimeout: 90000, // 90 seconds default

    /**
     * Whether chat has been shown via auto-open
     */
    autoOpenShown: false,

    /**
     * Whether this is the first message in session
     */
    isFirstMessage: true,

    /**
     * Whether API is online
     */
    isApiOnline: true,

    /**
     * Initialize
     */
    init: function () {
      this.$widget = $("#assistify-chat-widget");

      if (!this.$widget.length) {
        return;
      }

      this.$toggle = $("#assistify-chat-toggle");
      this.$container = $("#assistify-chat-container");
      this.$messages = this.$widget.find(".assistify-chat-messages");
      this.$form = $("#assistify-chat-form");
      this.$input = $("#assistify-chat-input");
      this.$closeBtn = this.$widget.find(".assistify-chat-close");
      this.$header = this.$widget.find(".assistify-chat-header");
      this.$title = this.$widget.find(".assistify-chat-title");

      // Check API status
      this.isApiOnline =
        assistifyFrontend.apiStatus && assistifyFrontend.apiStatus.online;

      // Apply settings
      this.applySettings();

      // Load session and consent
      this.loadSession();
      this.hasConsented = this.getConsent();

      // Load previous messages if consented
      if (this.hasConsented && this.messageHistory.length > 0) {
        this.restoreMessages();
      }

      this.bindEvents();

      // Start idle detection for auto-open (works for consented and non-consented users)
      if (assistifyFrontend.settings.autoOpenEnabled && !this.autoOpenShown) {
        this.startIdleDetection();
      }
    },

    /**
     * Apply settings from backend
     */
    applySettings: function () {
      // Set CSS custom property for primary color
      document.documentElement.style.setProperty(
        "--assistify-primary-color",
        assistifyFrontend.settings.primaryColor || "#6861f2"
      );

      // Sound settings
      this.soundEnabled = assistifyFrontend.settings.soundEnabled !== false;

      // Auto-open timeout
      if (assistifyFrontend.settings.autoOpenDelay) {
        this.idleTimeout = assistifyFrontend.settings.autoOpenDelay * 1000;
      }
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      const self = this;

      // Toggle chat
      this.$toggle.on("click", function () {
        self.toggleChat();
        self.resetIdleTimer();
      });

      // Close chat
      this.$closeBtn.on("click", function () {
        self.closeChat();
      });

      // Submit message
      this.$form.on("submit", function (e) {
        e.preventDefault();
        self.sendMessage();
      });

      // Quick question buttons
      this.$widget.on("click", ".assistify-quick-btn", function () {
        const question = $(this).data("question");
        if (question) {
          self.$input.val(question);
          self.sendMessage();
        }
      });

      // Keyboard events
      $(document).on("keydown", function (e) {
        if (e.key === "Escape" && self.$widget.hasClass("is-open")) {
          self.closeChat();
        }
      });

      // Copy message
      this.$widget.on("click", ".assistify-copy-btn", function () {
        const messageId = $(this).data("message-id");
        self.copyMessage(messageId);
      });

      // Reset idle timer on user activity
      $(document).on("mousemove keydown scroll click", function () {
        self.resetIdleTimer();
      });

      // Save session before page unload
      $(window).on("beforeunload", function () {
        self.saveSession();
      });
    },

    /**
     * Load or create session
     */
    loadSession: function () {
      try {
        this.sessionId = localStorage.getItem("assistify_customer_session");
        const savedHistory = localStorage.getItem("assistify_customer_history");
        if (savedHistory) {
          this.messageHistory = JSON.parse(savedHistory);
          this.isFirstMessage = this.messageHistory.length === 0;
        }
      } catch (e) {
        // Silent fail for private browsing
      }

      if (!this.sessionId) {
        this.sessionId = this.generateUUID();
        this.saveSession();
      }
    },

    /**
     * Save session
     */
    saveSession: function () {
      try {
        localStorage.setItem("assistify_customer_session", this.sessionId);
        localStorage.setItem(
          "assistify_customer_history",
          JSON.stringify(this.messageHistory.slice(-20))
        );
      } catch (e) {
        // Silent fail
      }
    },

    /**
     * Restore previous messages
     */
    restoreMessages: function () {
      this.messageHistory.forEach((msg) => {
        this.addMessage(msg.role, msg.content, msg.role === "user", true);
      });
      this.isFirstMessage = false;
    },

    /**
     * Generate UUID
     */
    generateUUID: function () {
      return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(
        /[xy]/g,
        function (c) {
          const r = (Math.random() * 16) | 0;
          const v = c === "x" ? r : (r & 0x3) | 0x8;
          return v.toString(16);
        }
      );
    },

    /**
     * Start idle detection for auto-open
     */
    startIdleDetection: function () {
      const self = this;
      const shownKey = "assistify_auto_open_shown_" + this.getDateKey();

      // Check if already shown today
      try {
        if (localStorage.getItem(shownKey) === "true") {
          this.autoOpenShown = true;
          return;
        }
      } catch (e) {
        // Continue
      }

      // Don't auto-open if user already has chat history (they know it exists)
      if (this.messageHistory.length > 0) {
        this.autoOpenShown = true;
        return;
      }

      this.idleTimer = setTimeout(function () {
        if (!self.autoOpenShown) {
          self.autoOpenChat();
          self.autoOpenShown = true;
          try {
            localStorage.setItem(shownKey, "true");
          } catch (e) {
            // Silent fail
          }
        }
      }, this.idleTimeout);
    },

    /**
     * Reset idle timer - only reset if user is actively engaging
     * We don't reset on every activity to allow proactive engagement
     */
    resetIdleTimer: function () {
      // Don't reset the timer - let it trigger after initial delay
      // This ensures the chat opens even if user is scrolling/reading
    },

    /**
     * Get date key for daily tracking
     */
    getDateKey: function () {
      const now = new Date();
      return now.toISOString().split("T")[0];
    },

    /**
     * Auto-open chat with proactive message
     */
    autoOpenChat: function () {
      // Show the chat (will show consent if not consented, or welcome if consented)
      this.$container.removeAttr("hidden");
      this.$toggle.attr("aria-expanded", "true");
      // Small delay to allow CSS transition to work
      requestAnimationFrame(() => {
        this.$widget.addClass("is-open");
      });

      if (this.hasConsented) {
        // Already consented - show contextual proactive message
        if (this.$messages.children(".assistify-message").length === 0) {
          const proactiveMsg = this.getContextualProactiveMessage();
          this.addMessage("assistant", proactiveMsg, false, true);
        }
        this.$input.focus();
      } else {
        // Not consented - show consent dialog
        this.showConsent();
      }

      setTimeout(() => this.scrollToBottom(), 100);
    },

    /**
     * Get contextual proactive message based on current page
     */
    getContextualProactiveMessage: function () {
      const context = assistifyFrontend.pageContext || {};
      const assistantName = assistifyFrontend.strings.assistantName || "Ayana";

      if (context.type === "product" && context.productName) {
        return `Hi! I'm ${assistantName}. I see you're checking out **${context.productName}**. Would you like to know more about it, or do you have any questions?`;
      } else if (context.type === "cart" || context.isCart) {
        return `Hi! I'm ${assistantName}. I see you have items in your cart. Need help with checkout, shipping options, or have any questions?`;
      } else if (context.type === "checkout" || context.isCheckout) {
        return `Hi! I'm ${assistantName}. Almost done with your order! Let me know if you have any questions about payment or delivery.`;
      } else if (context.type === "category" && context.categoryName) {
        return `Hi! I'm ${assistantName}. Looking for something in **${context.categoryName}**? I can help you find the perfect product!`;
      } else if (context.type === "shop") {
        return `Hi! I'm ${assistantName}. Browsing our products? I can help you find what you're looking for or answer any questions!`;
      } else {
        return `Hi! I'm ${assistantName}, your shopping assistant. Need any help or have questions? I'm here for you!`;
      }
    },

    /**
     * Toggle chat open/closed
     */
    toggleChat: function () {
      const isOpen = this.$widget.hasClass("is-open");

      if (isOpen) {
        this.closeChat();
      } else {
        this.openChat();
      }
    },

    /**
     * Open chat
     */
    openChat: function () {
      // Show consent if not consented
      if (!this.hasConsented) {
        this.showConsent();
        return;
      }

      this.$container.removeAttr("hidden");
      this.$toggle.attr("aria-expanded", "true");
      // Small delay to allow CSS transition to work
      requestAnimationFrame(() => {
        this.$widget.addClass("is-open");
      });

      this.$input.focus();

      // Show messages or welcome
      if (this.$messages.children(".assistify-message").length === 0) {
        this.showWelcome();
      }

      // Scroll to bottom
      setTimeout(() => this.scrollToBottom(), 100);
    },

    /**
     * Close chat
     */
    closeChat: function () {
      this.$widget.removeClass("is-open");
      this.$toggle.attr("aria-expanded", "false");
      // Wait for animation to complete before hiding
      setTimeout(() => {
        if (!this.$widget.hasClass("is-open")) {
          this.$container.attr("hidden", "");
        }
      }, 300);
    },

    /**
     * Show welcome message with context-aware quick questions
     */
    showWelcome: function () {
      // Check if API is offline
      if (!this.isApiOnline) {
        this.showOfflineMessage();
        return;
      }

      const welcomeMsg = this.getDynamicWelcome();
      this.addMessage("assistant", welcomeMsg, false, true);

      // Add context-aware quick questions
      const quickQuestions = this.getSmartQuickQuestions();
      if (quickQuestions.length > 0) {
        let quickHtml = '<div class="assistify-quick-questions">';
        quickQuestions.forEach((q) => {
          quickHtml += `<button type="button" class="assistify-quick-btn" data-question="${this.escapeAttr(
            q
          )}">${this.escapeHtml(q)}</button>`;
        });
        quickHtml += "</div>";
        this.$messages.append(quickHtml);
      }

      this.isFirstMessage = false;
    },

    /**
     * Show offline message with contact link
     */
    showOfflineMessage: function () {
      const offlineMsg =
        assistifyFrontend.strings.offlineMsg ||
        "I'm currently offline. Please contact us directly for assistance.";
      const contactUrl = assistifyFrontend.contactPageUrl || "#";
      const contactText = assistifyFrontend.strings.contactLink || "Contact Us";

      const messageWithLink = `${offlineMsg}\n\n[${contactText}](${contactUrl})`;
      this.addMessage("assistant", messageWithLink, false, true);

      // Disable input
      this.$input
        .prop("disabled", true)
        .attr("placeholder", "Chat currently unavailable");
      this.$form.find('button[type="submit"]').prop("disabled", true);
    },

    /**
     * Get dynamic welcome message
     */
    getDynamicWelcome: function () {
      const assistantName = assistifyFrontend.strings.assistantName || "Ayana";
      const storeName = assistifyFrontend.storeName || "our store";
      const userName = assistifyFrontend.userFirstName || "";
      const context = assistifyFrontend.pageContext || {};

      let greeting = "";

      // Personalize greeting
      if (userName) {
        greeting = `Hi ${userName}! `;
      } else {
        greeting = "Hi there! ";
      }

      greeting += `I'm ${assistantName}, your AI assistant for ${storeName}. `;

      // Context-specific message
      if (context.type === "product" && context.productName) {
        greeting += `I see you're looking at **${context.productName}**. I'd be happy to answer any questions about it!`;
      } else if (context.type === "cart" || context.isCart) {
        greeting += `Ready to check out? Let me know if you have any questions about your order or need help!`;
      } else if (context.type === "checkout" || context.isCheckout) {
        greeting += `Almost there! If you need help with shipping, payment, or have any questions, I'm here.`;
      } else if (context.type === "category" && context.categoryName) {
        greeting += `Looking for something in **${context.categoryName}**? I can help you find the perfect product!`;
      } else {
        greeting += `How can I help you today?`;
      }

      return greeting;
    },

    /**
     * Get smart quick questions based on context and user type
     */
    getSmartQuickQuestions: function () {
      const isLoggedIn = assistifyFrontend.isLoggedIn;
      const context = assistifyFrontend.pageContext || {};
      const questions = [];

      // Context-based questions
      if (context.type === "product") {
        questions.push("Is this product in stock?");
        questions.push("What are the shipping options?");
        questions.push("Do you have a size guide?");
      } else if (context.type === "cart" || context.isCart) {
        questions.push("Do you have any discount codes?");
        questions.push("What payment methods do you accept?");
        questions.push("How long will delivery take?");
      } else if (context.type === "checkout" || context.isCheckout) {
        questions.push("Is my payment secure?");
        questions.push("Can I change my shipping address?");
        questions.push("When will I receive my order?");
      } else if (context.type === "category") {
        questions.push("What's your best seller?");
        questions.push("Do you have any sales?");
        questions.push("Can you recommend something?");
      } else if (isLoggedIn) {
        // Logged-in user default questions
        questions.push("Where is my order?");
        questions.push("How do I return an item?");
        questions.push("Can I change my order?");
      } else {
        // Guest user default questions (no order-related)
        questions.push("What products do you have?");
        questions.push("What are your shipping options?");
        questions.push("Do you ship internationally?");
      }

      return questions.slice(0, 3);
    },

    /**
     * Show consent dialog
     */
    showConsent: function () {
      this.$container.removeAttr("hidden");
      this.$toggle.attr("aria-expanded", "true");
      // Small delay to allow CSS transition to work
      requestAnimationFrame(() => {
        this.$widget.addClass("is-open");
      });

      // Build privacy link HTML if URL is available
      let privacyLinkHtml = "";
      if (assistifyFrontend.privacyUrl) {
        privacyLinkHtml = `
          <p class="assistify-consent-privacy">
            <a href="${assistifyFrontend.privacyUrl}" target="_blank" rel="noopener noreferrer">
              ${assistifyFrontend.strings.privacyLink}
            </a>
          </p>
        `;
      }

      const consentHtml = `
        <div class="assistify-consent-modal">
          <div class="assistify-consent-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="currentColor">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h2v2h-2v-2zm0-8h2v6h-2V9z"/>
            </svg>
          </div>
          <h4>${assistifyFrontend.strings.consentTitle}</h4>
          <p>${assistifyFrontend.strings.consentText}</p>
          ${privacyLinkHtml}
          <div class="assistify-consent-buttons">
            <button type="button" class="assistify-consent-agree">${assistifyFrontend.strings.consentAgree}</button>
            <button type="button" class="assistify-consent-decline">${assistifyFrontend.strings.consentDecline}</button>
          </div>
        </div>
      `;

      this.$messages.html(consentHtml);

      const self = this;

      // Agree button
      this.$messages.find(".assistify-consent-agree").on("click", function () {
        self.setConsent(true);
        self.hasConsented = true;
        self.$messages.empty();
        self.showWelcome();
        self.$input.focus();
      });

      // Decline button
      this.$messages
        .find(".assistify-consent-decline")
        .on("click", function () {
          self.setConsent(false);
          self.closeChat();
        });
    },

    /**
     * Get consent from storage
     *
     * @return {boolean} Whether user has consented
     */
    getConsent: function () {
      try {
        return localStorage.getItem("assistify_consent") === "true";
      } catch (e) {
        return false;
      }
    },

    /**
     * Set consent in storage
     *
     * @param {boolean} value - Consent value
     */
    setConsent: function (value) {
      try {
        localStorage.setItem("assistify_consent", value ? "true" : "false");
      } catch (e) {
        // Silent fail for private browsing
      }
    },

    /**
     * Send message
     */
    sendMessage: function () {
      const message = this.$input.val().trim();

      if (!message || this.isStreaming) {
        return;
      }

      // Remove quick questions after first message
      this.$messages.find(".assistify-quick-questions").remove();

      // Add user message
      this.addMessage("user", message, true, false);
      this.$input.val("").prop("disabled", true);

      // Add to history
      this.messageHistory.push({ role: "user", content: message });

      // Show typing indicator
      this.showTypingIndicator();

      // Send to server
      $.ajax({
        url: assistifyFrontend.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_customer_chat",
          nonce: assistifyFrontend.nonce,
          message: message,
          session_id: this.sessionId,
          history: this.messageHistory.slice(-10),
        },
        success: (response) => {
          this.hideTypingIndicator();

          if (response.success) {
            // Update session ID if returned
            if (response.data.session_id) {
              this.sessionId = response.data.session_id;
            }

            // Stream the response
            this.streamResponse(response.data.message);

            // Add to history
            this.messageHistory.push({
              role: "assistant",
              content: response.data.message,
            });
            this.saveSession();
          } else {
            this.addMessage(
              "assistant",
              response.data.message || assistifyFrontend.strings.error,
              false,
              false
            );
            this.$input.prop("disabled", false).focus();
          }
        },
        error: () => {
          this.hideTypingIndicator();
          this.addMessage(
            "assistant",
            assistifyFrontend.strings.error,
            false,
            false
          );
          this.$input.prop("disabled", false).focus();
        },
      });
    },

    /**
     * Stream response like ChatGPT
     */
    streamResponse: function (content) {
      this.isStreaming = true;

      const messageId = "msg-" + Date.now();
      const messageHtml = `
        <div class="assistify-message assistify-message-assistant assistify-message-streaming" id="${messageId}">
          <div class="assistify-message-content"><span class="assistify-stream-cursor"></span></div>
        </div>
      `;

      this.$messages.append(messageHtml);
      const $messageContent = $(`#${messageId} .assistify-message-content`);

      // Tokenize into words
      const words = this.tokenizeForStreaming(content);
      let index = 0;
      let displayedContent = "";
      let lastScrollTime = 0;

      const streamWord = (timestamp) => {
        if (index < words.length) {
          // Add multiple words per frame
          const wordsPerFrame = Math.min(2, words.length - index);

          for (let i = 0; i < wordsPerFrame; i++) {
            displayedContent += words[index];
            index++;
          }

          $messageContent.html(
            this.parseMarkdownPartial(displayedContent) +
              '<span class="assistify-stream-cursor"></span>'
          );

          // Throttle scrolling
          if (timestamp - lastScrollTime > 100) {
            this.scrollToBottom();
            lastScrollTime = timestamp;
          }

          setTimeout(() => {
            requestAnimationFrame(streamWord);
          }, this.streamingSpeed);
        } else {
          // Streaming complete
          this.finalizeStreamedMessage(messageId, content);
        }
      };

      requestAnimationFrame(streamWord);
    },

    /**
     * Tokenize content for streaming
     */
    tokenizeForStreaming: function (content) {
      return content.match(/(\S+)(\s*)/g) || [content];
    },

    /**
     * Parse markdown during streaming (partial, safe)
     */
    parseMarkdownPartial: function (text) {
      let html = this.escapeHtml(text);
      html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
      html = html.replace(/\n/g, "<br>");
      return html;
    },

    /**
     * Finalize streamed message
     */
    finalizeStreamedMessage: function (messageId, content) {
      const $message = $(`#${messageId}`);
      $message.removeClass("assistify-message-streaming");

      // Parse markdown
      const parsedContent = this.parseMarkdown(content);

      // Add copy button
      const footerHtml = `
        <div class="assistify-message-footer">
          <button type="button" class="assistify-copy-btn" data-message-id="${messageId}" data-raw-content="${this.encodeHtmlEntities(
        content
      )}" title="Copy">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
              <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
            </svg>
          </button>
        </div>
      `;

      $message.find(".assistify-message-content").html(parsedContent);
      $message.append(footerHtml);

      this.isStreaming = false;
      this.$input.prop("disabled", false).focus();
      this.scrollToBottom();

      // Play sound
      this.playSound();
    },

    /**
     * Add message to chat
     *
     * @param {string} role - 'user' or 'assistant'
     * @param {string} content - Message content
     * @param {boolean} isUser - Whether this is a user message
     * @param {boolean} skipAnimation - Skip slide animation
     */
    addMessage: function (role, content, isUser, skipAnimation) {
      const messageId = "msg-" + Date.now();
      const parsedContent = isUser
        ? this.escapeHtml(content)
        : this.parseMarkdown(content);

      let footerHtml = "";
      if (!isUser) {
        footerHtml = `
          <div class="assistify-message-footer">
            <button type="button" class="assistify-copy-btn" data-message-id="${messageId}" data-raw-content="${this.encodeHtmlEntities(
          content
        )}" title="Copy">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
                <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
              </svg>
            </button>
          </div>
        `;
      }

      const animClass = skipAnimation ? "no-animation" : "";
      const messageHtml = `
        <div class="assistify-message assistify-message-${role} ${animClass}" id="${messageId}">
          <div class="assistify-message-content">${parsedContent}</div>
          ${footerHtml}
        </div>
      `;

      this.$messages.append(messageHtml);
      this.scrollToBottom();
    },

    /**
     * Parse markdown to HTML
     */
    parseMarkdown: function (text) {
      if (!text) return "";

      let html = this.escapeHtml(text);

      // Code blocks
      html = html.replace(
        /```(\w*)\n?([\s\S]*?)```/g,
        '<pre><code class="language-$1">$2</code></pre>'
      );

      // Inline code
      html = html.replace(/`([^`]+)`/g, "<code>$1</code>");

      // Bold
      html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");

      // Italic
      html = html.replace(/\*([^*]+)\*/g, "<em>$1</em>");

      // Links - internal links open in same tab, external in new tab
      html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (match, text, url) => {
        const isInternal = this.isInternalLink(url);
        if (isInternal) {
          return `<a href="${url}">${text}</a>`;
        }
        return `<a href="${url}" target="_blank" rel="noopener noreferrer">${text}</a>`;
      });

      // Line breaks
      html = html.replace(/\n/g, "<br>");

      // Lists
      html = html.replace(/^- (.+)$/gm, "<li>$1</li>");
      html = html.replace(/(<li>[\s\S]*?<\/li>)/g, "<ul>$1</ul>");

      return html;
    },

    /**
     * Copy message to clipboard
     */
    copyMessage: function (messageId) {
      const $btn = this.$messages.find(
        `.assistify-copy-btn[data-message-id="${messageId}"]`
      );
      const rawContent = this.decodeHtmlEntities($btn.data("raw-content"));

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(rawContent).then(() => {
          this.showCopyFeedback($btn);
        });
      } else {
        // Fallback
        const textarea = document.createElement("textarea");
        textarea.value = rawContent;
        textarea.style.position = "fixed";
        textarea.style.opacity = "0";
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand("copy");
        document.body.removeChild(textarea);
        this.showCopyFeedback($btn);
      }
    },

    /**
     * Show copy feedback
     */
    showCopyFeedback: function ($btn) {
      $btn.addClass("is-copied");
      $btn.html(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
      );

      setTimeout(() => {
        $btn.removeClass("is-copied");
        $btn.html(
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>'
        );
      }, 2000);
    },

    /**
     * Encode HTML entities
     */
    encodeHtmlEntities: function (text) {
      return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    },

    /**
     * Decode HTML entities
     */
    decodeHtmlEntities: function (text) {
      const textarea = document.createElement("textarea");
      textarea.innerHTML = text;
      return textarea.value;
    },

    /**
     * Show typing indicator
     */
    showTypingIndicator: function () {
      const indicatorHtml = `
        <div class="assistify-message assistify-message-assistant assistify-typing">
          <div class="assistify-typing-indicator">
            <span></span>
            <span></span>
            <span></span>
          </div>
        </div>
      `;

      this.$messages.append(indicatorHtml);
      this.scrollToBottom();
    },

    /**
     * Hide typing indicator
     */
    hideTypingIndicator: function () {
      this.$messages.find(".assistify-typing").remove();
    },

    /**
     * Scroll messages to bottom
     */
    scrollToBottom: function () {
      const doScroll = () => {
        if (this.$messages && this.$messages[0]) {
          this.$messages[0].scrollTop = this.$messages[0].scrollHeight + 10000;
        }
      };

      requestAnimationFrame(() => {
        doScroll();
        setTimeout(doScroll, 100);
      });
    },

    /**
     * Play notification sound
     */
    playSound: function () {
      if (!this.soundEnabled) return;

      // Check for custom sound URL
      if (assistifyFrontend.settings.customSoundUrl) {
        try {
          const audio = new Audio(assistifyFrontend.settings.customSoundUrl);
          audio.volume = 0.3;
          audio.play().catch(() => {});
          return;
        } catch (e) {
          // Fall through to default
        }
      }

      // Default sound using Web Audio API
      try {
        const audioContext = new (window.AudioContext ||
          window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        oscillator.frequency.value = 800;
        oscillator.type = "sine";
        gainNode.gain.value = 0.1;

        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.1);
      } catch (e) {
        // Silent fail
      }
    },

    /**
     * Escape HTML to prevent XSS
     *
     * @param {string} text - Text to escape
     * @return {string} Escaped text
     */
    escapeHtml: function (text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },

    /**
     * Escape attribute value
     */
    escapeAttr: function (text) {
      return text
        .replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
    },

    /**
     * Check if URL is internal (same domain)
     */
    isInternalLink: function (url) {
      if (!url) return false;

      // Relative URLs are internal
      if (url.startsWith("/") || url.startsWith("#")) {
        return true;
      }

      try {
        const linkUrl = new URL(url, window.location.origin);
        return linkUrl.hostname === window.location.hostname;
      } catch (e) {
        return false;
      }
    },

    /**
     * Start new chat
     */
    startNewChat: function () {
      this.sessionId = this.generateUUID();
      this.messageHistory = [];
      this.isFirstMessage = true;
      this.saveSession();
      this.$messages.empty();
      this.showWelcome();
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    AssistifyChat.init();
  });
})(jQuery);
