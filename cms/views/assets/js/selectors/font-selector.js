/*=============================================
Font Selector - Reusable Component
Centralized list of Google Fonts
===============================================*/

// List of available Google Fonts
// To add more fonts, simply add them to this array
const GOOGLE_FONTS = [
	{ name: 'Roboto', family: 'Roboto', category: 'Sans Serif' },
	{ name: 'Open Sans', family: 'Open Sans', category: 'Sans Serif' },
	{ name: 'Lato', family: 'Lato', category: 'Sans Serif' },
	{ name: 'Montserrat', family: 'Montserrat', category: 'Sans Serif' },
	{ name: 'Poppins', family: 'Poppins', category: 'Sans Serif' },
	{ name: 'Raleway', family: 'Raleway', category: 'Sans Serif' },
	{ name: 'Ubuntu', family: 'Ubuntu', category: 'Sans Serif' },
	{ name: 'Nunito', family: 'Nunito', category: 'Sans Serif' },
	{ name: 'Source Sans Pro', family: 'Source Sans Pro', category: 'Sans Serif' },
	{ name: 'Inter', family: 'Inter', category: 'Sans Serif' },
	{ name: 'Playfair Display', family: 'Playfair Display', category: 'Serif' },
	{ name: 'Merriweather', family: 'Merriweather', category: 'Serif' },
	{ name: 'Lora', family: 'Lora', category: 'Serif' },
	{ name: 'PT Serif', family: 'PT Serif', category: 'Serif' },
	{ name: 'Crimson Text', family: 'Crimson Text', category: 'Serif' },
	{ name: 'Roboto Slab', family: 'Roboto Slab', category: 'Serif' },
	{ name: 'Dancing Script', family: 'Dancing Script', category: 'Handwriting' },
	{ name: 'Pacifico', family: 'Pacifico', category: 'Handwriting' },
	{ name: 'Caveat', family: 'Caveat', category: 'Handwriting' },
	{ name: 'Kalam', family: 'Kalam', category: 'Handwriting' },
	{ name: 'Permanent Marker', family: 'Permanent Marker', category: 'Handwriting' },
	{ name: 'Oswald', family: 'Oswald', category: 'Display' },
	{ name: 'Bebas Neue', family: 'Bebas Neue', category: 'Display' },
	{ name: 'Righteous', family: 'Righteous', category: 'Display' },
	{ name: 'Bangers', family: 'Bangers', category: 'Display' },
	{ name: 'Anton', family: 'Anton', category: 'Display' }
];

/**
 * Initialize the font selector
 * @param {Object} options - Configuration options
 * @param {string} options.inputId - ID of the input/textarea where the selected font will be saved
 * @param {string} options.previewId - Optional preview container ID
 * @param {string} options.previewTextId - Optional preview text ID
 * @param {string} options.modalId - Modal ID (default: 'fontSelectorModal')
 * @param {string} options.listId - List container ID (default: 'fontList')
 * @param {string} options.searchId - Search input ID (default: 'fontSearch')
 */
function initFontSelector(options = {}) {
	const {
		inputId,
		previewId = null,
		previewTextId = null,
		modalId = 'fontSelectorModal',
		listId = 'fontList',
		searchId = 'fontSearch'
	} = options;

	if (!inputId) {
		console.error('FontSelector: inputId parameter is required');
		return;
	}

	const modalElement = document.getElementById(modalId);
	if (!modalElement) {
		console.error(`FontSelector: Modal with ID "${modalId}" not found`);
		return;
	}

	// Load fonts when modal opens
	modalElement.addEventListener('show.bs.modal', function() {
		loadFonts(listId, inputId, previewId, previewTextId);
		// Clear search
		const searchInput = document.getElementById(searchId);
		if (searchInput) searchInput.value = '';
	});

	// Font search
	const searchInput = document.getElementById(searchId);
	if (searchInput) {
		searchInput.addEventListener('input', function(e) {
			const searchTerm = e.target.value.toLowerCase();
			const fontItems = document.querySelectorAll(`#${listId} .font-item`);
			fontItems.forEach(item => {
				const fontText = item.textContent.toLowerCase();
				if (fontText.includes(searchTerm)) {
					item.style.display = 'block';
				} else {
					item.style.display = 'none';
				}
			});
		});
	}

	// Allow clicking input/textarea to open modal
	const inputElement = document.getElementById(inputId);
	if (inputElement) {
		inputElement.addEventListener('click', function() {
			const modal = new bootstrap.Modal(modalElement);
			modal.show();
		});
	}
}

/**
 * Load fonts into the list
 * @param {string} listId - List container ID
 * @param {string} inputId - Input/textarea ID where to save the value
 * @param {string|null} previewId - Optional preview container ID
 * @param {string|null} previewTextId - Optional preview text ID
 */
function loadFonts(listId, inputId, previewId = null, previewTextId = null) {
	const fontList = document.getElementById(listId);
	if (!fontList) {
		console.error(`FontSelector: List with ID "${listId}" not found`);
		return;
	}

	fontList.innerHTML = '';

	GOOGLE_FONTS.forEach(font => {
		const fontItem = document.createElement('div');
		fontItem.className = 'font-item';
		fontItem.style.fontFamily = `"${font.family}", sans-serif`;
		fontItem.innerHTML = `
			<div class="font-item-name">${font.name}</div>
			<div class="font-item-preview">The quick brown fox jumps over the lazy dog</div>
			<small class="text-muted">${font.category}</small>
		`;
		fontItem.addEventListener('click', () => {
			// Remove previous selection
			document.querySelectorAll(`#${listId} .font-item`).forEach(item => {
				item.classList.remove('selected');
			});
			// Add selection
			fontItem.classList.add('selected');

			// Update input/textarea with Google Fonts code
			const fontInput = document.getElementById(inputId);
			if (fontInput) {
				const fontUrl = `@import url('https://fonts.googleapis.com/css2?family=${font.family.replace(/\s+/g, '+')}:wght@300;400;500;600;700&display=swap');`;
				const fontCss = `font-family: '${font.family}', sans-serif;`;
				fontInput.value = `${fontUrl}\n\n${fontCss}`;

				// Mark field as valid
				fontInput.classList.remove('is-invalid');
				fontInput.classList.add('is-valid');
			}

			// Update preview if exists
			if (previewId && previewTextId) {
				const preview = document.getElementById(previewId);
				const previewText = document.getElementById(previewTextId);
				if (preview && previewText) {
					preview.style.display = 'block';
					preview.classList.add('show');
					previewText.style.fontFamily = `"${font.family}", sans-serif`;
				}
			}

			// Load font dynamically
			const link = document.createElement('link');
			link.href = `https://fonts.googleapis.com/css2?family=${font.family.replace(/\s+/g, '+')}:wght@300;400;500;600;700&display=swap`;
			link.rel = 'stylesheet';
			document.head.appendChild(link);

			// Close modal after a brief delay
			setTimeout(() => {
				const modalElement = fontList.closest('.modal');
				if (modalElement) {
					const modal = bootstrap.Modal.getInstance(modalElement);
					if (modal) modal.hide();
				}
			}, 300);
		});
		fontList.appendChild(fontItem);
	});
}

