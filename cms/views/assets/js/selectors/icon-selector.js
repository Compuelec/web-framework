/*=============================================
Icon Selector - Reusable Component
Centralized list of Bootstrap Icons
===============================================*/

// List of available Bootstrap Icons
// To add more icons, simply add them to this array
// Use window.BOOTSTRAP_ICONS to avoid redeclaration errors when script is loaded multiple times
if (typeof window.BOOTSTRAP_ICONS === 'undefined') {
	window.BOOTSTRAP_ICONS = [
	'bi-house', 'bi-house-door', 'bi-building', 'bi-briefcase', 'bi-briefcase-fill',
	'bi-graph-up', 'bi-graph-down', 'bi-bar-chart', 'bi-pie-chart', 'bi-speedometer',
	'bi-people', 'bi-person', 'bi-person-circle', 'bi-person-square', 'bi-people-fill',
	'bi-envelope', 'bi-envelope-fill', 'bi-envelope-open', 'bi-chat', 'bi-chat-dots',
	'bi-gear', 'bi-gear-fill', 'bi-sliders', 'bi-tools', 'bi-wrench',
	'bi-folder', 'bi-folder-fill', 'bi-file-earmark', 'bi-file-earmark-text', 'bi-file-earmark-code',
	'bi-image', 'bi-image-fill', 'bi-camera', 'bi-camera-fill', 'bi-palette',
	'bi-heart', 'bi-heart-fill', 'bi-star', 'bi-star-fill', 'bi-bookmark',
	'bi-shield', 'bi-shield-fill', 'bi-lock', 'bi-lock-fill', 'bi-key',
	'bi-bell', 'bi-bell-fill', 'bi-megaphone', 'bi-bullhorn', 'bi-volume-up',
	'bi-calendar', 'bi-calendar-event', 'bi-clock', 'bi-clock-history', 'bi-stopwatch',
	'bi-search', 'bi-funnel', 'bi-filter', 'bi-sort-down', 'bi-sort-up',
	'bi-plus', 'bi-plus-circle', 'bi-dash', 'bi-x', 'bi-check',
	'bi-arrow-left', 'bi-arrow-right', 'bi-arrow-up', 'bi-arrow-down', 'bi-arrows-move',
	'bi-grid', 'bi-grid-3x3', 'bi-list', 'bi-list-ul', 'bi-menu-button',
	'bi-download', 'bi-upload', 'bi-share', 'bi-link', 'bi-link-45deg',
	'bi-printer', 'bi-save', 'bi-trash', 'bi-pencil', 'bi-pencil-square',
	'bi-eye', 'bi-eye-slash', 'bi-info-circle', 'bi-question-circle', 'bi-exclamation-circle',
	'bi-check-circle', 'bi-x-circle', 'bi-flag', 'bi-flag-fill', 'bi-bookmark-star',
	'bi-trophy', 'bi-award', 'bi-gift', 'bi-cart', 'bi-bag',
	'bi-credit-card', 'bi-wallet', 'bi-cash', 'bi-currency-dollar', 'bi-currency-euro',
	'bi-globe', 'bi-geo-alt', 'bi-map', 'bi-compass', 'bi-navigation',
	'bi-wifi', 'bi-bluetooth', 'bi-battery', 'bi-lightning', 'bi-lightning-fill',
	'bi-sun', 'bi-moon', 'bi-cloud', 'bi-cloud-rain', 'bi-cloud-sun',
	'bi-music-note', 'bi-play', 'bi-pause', 'bi-stop', 'bi-skip-forward',
	'bi-film', 'bi-camera-video', 'bi-mic', 'bi-mic-mute', 'bi-headphones',
	'bi-laptop', 'bi-phone', 'bi-tablet', 'bi-display', 'bi-tv',
	'bi-database', 'bi-server', 'bi-hdd', 'bi-usb', 'bi-usb-drive',
	'bi-box', 'bi-archive', 'bi-inbox', 'bi-outbox', 'bi-send',
	'bi-recycle', 'bi-trash2', 'bi-trash3', 'bi-x-octagon', 'bi-shield-exclamation',
	'bi-activity', 'bi-pulse', 'bi-heart-pulse', 'bi-thermometer', 'bi-droplet',
	'bi-flower1', 'bi-flower2', 'bi-tree', 'bi-bug', 'bi-bug-fill',
	'bi-robot', 'bi-cpu', 'bi-motherboard', 'bi-memory', 'bi-hdd-stack',
	// Additional icons - Business & Finance
	'bi-bank', 'bi-bank2', 'bi-cash-stack', 'bi-coin', 'bi-credit-card-2-front',
	'bi-credit-card-2-back', 'bi-receipt', 'bi-receipt-cutoff', 'bi-tag', 'bi-tags',
	'bi-tag-fill', 'bi-percent', 'bi-calculator', 'bi-graph-up-arrow', 'bi-graph-down-arrow',
	// Additional icons - Communication
	'bi-chat-left', 'bi-chat-right', 'bi-chat-left-text', 'bi-chat-right-text',
	'bi-chat-left-dots', 'bi-chat-right-dots', 'bi-chat-left-fill', 'bi-chat-right-fill',
	'bi-telephone', 'bi-telephone-fill', 'bi-telephone-forward', 'bi-telephone-outbound',
	'bi-mailbox', 'bi-mailbox2', 'bi-send-fill', 'bi-reply', 'bi-reply-all',
	// Additional icons - Files & Documents
	'bi-file', 'bi-file-text', 'bi-file-code', 'bi-file-image', 'bi-file-pdf',
	'bi-file-word', 'bi-file-excel', 'bi-file-zip', 'bi-file-play', 'bi-file-music',
	'bi-file-check', 'bi-file-x', 'bi-file-plus', 'bi-file-minus', 'bi-file-earmark-pdf',
	'bi-file-earmark-image', 'bi-file-earmark-zip', 'bi-folder2', 'bi-folder2-open',
	'bi-folder-symlink', 'bi-folder-symlink-fill',
	// Additional icons - UI & Interface
	'bi-app', 'bi-app-indicator', 'bi-grid-1x2', 'bi-grid-3x2', 'bi-grid-3x3-gap',
	'bi-layout-sidebar', 'bi-layout-sidebar-reverse', 'bi-layout-split', 'bi-layout-text-sidebar',
	'bi-layout-text-sidebar-reverse', 'bi-layout-text-window', 'bi-layout-three-columns',
	'bi-window', 'bi-window-dock', 'bi-window-sidebar', 'bi-bounding-box', 'bi-bounding-box-circles',
	// Additional icons - Navigation & Arrows
	'bi-arrow-left-right', 'bi-arrow-up-down', 'bi-arrow-up-left', 'bi-arrow-up-right',
	'bi-arrow-down-left', 'bi-arrow-down-right', 'bi-arrow-90deg-up', 'bi-arrow-90deg-down',
	'bi-arrow-90deg-left', 'bi-arrow-90deg-right', 'bi-arrow-repeat', 'bi-arrow-clockwise',
	'bi-arrow-counterclockwise', 'bi-arrow-return-left', 'bi-arrow-return-right',
	'bi-caret-up', 'bi-caret-down', 'bi-caret-left', 'bi-caret-right', 'bi-caret-up-fill',
	'bi-caret-down-fill', 'bi-caret-left-fill', 'bi-caret-right-fill',
	// Additional icons - Media & Entertainment
	'bi-play-circle', 'bi-pause-circle', 'bi-stop-circle', 'bi-skip-backward',
	'bi-skip-backward-fill', 'bi-skip-forward-fill', 'bi-shuffle', 'bi-repeat',
	'bi-volume-down', 'bi-volume-mute', 'bi-volume-off', 'bi-music-player',
	'bi-music-player-fill', 'bi-vinyl', 'bi-vinyl-fill', 'bi-disc', 'bi-easel',
	'bi-easel-fill', 'bi-paint-bucket', 'bi-brush', 'bi-brush-fill',
	// Additional icons - Social & Sharing
	'bi-share-fill', 'bi-share-box', 'bi-at', 'bi-hash', 'bi-facebook',
	'bi-twitter', 'bi-instagram', 'bi-linkedin', 'bi-youtube', 'bi-github',
	'bi-google', 'bi-microsoft', 'bi-apple', 'bi-android', 'bi-whatsapp',
	// Additional icons - Tools & Settings
	'bi-wrench-adjustable', 'bi-wrench-adjustable-circle', 'bi-nut', 'bi-nut-fill',
	'bi-screwdriver', 'bi-hammer', 'bi-clipboard', 'bi-clipboard-check', 'bi-clipboard-data',
	'bi-clipboard-x', 'bi-clipboard-plus', 'bi-scissors', 'bi-eraser', 'bi-eraser-fill',
	'bi-highlighter', 'bi-pen', 'bi-pen-fill', 'bi-pencil-fill', 'bi-pencil-square-fill',
	// Additional icons - Status & Feedback
	'bi-check-all', 'bi-check2', 'bi-check2-all', 'bi-check2-circle', 'bi-check2-square',
	'bi-x-lg', 'bi-x-square', 'bi-x-square-fill', 'bi-exclamation-triangle', 'bi-exclamation-triangle-fill',
	'bi-exclamation-octagon', 'bi-exclamation-octagon-fill', 'bi-exclamation-diamond',
	'bi-exclamation-diamond-fill', 'bi-question', 'bi-question-square', 'bi-question-octagon',
	// Additional icons - Time & Calendar
	'bi-calendar-check', 'bi-calendar-x', 'bi-calendar-plus', 'bi-calendar-minus',
	'bi-calendar-week', 'bi-calendar-month', 'bi-calendar-range', 'bi-calendar-day',
	'bi-clock-fill', 'bi-hourglass', 'bi-hourglass-split', 'bi-hourglass-top',
	'bi-hourglass-bottom', 'bi-alarm', 'bi-alarm-fill', 'bi-stopwatch-fill',
	// Additional icons - Weather & Nature
	'bi-cloud-fill', 'bi-cloud-drizzle', 'bi-cloud-drizzle-fill', 'bi-cloud-snow',
	'bi-cloud-snow-fill', 'bi-cloud-lightning', 'bi-cloud-lightning-fill', 'bi-cloud-lightning-rain',
	'bi-cloud-lightning-rain-fill', 'bi-cloud-hail', 'bi-cloud-hail-fill', 'bi-cloud-fog',
	'bi-cloud-fog-fill', 'bi-cloud-fog2', 'bi-cloud-fog2-fill', 'bi-sun-fill',
	'bi-moon-fill', 'bi-stars', 'bi-rainbow', 'bi-umbrella', 'bi-umbrella-fill',
	// Additional icons - Transportation
	'bi-car-front', 'bi-car-front-fill', 'bi-truck', 'bi-bus-front', 'bi-bus-front-fill',
	'bi-train-front', 'bi-train-front-fill', 'bi-airplane', 'bi-airplane-engines',
	'bi-airplane-engines-fill', 'bi-bicycle', 'bi-scooter', 'bi-ship', 'bi-fuel-pump',
	'bi-fuel-pump-diesel', 'bi-fuel-pump-diesel-fill', 'bi-fuel-pump-fill',
	// Additional icons - Shopping & E-commerce
	'bi-cart-fill', 'bi-cart-check', 'bi-cart-check-fill', 'bi-cart-dash', 'bi-cart-dash-fill',
	'bi-cart-plus', 'bi-cart-plus-fill', 'bi-cart-x', 'bi-cart-x-fill', 'bi-bag-fill',
	'bi-bag-check', 'bi-bag-check-fill', 'bi-bag-dash', 'bi-bag-dash-fill', 'bi-bag-plus',
	'bi-bag-plus-fill', 'bi-bag-x', 'bi-bag-x-fill', 'bi-basket', 'bi-basket-fill',
	'bi-basket2', 'bi-basket2-fill', 'bi-basket3', 'bi-basket3-fill',
	// Additional icons - Health & Medical
	'bi-heart-pulse-fill', 'bi-hospital', 'bi-hospital-fill', 'bi-capsule', 'bi-capsule-pill',
	'bi-prescription', 'bi-prescription2', 'bi-thermometer-half', 'bi-thermometer-high',
	'bi-thermometer-low', 'bi-thermometer-sun', 'bi-thermometer-snow', 'bi-bandaid',
	'bi-bandaid-fill', 'bi-capsule-pill', 'bi-file-medical', 'bi-file-medical-fill',
	// Additional icons - Education & Learning
	'bi-book', 'bi-book-fill', 'bi-journal', 'bi-journal-text', 'bi-journal-code',
	'bi-journal-bookmark', 'bi-journal-bookmark-fill', 'bi-journal-check', 'bi-journal-richtext',
	'bi-journal-text', 'bi-mortarboard', 'bi-mortarboard-fill', 'bi-backpack', 'bi-backpack-fill',
	'bi-pencil-square', 'bi-pencil-square-fill', 'bi-pen-fill', 'bi-pen-fill',
	// Additional icons - Technology & Devices
	'bi-router', 'bi-router-fill', 'bi-modem', 'bi-modem-fill', 'bi-hdd-network',
	'bi-hdd-rack', 'bi-hdd-stack-fill', 'bi-device-ssd', 'bi-device-ssd-fill',
	'bi-device-hdd', 'bi-device-hdd-fill', 'bi-mouse', 'bi-mouse-fill', 'bi-mouse2',
	'bi-mouse2-fill', 'bi-mouse3', 'bi-mouse3-fill', 'bi-keyboard', 'bi-keyboard-fill',
	'bi-webcam', 'bi-webcam-fill', 'bi-printer-fill', 'bi-scanner', 'bi-projector',
	'bi-projector-fill', 'bi-smartwatch', 'bi-watch', 'bi-watch-fill',
	// Additional icons - Miscellaneous
	'bi-lightbulb', 'bi-lightbulb-fill', 'bi-lightbulb-off', 'bi-lightbulb-off-fill',
	'bi-fan', 'bi-fire', 'bi-water', 'bi-droplet-fill', 'bi-droplet-half',
	'bi-snow', 'bi-snow2', 'bi-snow3', 'bi-wind', 'bi-tornado', 'bi-hurricane',
	'bi-signpost', 'bi-signpost-2', 'bi-signpost-2-fill', 'bi-signpost-split',
	'bi-signpost-split-fill', 'bi-postage', 'bi-postage-fill', 'bi-postage-heart',
	'bi-postage-heart-fill', 'bi-stamp', 'bi-stamp-fill'
	];
}

