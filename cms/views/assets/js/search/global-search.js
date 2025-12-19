/*=============================================
Global Search for CMS
=============================================*/

var GlobalSearch = {
    isOpen: false,
    searchTerm: '',
    results: [],
    currentIndex: -1,
    
    init: function() {
        this.createSearchModal();
        this.bindKeyboardShortcuts();
        this.bindSearchEvents();
    },
    
    createSearchModal: function() {
        var modalHTML = `
            <div class="modal fade" id="globalSearchModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-body p-0">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white border-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" 
                                       id="globalSearchInput" 
                                       class="form-control border-0" 
                                       placeholder="Search pages, modules, data... (Press ESC to close)"
                                       autocomplete="off">
                                <span class="input-group-text bg-white border-0">
                                    <kbd class="bg-light">Ctrl+K</kbd>
                                </span>
                            </div>
                            <div id="globalSearchResults" class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                                <div class="text-center text-muted p-4">
                                    <i class="bi bi-search" style="font-size: 2rem;"></i>
                                    <p class="mt-2 mb-0">Type to search...</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-0">
                            <small class="text-muted">
                                <kbd>↑↓</kbd> Navigate | <kbd>Enter</kbd> Select | <kbd>ESC</kbd> Close
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHTML);
    },
    
    bindKeyboardShortcuts: function() {
        var self = this;
        
        // Ctrl+K or Cmd+K to open search
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                self.open();
            }
            
            // ESC to close
            if (e.key === 'Escape' && self.isOpen) {
                self.close();
            }
        });
        
        // Arrow key navigation
        $(document).on('keydown', '#globalSearchInput', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                self.navigateResults(1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                self.navigateResults(-1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                self.selectResult();
            }
        });
    },
    
    bindSearchEvents: function() {
        var self = this;
        
        // Real-time search
        $(document).on('input', '#globalSearchInput', function() {
            var term = $(this).val().trim();
            if (term.length >= 2) {
                self.search(term);
            } else {
                self.showEmptyState();
            }
        });
        
        // Click on result
        $(document).on('click', '.global-search-result', function() {
            var url = $(this).data('url');
            if (url) {
                window.location.href = url;
            }
        });
    },
    
    open: function() {
        this.isOpen = true;
        $('#globalSearchModal').modal('show');
        $('#globalSearchInput').focus();
        this.currentIndex = -1;
    },
    
    close: function() {
        this.isOpen = false;
        $('#globalSearchModal').modal('hide');
        $('#globalSearchInput').val('');
        this.results = [];
        this.currentIndex = -1;
    },
    
    search: function(term) {
        var self = this;
        this.searchTerm = term;
        
        // Show loading
        this.showLoading();
        
        // Search in pages, modules and data
        $.ajax({
            url: CMS_AJAX_PATH + '/global-search.ajax.php',
            method: 'POST',
            data: {
                term: term,
                token: localStorage.getItem('tokenAdmin') || ''
            },
            success: function(response) {
                try {
                    var data = typeof response === 'string' ? JSON.parse(response) : response;
                    self.results = data.results || [];
                    self.displayResults();
                } catch (e) {
                    console.error('Error parsing search results:', e);
                    self.showError();
                }
            },
            error: function() {
                self.showError();
            }
        });
    },
    
    displayResults: function() {
        var $container = $('#globalSearchResults');
        $container.empty();
        
        if (this.results.length === 0) {
            $container.html(`
                <div class="text-center text-muted p-4">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2 mb-0">No results found for "${this.searchTerm}"</p>
                </div>
            `);
            return;
        }
        
        // Group results by type
        var grouped = this.groupResultsByType(this.results);
        var self = this;
        
        Object.keys(grouped).forEach(function(type) {
            var typeLabel = self.getTypeLabel(type);
            var $group = $('<div class="list-group-item bg-light"><small class="text-muted text-uppercase fw-bold">' + typeLabel + '</small></div>');
            $container.append($group);
            
            grouped[type].forEach(function(result) {
                var $item = $('<a href="#" class="list-group-item list-group-item-action global-search-result" data-url="' + result.url + '">' +
                    '<div class="d-flex justify-content-between align-items-center">' +
                    '<div>' +
                    '<h6 class="mb-1">' + result.title + '</h6>' +
                    (result.description ? '<small class="text-muted">' + result.description + '</small>' : '') +
                    '</div>' +
                    '<i class="bi bi-chevron-right text-muted"></i>' +
                    '</div>' +
                    '</a>');
                $container.append($item);
            });
        });
        
        // Resaltar término de búsqueda
        this.highlightSearchTerm();
    },
    
    groupResultsByType: function(results) {
        var grouped = {};
        results.forEach(function(result) {
            if (!grouped[result.type]) {
                grouped[result.type] = [];
            }
            grouped[result.type].push(result);
        });
        return grouped;
    },
    
    getTypeLabel: function(type) {
        var labels = {
            'page': 'Pages',
            'module': 'Modules',
            'data': 'Data',
            'file': 'Files'
        };
        return labels[type] || type;
    },
    
    highlightSearchTerm: function() {
        var term = this.searchTerm.toLowerCase();
        $('#globalSearchResults .global-search-result h6').each(function() {
            var text = $(this).text();
            var regex = new RegExp('(' + term + ')', 'gi');
            var highlighted = text.replace(regex, '<mark>$1</mark>');
            $(this).html(highlighted);
        });
    },
    
    navigateResults: function(direction) {
        var $results = $('#globalSearchResults .global-search-result');
        if ($results.length === 0) return;
        
        this.currentIndex += direction;
        
        if (this.currentIndex < 0) {
            this.currentIndex = $results.length - 1;
        } else if (this.currentIndex >= $results.length) {
            this.currentIndex = 0;
        }
        
        $results.removeClass('active');
        $results.eq(this.currentIndex).addClass('active').get(0).scrollIntoView({ block: 'nearest' });
    },
    
    selectResult: function() {
        var $active = $('#globalSearchResults .global-search-result.active');
        if ($active.length > 0) {
            var url = $active.data('url');
            if (url) {
                window.location.href = url;
            }
        } else {
            // If no selection, go to first result
            var $first = $('#globalSearchResults .global-search-result').first();
            if ($first.length > 0) {
                var url = $first.data('url');
                if (url) {
                    window.location.href = url;
                }
            }
        }
    },
    
    showLoading: function() {
        $('#globalSearchResults').html(`
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Searching...</span>
                </div>
                <p class="mt-2 text-muted">Searching...</p>
            </div>
        `);
    },
    
    showEmptyState: function() {
        $('#globalSearchResults').html(`
            <div class="text-center text-muted p-4">
                <i class="bi bi-search" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0">Type to search...</p>
            </div>
        `);
    },
    
    showError: function() {
        $('#globalSearchResults').html(`
            <div class="text-center text-danger p-4">
                <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0">Error performing search</p>
            </div>
        `);
    }
};

// Initialize when DOM is ready
$(document).ready(function() {
    GlobalSearch.init();
});

