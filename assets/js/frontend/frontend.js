/**
 * Assistify for WooCommerce - Frontend JavaScript
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

/* global jQuery, assistifyFrontend */

(function ($) {
	'use strict';

	/**
	 * Assistify Frontend Chat
	 */
	const AssistifyChat = {
		/**
		 * Whether user has consented to chat
		 */
		hasConsented: false,

		/**
		 * Initialize
		 */
		init: function () {
			this.$widget = $('#assistify-chat-widget');
			this.$toggle = $('#assistify-chat-toggle');
			this.$container = $('#assistify-chat-container');
			this.$messages = this.$widget.find('.assistify-chat-messages');
			this.$form = $('#assistify-chat-form');
			this.$input = $('#assistify-chat-input');
			this.$minimize = this.$widget.find('.assistify-chat-minimize');

			// Set CSS custom property for primary color
			document.documentElement.style.setProperty(
				'--assistify-primary-color',
				assistifyFrontend.settings.primaryColor || '#7f54b3'
			);

			// Check for consent
			this.hasConsented = this.getConsent();

			this.bindEvents();
		},

		/**
		 * Bind events
		 */
		bindEvents: function () {
			const self = this;

			// Toggle chat
			this.$toggle.on('click', function () {
				self.toggleChat();
			});

			// Minimize chat
			this.$minimize.on('click', function () {
				self.closeChat();
			});

			// Submit message
			this.$form.on('submit', function (e) {
				e.preventDefault();
				self.sendMessage();
			});

			// Keyboard events
			$(document).on('keydown', function (e) {
				if (e.key === 'Escape' && !self.$container.prop('hidden')) {
					self.closeChat();
				}
			});
		},

		/**
		 * Toggle chat open/closed
		 */
		toggleChat: function () {
			const isHidden = this.$container.prop('hidden');

			if (isHidden) {
				this.openChat();
			} else {
				this.closeChat();
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

			this.$container.prop('hidden', false);
			this.$toggle.attr('aria-expanded', 'true');
			this.$input.focus();

			// Add welcome message if no messages
			if (this.$messages.children().length === 0) {
				this.addMessage('assistant', assistifyFrontend.strings.welcome);
			}
		},

		/**
		 * Close chat
		 */
		closeChat: function () {
			this.$container.prop('hidden', true);
			this.$toggle.attr('aria-expanded', 'false');
		},

		/**
		 * Show consent dialog
		 */
		showConsent: function () {
			this.$container.prop('hidden', false);
			this.$toggle.attr('aria-expanded', 'true');

			const consentHtml = `
				<div class="assistify-consent-modal">
					<h4>${assistifyFrontend.strings.consentTitle}</h4>
					<p>${assistifyFrontend.strings.consentText}</p>
					<div class="assistify-consent-buttons">
						<button type="button" class="assistify-consent-agree">${assistifyFrontend.strings.consentAgree}</button>
						<button type="button" class="assistify-consent-decline">${assistifyFrontend.strings.consentDecline}</button>
					</div>
				</div>
			`;

			this.$messages.html(consentHtml);

			const self = this;

			// Agree button
			this.$messages.find('.assistify-consent-agree').on('click', function () {
				self.setConsent(true);
				self.hasConsented = true;
				self.$messages.empty();
				self.addMessage('assistant', assistifyFrontend.strings.welcome);
				self.$input.focus();
			});

			// Decline button
			this.$messages.find('.assistify-consent-decline').on('click', function () {
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
				return localStorage.getItem('assistify_consent') === 'true';
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
				localStorage.setItem('assistify_consent', value ? 'true' : 'false');
			} catch (e) {
				// Silent fail for private browsing
			}
		},

		/**
		 * Send message
		 */
		sendMessage: function () {
			const message = this.$input.val().trim();

			if (!message) {
				return;
			}

			// Add user message
			this.addMessage('user', message);
			this.$input.val('');

			// Show typing indicator
			this.showTypingIndicator();

			// Send to server
			$.ajax({
				url: assistifyFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'assistify_customer_chat',
					nonce: assistifyFrontend.nonce,
					message: message,
				},
				success: (response) => {
					this.hideTypingIndicator();

					if (response.success) {
						this.addMessage('assistant', response.data.message);
					} else {
						this.addMessage('assistant', response.data.message || assistifyFrontend.strings.error);
					}
				},
				error: () => {
					this.hideTypingIndicator();
					this.addMessage('assistant', assistifyFrontend.strings.error);
				},
			});
		},

		/**
		 * Add message to chat
		 *
		 * @param {string} role - 'user' or 'assistant'
		 * @param {string} content - Message content
		 */
		addMessage: function (role, content) {
			const messageHtml = `
				<div class="assistify-message assistify-message-${role}">
					<div class="assistify-message-content">${this.escapeHtml(content)}</div>
				</div>
			`;

			this.$messages.append(messageHtml);
			this.scrollToBottom();
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
			this.$messages.find('.assistify-typing').remove();
		},

		/**
		 * Scroll messages to bottom
		 */
		scrollToBottom: function () {
			this.$messages.scrollTop(this.$messages[0].scrollHeight);
		},

		/**
		 * Escape HTML to prevent XSS
		 *
		 * @param {string} text - Text to escape
		 * @return {string} Escaped text
		 */
		escapeHtml: function (text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		},
	};

	// Initialize on document ready
	$(document).ready(function () {
		AssistifyChat.init();
	});
})(jQuery);