// All references to BOOTSTRAP_ICONS should use window.BOOTSTRAP_ICONS directly
// to avoid redeclaration errors when the script is loaded multiple times

/**
 * Initialize the icon selector
 * @param {Object} options - Configuration options
 * @param {string} options.inputId - ID of the input where the selected icon will be saved
 * @param {string} options.previewId - Optional ID of the element to show icon preview
 * @param {string} options.modalId - Modal ID (default: 'iconSelectorModal')
 * @param {string} options.gridId - Grid container ID (default: 'iconGrid')
 * @param {string} options.searchId - Search input ID (default: 'iconSearch')
 */
function initIconSelector(options = {}) {
	const {
		inputId,
		previewId = null,
		modalId = 'iconSelectorModal',
		gridId = 'iconGrid',
		searchId = 'iconSearch'
	} = options;

	if (!inputId) {
		console.error('IconSelector: inputId parameter is required');
		return;
	}

	const modalElement = document.getElementById(modalId);
	if (!modalElement) {
		console.error(`IconSelector: Modal with ID "${modalId}" not found`);
		return;
	}

	// Load icons when modal opens
	modalElement.addEventListener('show.bs.modal', function() {
		loadIcons(gridId, inputId, previewId);
		// Clear search
		const searchInput = document.getElementById(searchId);
		if (searchInput) searchInput.value = '';
	});

	// Icon search
	const searchInput = document.getElementById(searchId);
	if (searchInput) {
		searchInput.addEventListener('input', function(e) {
			const searchTerm = e.target.value.toLowerCase();
			const iconItems = document.querySelectorAll(`#${gridId} .icon-item`);
			iconItems.forEach(item => {
				const iconText = item.textContent.toLowerCase();
				if (iconText.includes(searchTerm)) {
					item.style.display = 'block';
				} else {
					item.style.display = 'none';
				}
			});
		});
	}

	// Allow clicking input to open modal
	const inputElement = document.getElementById(inputId);
	if (inputElement) {
		inputElement.addEventListener('click', function() {
			const modal = new bootstrap.Modal(modalElement);
			modal.show();
		});
	}
}

