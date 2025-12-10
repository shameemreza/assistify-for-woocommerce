/**
 * Assistify for WooCommerce - Admin JavaScript
 *
 * @package Assistify_For_WooCommerce
 * @since   1.0.0
 */

/* global jQuery, assistifyAdmin */

(function ($) {
	'use strict';

	/**
	 * Assistify Admin Chat
	 */
	const AssistifyAdminChat = {
		/**
		 * Initialize
		 */
		init: function () {
			// Check if assistifyAdmin exists and chat is enabled
			if (typeof assistifyAdmin === 'undefined' || !assistifyAdmin.settings || assistifyAdmin.settings.chatEnabled !== 'yes') {
				return;
			}

			this.createWidget();
			this.bindEvents();
		},

		/**
		 * Create the chat widget
		 */
		createWidget: function () {
			const widgetHtml = `
				<div class="assistify-admin-chat">
					<button type="button" class="assistify-admin-chat-toggle" aria-expanded="false" aria-label="Open Assistify Chat">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
							<path d="M12 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.36 5.07L2 22l4.93-1.36C8.42 21.5 10.15 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.57 0-3.05-.43-4.32-1.18l-.31-.18-3.22.89.89-3.22-.18-.31C4.43 15.05 4 13.57 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8-3.59 8-8 8z"/>
						</svg>
					</button>
					<div class="assistify-admin-chat-container">
						<div class="assistify-admin-chat-header">
							<h3>Assistify</h3>
							<button type="button" class="assistify-admin-chat-close" aria-label="Close chat">×</button>
						</div>
						<div class="assistify-admin-chat-messages" role="log" aria-live="polite"></div>
						<form class="assistify-admin-chat-form">
							<input type="text" class="assistify-admin-chat-input" placeholder="Ask anything about your store..." autocomplete="off">
							<button type="submit" class="assistify-admin-chat-send">Send</button>
						</form>
					</div>
				</div>
			`;

			$('body').append(widgetHtml);

			this.$widget = $('.assistify-admin-chat');
			this.$toggle = this.$widget.find('.assistify-admin-chat-toggle');
			this.$container = this.$widget.find('.assistify-admin-chat-container');
			this.$messages = this.$widget.find('.assistify-admin-chat-messages');
			this.$form = this.$widget.find('.assistify-admin-chat-form');
			this.$input = this.$widget.find('.assistify-admin-chat-input');
			this.$close = this.$widget.find('.assistify-admin-chat-close');

			// Add welcome message
			this.addMessage('assistant', 'Hi! I\'m your Assistify assistant. How can I help you manage your store today?');
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

			// Close chat
			this.$close.on('click', function () {
				self.closeChat();
			});

			// Submit message
			this.$form.on('submit', function (e) {
				e.preventDefault();
				self.sendMessage();
			});

			// Keyboard shortcut (Ctrl + /)
			$(document).on('keydown', function (e) {
				if (e.ctrlKey && e.key === '/') {
					e.preventDefault();
					self.toggleChat();
				}
				if (e.key === 'Escape' && self.$container.hasClass('is-open')) {
					self.closeChat();
				}
			});
		},

		/**
		 * Toggle chat open/closed
		 */
		toggleChat: function () {
			const isOpen = this.$container.hasClass('is-open');

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
			this.$container.addClass('is-open');
			this.$toggle.attr('aria-expanded', 'true');
			this.$input.focus();
		},

		/**
		 * Close chat
		 */
		closeChat: function () {
			this.$container.removeClass('is-open');
			this.$toggle.attr('aria-expanded', 'false');
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
				url: assistifyAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'assistify_admin_chat',
					nonce: assistifyAdmin.nonce,
					message: message,
				},
				success: (response) => {
					this.hideTypingIndicator();

					if (response.success) {
						this.addMessage('assistant', response.data.message);
					} else {
						this.addMessage('assistant', response.data.message || assistifyAdmin.strings.error);
					}
				},
				error: () => {
					this.hideTypingIndicator();
					this.addMessage('assistant', assistifyAdmin.strings.error);
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
			if (typeof assistifyAdmin === 'undefined' || !assistifyAdmin.modelsByProvider) {
				return;
			}

			this.$providerSelect = $('#assistify_ai_provider');
			this.$modelSelect = $('#assistify_ai_model');

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

			this.$providerSelect.on('change', function () {
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
			$.each(models, function (value, label) {
				this.$modelSelect.append(
					$('<option></option>').val(value).text(label)
				);
				if (value === currentModel) {
					hasCurrentModel = true;
				}
			}.bind(this));

			// Select appropriate model
			if (hasCurrentModel) {
				this.$modelSelect.val(currentModel);
			} else if (this.defaultModels[provider]) {
				this.$modelSelect.val(this.defaultModels[provider]);
			}

			// Trigger change for Select2/selectWoo to update UI
			if ($.fn.selectWoo) {
				this.$modelSelect.trigger('change.select2');
			}
		},
	};

	// Initialize on document ready
	$(document).ready(function () {
		AssistifyAdminChat.init();
		AssistifyModelFilter.init();
	});
})(jQuery);

