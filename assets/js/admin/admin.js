/**
 * Assistify for WooCommerce - Admin JavaScript
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

/* global jQuery, assistifyAdmin */

(function ($) {
  "use strict";

  /**
   * Simple Markdown Parser for chat messages.
   */
  const MarkdownParser = {
    /**
     * Parse markdown to HTML.
     *
     * @param {string} text - Markdown text.
     * @return {string} HTML string.
     */
    parse: function (text) {
      if (!text) return "";

      let html = this.escapeHtml(text);

      // Parse tables first (before other transformations)
      html = this.parseTables(html);

      // Code blocks (``` ... ```)
      html = html.replace(
        /```(\w*)\n?([\s\S]*?)```/g,
        '<pre class="assistify-code-block"><code class="language-$1">$2</code></pre>'
      );

      // Inline code (`code`)
      html = html.replace(
        /`([^`]+)`/g,
        '<code class="assistify-inline-code">$1</code>'
      );

      // Bold (**text** or __text__)
      html = html.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
      html = html.replace(/__([^_]+)__/g, "<strong>$1</strong>");

      // Italic (*text* or _text_)
      html = html.replace(/\*([^*]+)\*/g, "<em>$1</em>");
      html = html.replace(/_([^_]+)_/g, "<em>$1</em>");

      // Headers (### Header)
      html = html.replace(/^### (.+)$/gm, '<h4 class="assistify-h4">$1</h4>');
      html = html.replace(/^## (.+)$/gm, '<h3 class="assistify-h3">$1</h3>');
      html = html.replace(/^# (.+)$/gm, '<h2 class="assistify-h2">$1</h2>');

      // Unordered lists
      html = html.replace(/^\s*[-*]\s+(.+)$/gm, "<li>$1</li>");
      html = html.replace(/(<li>.*<\/li>\n?)+/g, "<ul>$&</ul>");

      // Ordered lists
      html = html.replace(/^\s*\d+\.\s+(.+)$/gm, "<li>$1</li>");

      // Links [text](url) - internal links same tab, external new tab
      html = html.replace(
        /\[([^\]]+)\]\(([^)]+)\)/g,
        function (match, text, url) {
          var isInternal =
            url.startsWith("/") ||
            url.startsWith("#") ||
            url.indexOf(window.location.hostname) !== -1;
          if (isInternal) {
            return '<a href="' + url + '">' + text + "</a>";
          }
          return (
            '<a href="' +
            url +
            '" target="_blank" rel="noopener">' +
            text +
            "</a>"
          );
        }
      );

      // Line breaks (double newline = paragraph)
      html = html.replace(/\n\n/g, "</p><p>");
      html = "<p>" + html + "</p>";

      // Clean up empty paragraphs and table wrapping
      html = html.replace(/<p>\s*<\/p>/g, "");
      html = html.replace(/<p>\s*(<h[234])/g, "$1");
      html = html.replace(/(<\/h[234]>)\s*<\/p>/g, "$1");
      html = html.replace(/<p>\s*(<ul>)/g, "$1");
      html = html.replace(/(<\/ul>)\s*<\/p>/g, "$1");
      html = html.replace(/<p>\s*(<pre)/g, "$1");
      html = html.replace(/(<\/pre>)\s*<\/p>/g, "$1");
      html = html.replace(/<p>\s*(<table)/g, "$1");
      html = html.replace(/(<\/table>)\s*<\/p>/g, "$1");

      // Single line breaks
      html = html.replace(/\n/g, "<br>");

      return html;
    },

    /**
     * Parse markdown tables to HTML.
     *
     * @param {string} text - Text with potential tables.
     * @return {string} Text with tables converted to HTML.
     */
    parseTables: function (text) {
      const lines = text.split("\n");
      let result = [];
      let tableLines = [];
      let inTable = false;

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();

        // Check if line looks like a table row (starts and ends with |)
        if (line.startsWith("|") && line.endsWith("|")) {
          // Skip separator lines (|---|---|)
          if (
            /^\|[\s\-:]+\|$/.test(
              line.replace(/\|/g, "|").replace(/[\s\-:]/g, "")
            )
          ) {
            continue;
          }

          if (!inTable) {
            inTable = true;
            tableLines = [];
          }
          tableLines.push(line);
        } else if (line.match(/^\|?[\s\-:]+\|[\s\-:|]+\|?$/)) {
          // This is a separator line, skip it
          continue;
        } else {
          // Not a table line
          if (inTable && tableLines.length > 0) {
            result.push(this.buildTable(tableLines));
            tableLines = [];
            inTable = false;
          }
          result.push(lines[i]);
        }
      }

      // Handle table at end of text
      if (inTable && tableLines.length > 0) {
        result.push(this.buildTable(tableLines));
      }

      return result.join("\n");
    },

    /**
     * Build HTML table from table lines.
     *
     * @param {Array} lines - Array of table row strings.
     * @return {string} HTML table.
     */
    buildTable: function (lines) {
      if (lines.length === 0) return "";

      let html = '<table class="assistify-table">';

      lines.forEach((line, index) => {
        const cells = line
          .split("|")
          .filter((cell) => cell.trim() !== "")
          .map((cell) => cell.trim());

        if (cells.length === 0) return;

        const tag = index === 0 ? "th" : "td";
        const rowTag = index === 0 ? "thead" : index === 1 ? "tbody" : "";
        const rowEndTag = index === 0 ? "</thead>" : "";

        if (index === 1) {
          html += "<tbody>";
        }

        html += "<tr>";
        cells.forEach((cell) => {
          html += `<${tag}>${cell}</${tag}>`;
        });
        html += "</tr>";

        if (index === 0) {
          html += "</thead>";
        }
      });

      html += "</tbody></table>";
      return html;
    },

    /**
     * Escape HTML entities.
     *
     * @param {string} text - Text to escape.
     * @return {string} Escaped text.
     */
    escapeHtml: function (text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },
  };

  /**
   * Assistify Admin Chat
   */
  const AssistifyAdminChat = {
    sessionId: null,
    currentTab: "chat",
    sessions: [],
    isStreaming: false,
    streamingSpeed: 20, // milliseconds per word chunk (faster = smoother)

    /**
     * Initialize
     */
    init: function () {
      // Check if assistifyAdmin exists and chat is enabled
      if (
        typeof assistifyAdmin === "undefined" ||
        !assistifyAdmin.settings ||
        assistifyAdmin.settings.chatEnabled !== "yes"
      ) {
        return;
      }

      this.createWidget();
      this.bindEvents();
      this.loadSessionId();
      this.loadSessions();

      // Load messages for current session if it exists
      this.loadCurrentSessionMessages();
    },

    /**
     * Load messages for the current session on page load.
     */
    loadCurrentSessionMessages: function () {
      if (!this.sessionId) {
        return;
      }

      $.ajax({
        url: assistifyAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_get_session_messages",
          nonce: assistifyAdmin.nonce,
          session_id: this.sessionId,
        },
        success: (response) => {
          if (
            response.success &&
            response.data.messages &&
            response.data.messages.length > 0
          ) {
            // Clear welcome message and load session messages
            this.$messages.empty();
            response.data.messages.forEach((msg) => {
              this.addMessage(msg.role, msg.content, true, false);
            });
            // Always scroll to bottom to show last message
            // Use multiple scroll attempts to handle async rendering
            this.scrollToBottom();
            setTimeout(() => this.scrollToBottom(), 200);
            setTimeout(() => this.scrollToBottom(), 500);
          }
          // If no messages, keep the welcome message that was added in createWidget
        },
      });
    },

    /**
     * Load or create session ID.
     */
    loadSessionId: function () {
      const stored = localStorage.getItem("assistify_session_id");
      if (stored) {
        this.sessionId = stored;
      } else {
        this.sessionId = this.generateSessionId();
        localStorage.setItem("assistify_session_id", this.sessionId);
      }
    },

    /**
     * Generate a simple session ID.
     */
    generateSessionId: function () {
      return (
        "afw_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9)
      );
    },

    /**
     * Load user's chat sessions from server.
     */
    loadSessions: function () {
      $.ajax({
        url: assistifyAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_get_sessions",
          nonce: assistifyAdmin.nonce,
        },
        success: (response) => {
          if (response.success && response.data.sessions) {
            this.sessions = response.data.sessions;
            this.updateSessionsCount();

            // Re-render if currently viewing History tab.
            if (this.currentTab === "history") {
              this.renderSessions();
            }
          }
        },
      });
    },

    /**
     * Update sessions count badge.
     */
    updateSessionsCount: function () {
      const count = this.sessions.length;
      const $badge = this.$widget.find(".assistify-tab-badge");
      if (count > 0) {
        $badge.text(count).show();
      } else {
        $badge.hide();
      }
    },

    /**
     * Create the chat widget
     */
    createWidget: function () {
      const widgetHtml = `
        <div class="assistify-admin-chat">
          <button type="button" class="assistify-admin-chat-toggle" aria-expanded="false" aria-label="${
            assistifyAdmin.strings.openChat || "Chat with Ayana"
          }">
            <span class="assistify-chat-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.36 5.07L2 22l4.93-1.36C8.42 21.5 10.15 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.57 0-3.05-.43-4.32-1.18l-.31-.18-3.22.89.89-3.22-.18-.31C4.43 15.05 4 13.57 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8-3.59 8-8 8z"/>
              </svg>
            </span>
            <span class="assistify-close-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
              </svg>
            </span>
          </button>
          <span class="assistify-keyboard-hint">Press <kbd>Ctrl</kbd>+<kbd>/</kbd></span>
          <div class="assistify-admin-chat-container">
            <div class="assistify-admin-chat-header">
              <div class="assistify-header-content">
                <h3>Assistify<span class="assistify-status-dot ${
                  assistifyAdmin.settings.apiConfigured
                    ? "is-online"
                    : "is-offline"
                }" title="${
        assistifyAdmin.settings.apiConfigured
          ? "Connected"
          : "API key not configured"
      }"></span></h3>
                <span class="assistify-header-subtitle">Store Intelligence</span>
              </div>
              <button type="button" class="assistify-admin-chat-close" aria-label="Close chat">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                  <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
              </button>
            </div>
            <div class="assistify-chat-tabs">
              <button type="button" class="assistify-tab is-active" data-tab="chat">Chat</button>
              <button type="button" class="assistify-tab" data-tab="history">History <span class="assistify-tab-badge" style="display:none;">0</span></button>
            </div>
            <div class="assistify-tab-content assistify-tab-chat is-active">
              <div class="assistify-admin-chat-messages" role="log" aria-live="polite"></div>
              <form class="assistify-admin-chat-form">
                <input type="text" class="assistify-admin-chat-input" placeholder="${
                  assistifyAdmin.strings.placeholder || "Ask Ayana anything..."
                }" autocomplete="off">
                <button type="submit" class="assistify-admin-chat-send" aria-label="Send message">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                  </svg>
                </button>
              </form>
            </div>
            <div class="assistify-tab-content assistify-tab-history">
              <div class="assistify-sessions-list"></div>
              <button type="button" class="assistify-new-chat-btn">+ Start New Chat</button>
            </div>
          </div>
        </div>
      `;

      $("body").append(widgetHtml);

      this.$widget = $(".assistify-admin-chat");
      this.$toggle = this.$widget.find(".assistify-admin-chat-toggle");
      this.$container = this.$widget.find(".assistify-admin-chat-container");
      this.$messages = this.$widget.find(".assistify-admin-chat-messages");
      this.$form = this.$widget.find(".assistify-admin-chat-form");
      this.$input = this.$widget.find(".assistify-admin-chat-input");
      this.$close = this.$widget.find(".assistify-admin-chat-close");
      this.$send = this.$widget.find(".assistify-admin-chat-send");
      this.$sessionsList = this.$widget.find(".assistify-sessions-list");

      // Add welcome message
      this.addMessage("assistant", this.getWelcomeMessage());
    },

    /**
     * Get a random creative welcome message.
     */
    getWelcomeMessage: function () {
      const greetings = [
        "Hey, I'm **Ayana**. I work behind the scenes of your store and know your products, orders, customers, and sales inside out. Tell me what you need.",
        "Hi, I'm **Ayana**. I keep an eye on your products, orders, customers, and sales. Ask me anything and I'll pull the answers for you.",
        "I'm **Ayana**. I sit inside your store and understand your data from top to bottom. Tell me what you want to check or fix.",
        "**Ayana** here. I know your store's numbers, customers, and products by heart. Ask away.",
        "Hey! **Ayana** at your service. I've got full access to your store data. What would you like to know?",
      ];

      const randomIndex = Math.floor(Math.random() * greetings.length);
      return greetings[randomIndex];
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      const self = this;

      // Toggle chat
      this.$toggle.on("click", function () {
        self.toggleChat();
      });

      // Close chat
      this.$close.on("click", function () {
        self.closeChat();
      });

      // Submit message
      this.$form.on("submit", function (e) {
        e.preventDefault();
        self.sendMessage();
      });

      // Tab switching
      this.$widget.on("click", ".assistify-tab", function () {
        const tab = $(this).data("tab");
        self.switchTab(tab);
      });

      // Load session from history
      this.$widget.on("click", ".assistify-session-item", function () {
        const sessionId = $(this).data("session-id");
        self.loadSession(sessionId);
      });

      // Start new chat
      this.$widget.on("click", ".assistify-new-chat-btn", function () {
        self.startNewChat();
      });

      // Delete individual session
      this.$widget.on("click", ".assistify-session-delete", function (e) {
        e.stopPropagation(); // Prevent triggering session load
        const sessionId = $(this).data("session-id");
        self.deleteSession(sessionId);
      });

      // Clear all history
      this.$widget.on("click", ".assistify-clear-all-btn", function () {
        self.clearAllSessions();
      });

      // Keyboard shortcut (Ctrl + /)
      $(document).on("keydown", function (e) {
        if (e.ctrlKey && e.key === "/") {
          e.preventDefault();
          self.toggleChat();
        }
        if (e.key === "Escape" && self.$container.hasClass("is-open")) {
          self.closeChat();
        }
      });

      // Handle Enter key in input
      this.$input.on("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          self.sendMessage();
        }
      });

      // Copy message button
      this.$widget.on("click", ".assistify-copy-btn", function (e) {
        e.preventDefault();
        const messageId = $(this).data("message-id");
        self.copyMessage(messageId);
      });
    },

    /**
     * Toggle chat open/closed
     */
    toggleChat: function () {
      const isOpen = this.$container.hasClass("is-open");

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
      this.$toggle.attr("aria-expanded", "true");
      // Small delay to allow CSS transition to work
      requestAnimationFrame(() => {
        this.$container.addClass("is-open");
      });
      this.$input.focus();
      // Scroll to bottom when opening chat
      setTimeout(() => this.scrollToBottom(), 100);
    },

    /**
     * Close chat
     */
    closeChat: function () {
      this.$container.removeClass("is-open");
      this.$toggle.attr("aria-expanded", "false");
    },

    /**
     * Send message
     */
    sendMessage: function () {
      const message = this.$input.val().trim();

      if (!message || this.isStreaming) {
        return;
      }

      // Disable input while processing
      this.$input.prop("disabled", true);
      this.$send.prop("disabled", true);

      // Add user message
      this.addMessage("user", message);
      this.$input.val("");

      // Show typing indicator
      this.showTypingIndicator();

      // Send to server
      $.ajax({
        url: assistifyAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_admin_chat",
          nonce: assistifyAdmin.nonce,
          message: message,
          session_id: this.sessionId,
        },
        success: (response) => {
          this.hideTypingIndicator();

          if (response.success) {
            // Check if this is an action requiring confirmation
            if (response.data.pending_action) {
              this.showActionConfirmation(response.data);
            } else {
              // Stream the response for better UX
              this.streamResponse(response.data.message);
            }
          } else {
            this.$input.prop("disabled", false);
            this.$send.prop("disabled", false);
            this.$input.focus();
            this.addMessage(
              "assistant",
              response.data.message || assistifyAdmin.strings.error,
              false,
              true
            );
          }
        },
        error: () => {
          this.hideTypingIndicator();
          this.$input.prop("disabled", false);
          this.$send.prop("disabled", false);
          this.$input.focus();
          this.addMessage(
            "assistant",
            assistifyAdmin.strings.error,
            false,
            true
          );
        },
      });
    },

    /**
     * Stream a response word by word for a smooth typing effect like ChatGPT.
     *
     * @param {string} content - The full response content.
     */
    streamResponse: function (content) {
      this.isStreaming = true;

      const time = new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      });

      const messageId =
        "msg_" + Date.now() + "_" + Math.random().toString(36).substr(2, 5);

      // Create message container with streaming class
      const messageHtml = `
        <div class="assistify-message assistify-message-assistant assistify-message-streaming" id="${messageId}" data-raw-content="">
          <div class="assistify-message-content"></div>
          <div class="assistify-message-footer">
            <span class="assistify-message-time">${time}</span>
          </div>
        </div>
      `;

      this.$messages.append(messageHtml);
      const $message = $("#" + messageId);
      const $content = $message.find(".assistify-message-content");

      // Tokenize content into words for natural streaming
      const words = this.tokenizeForStreaming(content);
      let index = 0;
      let displayedContent = "";
      let lastScrollTime = 0;

      const self = this;

      // Use requestAnimationFrame for smoother animation
      const streamWord = (timestamp) => {
        if (index < words.length) {
          // Add multiple words per frame for faster streaming
          const wordsPerFrame = Math.min(2, words.length - index);

          for (let i = 0; i < wordsPerFrame; i++) {
            displayedContent += words[index];
            index++;
          }

          // Update display with cursor
          $content.html(
            this.escapeHtmlForStreaming(displayedContent) +
              '<span class="assistify-stream-cursor"></span>'
          );

          // Throttle scrolling for performance (every 100ms)
          if (timestamp - lastScrollTime > 100) {
            this.scrollToBottom();
            lastScrollTime = timestamp;
          }

          // Schedule next frame with slight delay for natural feel
          setTimeout(() => {
            requestAnimationFrame(streamWord);
          }, this.streamingSpeed);
        } else {
          // Streaming complete - finalize the message
          this.finalizeStreamedMessage($message, $content, content, messageId);
        }
      };

      // Start streaming
      requestAnimationFrame(streamWord);
    },

    /**
     * Escape HTML for streaming display (basic escaping without full parsing).
     *
     * @param {string} text - Text to escape.
     * @return {string} Escaped text with newlines preserved.
     */
    escapeHtmlForStreaming: function (text) {
      return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\n/g, "<br>");
    },

    /**
     * Finalize a streamed message with markdown parsing and copy button.
     *
     * @param {jQuery} $message - The message element.
     * @param {jQuery} $content - The content element.
     * @param {string} content - The full raw content.
     * @param {string} messageId - The message ID.
     */
    finalizeStreamedMessage: function ($message, $content, content, messageId) {
      // Remove cursor and parse markdown
      $content.find(".assistify-stream-cursor").remove();
      const parsedContent = MarkdownParser.parse(content);
      $content.html(parsedContent);

      // Update raw content for copy functionality
      $message.attr("data-raw-content", this.encodeHtmlEntities(content));
      $message.removeClass("assistify-message-streaming");

      // Add copy button
      const copyButton = `
        <button type="button" class="assistify-copy-btn" data-message-id="${messageId}" title="Copy message">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
            <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
          </svg>
        </button>
      `;
      $message.find(".assistify-message-footer").append(copyButton);

      // Re-enable input
      this.isStreaming = false;
      this.$input.prop("disabled", false);
      this.$send.prop("disabled", false);
      this.$input.focus();

      this.scrollToBottom();

      // Refresh sessions list to include this session
      this.loadSessions();
    },

    /**
     * Show action confirmation UI with confirm/cancel buttons.
     *
     * @param {Object} data - Response data with pending action info.
     */
    showActionConfirmation: function (data) {
      const time = new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      });

      const messageId =
        "msg_" + Date.now() + "_" + Math.random().toString(36).substr(2, 5);

      // Parse the message for display
      const parsedContent = MarkdownParser.parse(data.message);

      // Build confirmation buttons
      const destructiveClass = data.is_destructive
        ? "assistify-btn-destructive"
        : "";
      const confirmText = data.is_destructive ? "Yes, proceed" : "Confirm";

      const buttonsHtml = `
        <div class="assistify-action-buttons" data-token="${data.confirmation_token}">
          <button type="button" class="assistify-btn assistify-btn-confirm ${destructiveClass}">
            ${confirmText}
          </button>
          <button type="button" class="assistify-btn assistify-btn-cancel">
            Cancel
          </button>
        </div>
      `;

      // Create message container
      const messageHtml = `
        <div class="assistify-message assistify-message-assistant assistify-message-action" id="${messageId}">
          <div class="assistify-message-content">${parsedContent}</div>
          ${buttonsHtml}
          <div class="assistify-message-footer">
            <span class="assistify-message-time">${time}</span>
          </div>
        </div>
      `;

      this.$messages.append(messageHtml);
      this.scrollToBottom();

      // Store the token for later use
      this.pendingActionToken = data.confirmation_token;

      // Re-enable input
      this.$input.prop("disabled", false);
      this.$send.prop("disabled", false);

      // Bind button events
      const self = this;
      const $message = $("#" + messageId);

      $message.find(".assistify-btn-confirm").on("click", function () {
        self.confirmAction(data.confirmation_token, $message);
      });

      $message.find(".assistify-btn-cancel").on("click", function () {
        self.cancelAction(data.confirmation_token, $message);
      });
    },

    /**
     * Confirm and execute a pending action.
     *
     * @param {string} token - Confirmation token.
     * @param {jQuery} $message - The message element.
     */
    confirmAction: function (token, $message) {
      // Disable buttons while processing
      $message
        .find(".assistify-action-buttons")
        .html('<span class="assistify-action-processing">Processing...</span>');

      const self = this;

      $.ajax({
        url: assistifyAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_confirm_action",
          nonce: assistifyAdmin.nonce,
          confirmation_token: token,
          session_id: this.sessionId,
        },
        success: function (response) {
          // Remove the action buttons
          $message
            .find(".assistify-action-buttons, .assistify-action-processing")
            .remove();
          $message.removeClass("assistify-message-action");

          if (response.success) {
            // Add success message
            self.addMessage("assistant", response.data.message, true, false);
          } else {
            self.addMessage(
              "assistant",
              "❌ " + (response.data.message || "Action failed."),
              false,
              true
            );
          }
        },
        error: function () {
          $message
            .find(".assistify-action-buttons, .assistify-action-processing")
            .remove();
          self.addMessage(
            "assistant",
            "❌ Failed to execute action. Please try again.",
            false,
            true
          );
        },
      });
    },

    /**
     * Cancel a pending action.
     *
     * @param {string} token - Confirmation token.
     * @param {jQuery} $message - The message element.
     */
    cancelAction: function (token, $message) {
      const self = this;

      $.ajax({
        url: assistifyAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_cancel_action",
          nonce: assistifyAdmin.nonce,
          confirmation_token: token,
          session_id: this.sessionId,
        },
        success: function (response) {
          // Remove the action buttons
          $message.find(".assistify-action-buttons").remove();
          $message.removeClass("assistify-message-action");

          // Update message to show cancelled
          $message
            .find(".assistify-message-content")
            .html(
              MarkdownParser.parse(
                "~~Action cancelled.~~ Is there anything else I can help you with?"
              )
            );
        },
        error: function () {
          $message.find(".assistify-action-buttons").remove();
        },
      });
    },

    /**
     * Tokenize content into words for natural streaming like ChatGPT.
     *
     * @param {string} content - The content to tokenize.
     * @return {Array} Array of word tokens.
     */
    tokenizeForStreaming: function (content) {
      // Split into words while preserving spaces and punctuation
      // This creates natural word-by-word flow like ChatGPT
      const tokens = [];
      const regex = /(\S+)(\s*)/g;
      let match;

      while ((match = regex.exec(content)) !== null) {
        // Add word + following whitespace as one token
        tokens.push(match[1] + match[2]);
      }

      return tokens;
    },

    /**
     * Add message to chat
     *
     * @param {string} role - 'user' or 'assistant'
     * @param {string} content - Message content
     * @param {boolean} parseMarkdown - Whether to parse markdown
     * @param {boolean} isError - Whether this is an error message
     */
    addMessage: function (
      role,
      content,
      parseMarkdown = true,
      isError = false
    ) {
      const time = new Date().toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      });

      let displayContent = content;
      if (parseMarkdown && role === "assistant") {
        displayContent = MarkdownParser.parse(content);
      } else {
        displayContent = MarkdownParser.escapeHtml(content);
      }

      const errorClass = isError ? " assistify-message-error" : "";
      const messageId =
        "msg_" + Date.now() + "_" + Math.random().toString(36).substr(2, 5);

      // Copy button for assistant messages
      const copyButton =
        role === "assistant" && !isError
          ? `
        <button type="button" class="assistify-copy-btn" data-message-id="${messageId}" title="Copy message">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
            <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
          </svg>
        </button>
      `
          : "";

      const messageHtml = `
        <div class="assistify-message assistify-message-${role}${errorClass}" id="${messageId}" data-raw-content="${this.encodeHtmlEntities(
        content
      )}">
          <div class="assistify-message-content">${displayContent}</div>
          <div class="assistify-message-footer">
            <span class="assistify-message-time">${time}</span>
            ${copyButton}
          </div>
        </div>
      `;

      this.$messages.append(messageHtml);
      this.scrollToBottom();
    },

    /**
     * Encode HTML entities for data attribute storage.
     *
     * @param {string} text - Text to encode.
     * @return {string} Encoded text.
     */
    encodeHtmlEntities: function (text) {
      return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    },

    /**
     * Decode HTML entities from data attribute.
     *
     * @param {string} text - Text to decode.
     * @return {string} Decoded text.
     */
    decodeHtmlEntities: function (text) {
      const textarea = document.createElement("textarea");
      textarea.innerHTML = text;
      return textarea.value;
    },

    /**
     * Copy message content to clipboard.
     *
     * @param {string} messageId - The message element ID.
     */
    copyMessage: function (messageId) {
      const $message = $("#" + messageId);
      const rawContent = this.decodeHtmlEntities($message.data("raw-content"));
      const $copyBtn = $message.find(".assistify-copy-btn");

      navigator.clipboard
        .writeText(rawContent)
        .then(() => {
          // Show success feedback
          $copyBtn.addClass("is-copied");
          $copyBtn.html(`
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
          </svg>
        `);

          // Reset after 2 seconds
          setTimeout(() => {
            $copyBtn.removeClass("is-copied");
            $copyBtn.html(`
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
              <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
            </svg>
          `);
          }, 2000);
        })
        .catch(() => {
          // Fallback for older browsers
          const textarea = document.createElement("textarea");
          textarea.value = rawContent;
          document.body.appendChild(textarea);
          textarea.select();
          document.execCommand("copy");
          document.body.removeChild(textarea);

          $copyBtn.addClass("is-copied");
          setTimeout(() => $copyBtn.removeClass("is-copied"), 2000);
        });
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
     * Scroll messages to bottom with retry for reliable scrolling.
     *
     * @param {boolean} immediate - If true, scroll immediately without delay.
     */
    scrollToBottom: function (immediate) {
      const doScroll = () => {
        if (this.$messages && this.$messages[0]) {
          const container = this.$messages[0];
          // Force scroll to absolute bottom
          container.scrollTop = container.scrollHeight + 10000;

          // Also try scrollIntoView on last message for reliability
          const lastMessage = container.querySelector(
            ".assistify-message:last-child"
          );
          if (lastMessage) {
            lastMessage.scrollIntoView({ block: "end", behavior: "instant" });
          }
        }
      };

      if (immediate) {
        doScroll();
      } else {
        // Use requestAnimationFrame for smoother, more reliable scroll
        requestAnimationFrame(() => {
          doScroll();
          // Double-check scroll after a brief delay for content that renders async
          setTimeout(doScroll, 150);
        });
      }
    },

    /**
     * Switch between Chat and History tabs.
     *
     * @param {string} tab - 'chat' or 'history'
     */
    switchTab: function (tab) {
      this.currentTab = tab;

      // Update tab buttons
      this.$widget.find(".assistify-tab").removeClass("is-active");
      this.$widget
        .find('.assistify-tab[data-tab="' + tab + '"]')
        .addClass("is-active");

      // Update tab content
      this.$widget.find(".assistify-tab-content").removeClass("is-active");
      this.$widget.find(".assistify-tab-" + tab).addClass("is-active");

      // If switching to history, render sessions
      if (tab === "history") {
        this.renderSessions();
      }

      // Focus input if switching to chat
      if (tab === "chat") {
        this.$input.focus();
      }
    },

    /**
     * Render sessions list in History tab.
     */
    renderSessions: function () {
      if (!this.sessions || this.sessions.length === 0) {
        this.$sessionsList.html(
          '<div class="assistify-no-sessions">No previous chats yet. Start a conversation!</div>'
        );
        return;
      }

      let html = "";
      this.sessions.forEach((session) => {
        const isActive = session.id === this.sessionId;
        const activeClass = isActive ? " is-current" : "";
        const activeDot = isActive
          ? '<span class="assistify-session-active-dot"></span>'
          : "";
        const timeAgo = this.formatTimeAgo(session.last_activity);
        const preview = this.truncateText(session.preview || "New chat", 50);

        html += `
          <div class="assistify-session-item${activeClass}" data-session-id="${
          session.id
        }">
            <div class="assistify-session-preview">
              <span class="assistify-session-text">${MarkdownParser.escapeHtml(
                preview
              )}</span>
              <div class="assistify-session-actions">
                ${activeDot}
                <button type="button" class="assistify-session-delete" data-session-id="${
                  session.id
                }" title="Delete this chat">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                  </svg>
                </button>
              </div>
            </div>
            <div class="assistify-session-meta">
              <span class="assistify-session-time">${timeAgo}</span>
              <span class="assistify-session-count">${
                session.message_count || 0
              } messages</span>
            </div>
          </div>
        `;
      });

      // Add clear all button if there are sessions.
      if (this.sessions.length > 1) {
        html +=
          '<button type="button" class="assistify-clear-all-btn">Clear All History</button>';
      }

      this.$sessionsList.html(html);
    },

    /**
     * Load a specific session from history.
     *
     * @param {string} sessionId - The session ID to load.
     */
    loadSession: function (sessionId) {
      // Update current session
      this.sessionId = sessionId;
      localStorage.setItem("assistify_session_id", sessionId);

      // Clear current messages
      this.$messages.empty();

      // Show loading state
      this.$messages.html(
        '<div class="assistify-loading">Loading conversation...</div>'
      );

      // Fetch messages for this session
      $.ajax({
        url: assistifyAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "assistify_get_session_messages",
          nonce: assistifyAdmin.nonce,
          session_id: sessionId,
        },
        success: (response) => {
          this.$messages.empty();

          if (response.success && response.data.messages) {
            if (response.data.messages.length === 0) {
              // Empty session, show welcome
              this.addMessage("assistant", this.getWelcomeMessage());
            } else {
              // Render all messages
              response.data.messages.forEach((msg) => {
                this.addMessage(msg.role, msg.content, true, false);
              });
            }
          } else {
            this.addMessage("assistant", this.getWelcomeMessage());
          }

          // Switch to chat tab and scroll to bottom
          this.switchTab("chat");

          // Use multiple scroll attempts to handle async rendering
          this.scrollToBottom();
          setTimeout(() => this.scrollToBottom(), 200);
          setTimeout(() => this.scrollToBottom(), 500);
        },
        error: () => {
          this.$messages.empty();
          this.addMessage("assistant", this.getWelcomeMessage());
          this.switchTab("chat");
        },
      });
    },

    /**
     * Start a new chat session.
     * Note: Session is only saved to database when first message is sent.
     */
    startNewChat: function () {
      // Generate new session ID (not saved to DB yet)
      this.sessionId = this.generateSessionId();
      localStorage.setItem("assistify_session_id", this.sessionId);

      // Clear messages and show welcome
      this.$messages.empty();
      this.addMessage("assistant", this.getWelcomeMessage());

      // Switch to chat tab
      this.switchTab("chat");
    },

    /**
     * Delete a specific session.
     *
     * @param {string} sessionId - The session ID to delete.
     */
    deleteSession: function (sessionId) {
      this.showConfirmModal(
        "Delete Chat",
        "Are you sure you want to delete this chat? This action cannot be undone.",
        () => {
          $.ajax({
            url: assistifyAdmin.ajaxUrl,
            type: "POST",
            data: {
              action: "assistify_delete_session",
              nonce: assistifyAdmin.nonce,
              session_id: sessionId,
            },
            success: (response) => {
              if (response.success) {
                // If deleted session was current, start new chat.
                if (sessionId === this.sessionId) {
                  this.startNewChat();
                }
                // Reload sessions list.
                this.loadSessions();
              }
            },
          });
        }
      );
    },

    /**
     * Clear all chat sessions.
     */
    clearAllSessions: function () {
      this.showConfirmModal(
        "Clear All History",
        "Are you sure you want to delete all chat history? This action cannot be undone.",
        () => {
          $.ajax({
            url: assistifyAdmin.ajaxUrl,
            type: "POST",
            data: {
              action: "assistify_clear_all_sessions",
              nonce: assistifyAdmin.nonce,
            },
            success: (response) => {
              if (response.success) {
                // Start fresh.
                this.sessions = [];
                this.startNewChat();
                this.renderSessions();
              }
            },
          });
        }
      );
    },

    /**
     * Show a confirmation modal.
     *
     * @param {string} title - Modal title.
     * @param {string} message - Confirmation message.
     * @param {Function} onConfirm - Callback when confirmed.
     */
    showConfirmModal: function (title, message, onConfirm) {
      // Remove existing modal if any.
      $(".assistify-confirm-modal").remove();

      const modalHtml = `
        <div class="assistify-confirm-modal">
          <div class="assistify-confirm-modal-backdrop"></div>
          <div class="assistify-confirm-modal-content">
            <h4>${title}</h4>
            <p>${message}</p>
            <div class="assistify-confirm-modal-actions">
              <button type="button" class="assistify-confirm-cancel">Cancel</button>
              <button type="button" class="assistify-confirm-delete">Delete</button>
            </div>
          </div>
        </div>
      `;

      this.$container.append(modalHtml);

      const $modal = this.$container.find(".assistify-confirm-modal");

      // Handle cancel.
      $modal
        .find(".assistify-confirm-cancel, .assistify-confirm-modal-backdrop")
        .on("click", function () {
          $modal.remove();
        });

      // Handle confirm.
      $modal.find(".assistify-confirm-delete").on("click", function () {
        $modal.remove();
        if (typeof onConfirm === "function") {
          onConfirm();
        }
      });

      // Handle Escape key.
      $(document).one("keydown.confirmModal", function (e) {
        if (e.key === "Escape") {
          $modal.remove();
        }
      });
    },

    /**
     * Format timestamp to relative time (e.g., "2 hours ago").
     *
     * @param {string} timestamp - ISO timestamp or MySQL datetime.
     * @return {string} Formatted relative time.
     */
    formatTimeAgo: function (timestamp) {
      if (!timestamp) return "Unknown";

      const now = new Date();
      const then = new Date(timestamp);
      const diffMs = now - then;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);

      if (diffMins < 1) return "Just now";
      if (diffMins < 60) return diffMins + "m ago";
      if (diffHours < 24) return diffHours + "h ago";
      if (diffDays < 7) return diffDays + "d ago";

      return then.toLocaleDateString();
    },

    /**
     * Truncate text to specified length.
     *
     * @param {string} text - Text to truncate.
     * @param {number} maxLength - Maximum length.
     * @return {string} Truncated text.
     */
    truncateText: function (text, maxLength) {
      if (!text) return "";
      if (text.length <= maxLength) return text;
      return text.substring(0, maxLength) + "...";
    },
  };

  /**
   * Assistify Settings - Model Filter
   * Filters the Model dropdown based on selected AI Provider.
   */
  const AssistifyModelFilter = {
    /**
     * Initialize
     */
    init: function () {
      // Check if we have the required data
      if (
        typeof assistifyAdmin === "undefined" ||
        !assistifyAdmin.modelsByProvider
      ) {
        return;
      }

      this.$providerSelect = $("#assistify_ai_provider");
      this.$modelSelect = $("#assistify_ai_model");

      // Exit if elements don't exist (not on settings page)
      if (!this.$providerSelect.length || !this.$modelSelect.length) {
        return;
      }

      this.modelsByProvider = assistifyAdmin.modelsByProvider;
      this.defaultModels = assistifyAdmin.defaultModels || {};

      this.bindEvents();
      // Initial filter on page load
      this.updateModelOptions(this.$providerSelect.val());
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      const self = this;

      this.$providerSelect.on("change", function () {
        self.updateModelOptions($(this).val());
      });
    },

    /**
     * Update model dropdown options based on provider
     *
     * @param {string} provider - Selected provider ID
     */
    updateModelOptions: function (provider) {
      const currentModel = this.$modelSelect.val();
      const models = this.modelsByProvider[provider] || {};
      let hasCurrentModel = false;

      // Clear and rebuild options
      this.$modelSelect.empty();

      // Add options for the selected provider
      $.each(
        models,
        function (value, label) {
          this.$modelSelect.append(
            $("<option></option>").val(value).text(label)
          );
          if (value === currentModel) {
            hasCurrentModel = true;
          }
        }.bind(this)
      );

      // Select appropriate model
      if (hasCurrentModel) {
        this.$modelSelect.val(currentModel);
      } else if (this.defaultModels[provider]) {
        this.$modelSelect.val(this.defaultModels[provider]);
      }

      // Trigger change for Select2/selectWoo to update UI
      if ($.fn.selectWoo) {
        this.$modelSelect.trigger("change.select2");
      }
    },
  };

  /**
   * Assistify Settings - Image Model Filter
   * Filters the Image Model dropdown based on selected AI Provider.
   * Uses the main AI Provider setting since image generation shares the same API key.
   */
  const AssistifyImageModelFilter = {
    /**
     * Initialize
     */
    init: function () {
      // Check if we have the required data
      if (
        typeof assistifyAdmin === "undefined" ||
        !assistifyAdmin.imageModelsByProvider
      ) {
        return;
      }

      // Use main AI provider selector (not a separate image provider)
      this.$providerSelect = $("#assistify_ai_provider");
      this.$modelSelect = $("#assistify_image_model");

      // Exit if elements don't exist (not on settings page)
      if (!this.$providerSelect.length || !this.$modelSelect.length) {
        return;
      }

      this.modelsByProvider = assistifyAdmin.imageModelsByProvider;
      this.defaultModels = assistifyAdmin.defaultImageModels || {};

      this.bindEvents();
      // Initial filter on page load
      this.updateModelOptions(this.$providerSelect.val());
    },

    /**
     * Bind events
     */
    bindEvents: function () {
      const self = this;

      this.$providerSelect.on("change", function () {
        self.updateModelOptions($(this).val());
      });
    },

    /**
     * Update model dropdown options based on provider
     *
     * @param {string} provider - Selected provider ID
     */
    updateModelOptions: function (provider) {
      const currentModel = this.$modelSelect.val();
      const models = this.modelsByProvider[provider] || {};
      let hasCurrentModel = false;
      const hasModels = Object.keys(models).length > 0;

      // Clear and rebuild options
      this.$modelSelect.empty();

      if (!hasModels) {
        // Provider doesn't support image generation
        this.$modelSelect.append(
          $("<option></option>")
            .val("")
            .text("No image models available for this provider")
        );
        this.$modelSelect.prop("disabled", true);
      } else {
        this.$modelSelect.prop("disabled", false);

        // Add options for the selected provider
        $.each(
          models,
          function (value, label) {
            this.$modelSelect.append(
              $("<option></option>").val(value).text(label)
            );
            if (value === currentModel) {
              hasCurrentModel = true;
            }
          }.bind(this)
        );

        // Select appropriate model
        if (hasCurrentModel) {
          this.$modelSelect.val(currentModel);
        } else if (this.defaultModels[provider]) {
          this.$modelSelect.val(this.defaultModels[provider]);
        }
      }

      // Trigger change for Select2/selectWoo to update UI
      if ($.fn.selectWoo) {
        this.$modelSelect.trigger("change.select2");
      }
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    AssistifyAdminChat.init();
    AssistifyModelFilter.init();
    AssistifyImageModelFilter.init();
  });
})(jQuery);