/**
 * Load icons into the grid
 * @param {string} gridId - Grid container ID
 * @param {string} inputId - Input ID where to save the value
 * @param {string|null} previewId - Optional preview element ID
 */
function loadIcons(gridId, inputId, previewId = null) {
	const iconGrid = document.getElementById(gridId);
	if (!iconGrid) {
		console.error(`IconSelector: Grid with ID "${gridId}" not found`);
		return;
	}

	iconGrid.innerHTML = '';

	// Use window.BOOTSTRAP_ICONS directly to avoid scope issues
	var icons = window.BOOTSTRAP_ICONS || [];
	icons.forEach(iconClass => {
		const iconItem = document.createElement('div');
		iconItem.className = 'icon-item';
		iconItem.innerHTML = `
			<i class="bi ${iconClass}"></i>
			<span>${iconClass.replace('bi-', '')}</span>
		`;
		iconItem.addEventListener('click', () => {
			// Remove previous selection
			document.querySelectorAll(`#${gridId} .icon-item`).forEach(item => {
				item.classList.remove('selected');
			});
			// Add selection
			iconItem.classList.add('selected');
			
			// Update input with icon name
			const iconInput = document.getElementById(inputId);
			if (iconInput) {
				// Update preview immediately if exists
				if (previewId) {
					const previewElement = document.getElementById(previewId);
					if (previewElement) {
						previewElement.className = `bi ${iconClass}`;
					}
				}
				
				// Set the value using jQuery to ensure it works with all listeners
				if (typeof jQuery !== 'undefined') {
					// Force update of the input value property first
					iconInput.value = iconClass;
					
					// Then set value using jQuery
					jQuery(iconInput).val(iconClass);
					
					// Mark field as valid
					jQuery(iconInput).removeClass('is-invalid').addClass('is-valid');
					
					// Verify value was set
					console.log('Icon selected:', iconClass, 'Input value:', jQuery(iconInput).val(), 'Native value:', iconInput.value);
					
					// Wait a bit longer to ensure value is fully set before triggering change
					setTimeout(() => {
						// Force set the value one more time to ensure it's correct
						iconInput.value = iconClass;
						jQuery(iconInput).val(iconClass);
						
						// Verify value is correct
						var currentValue = jQuery(iconInput).val();
						console.log('Before triggering change - Icon:', iconClass, 'Current value:', currentValue, 'Native value:', iconInput.value);
						
						if (currentValue !== iconClass) {
							// Value was changed, force set it again
							iconInput.value = iconClass;
							jQuery(iconInput).val(iconClass);
							console.log('Value was incorrect, forced reset to:', iconClass);
						}
						
						// Use a custom event to pass the icon value directly
						var customEvent = jQuery.Event('change', { iconValue: iconClass });
						jQuery(iconInput).trigger(customEvent);
						
						// Also trigger regular change event
						jQuery(iconInput).trigger('change');
						
						console.log('Change event triggered for icon:', iconClass, 'Final value:', jQuery(iconInput).val());
					}, 250);
				} else {
					// Fallback if jQuery is not available
					iconInput.value = iconClass;
					iconInput.classList.remove('is-invalid');
					iconInput.classList.add('is-valid');
					
					setTimeout(() => {
						const changeEvent = new Event('change', { bubbles: true });
						iconInput.dispatchEvent(changeEvent);
					}, 50);
				}
			}

			// Close modal after a brief delay to ensure value is set
			setTimeout(() => {
				const modalElement = iconGrid.closest('.modal');
				if (modalElement) {
					const modal = bootstrap.Modal.getInstance(modalElement);
					if (modal) {
						modal.hide();
					}
				}
			}, 200);
		});
		iconGrid.appendChild(iconItem);
	});
}

