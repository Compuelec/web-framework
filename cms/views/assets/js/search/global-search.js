/*=============================================
Global Search for CMS
=============================================*/

var GlobalSearch = {
    isOpen: false,
    searchTerm: '',
    results: [],
    currentIndex: -1,
    _recentsKey: 'cms_search_recents',
    _searchTimer: null,

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
                                       placeholder="Buscar páginas, módulos, datos... (ESC para cerrar)"
                                       autocomplete="off">
                                <span class="input-group-text bg-white border-0">
                                    <kbd class="bg-light">Ctrl+K</kbd>
                                </span>
                            </div>
                            <div id="globalSearchResults" class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;"></div>
                        </div>
                        <div class="modal-footer bg-light border-0">
                            <small class="text-muted">
                                <kbd>↑↓</kbd> Navegar &nbsp;|&nbsp; <kbd>Enter</kbd> Ir &nbsp;|&nbsp; <kbd>ESC</kbd> Cerrar
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

        // Real-time search with debounce
        $(document).on('input', '#globalSearchInput', function() {
            var term = $(this).val().trim();
            clearTimeout(self._searchTimer);
            if (term.length >= 2) {
                self._searchTimer = setTimeout(function() { self.search(term); }, 250);
            } else {
                self.showRecents();
            }
        });

        // Click on result
        $(document).on('click', '.global-search-result', function(e) {
            e.preventDefault();
            var url   = $(this).data('url');
            var title = $(this).data('title');
            var type  = $(this).data('type');
            var icon  = $(this).data('icon');
            if (url) {
                self.saveRecent({ title: title, url: url, type: type, icon: icon });
                window.location.href = url;
            }
        });
    },

    open: function() {
        this.isOpen = true;
        $('#globalSearchModal').modal('show');
        setTimeout(function() { $('#globalSearchInput').focus(); }, 200);
        this.currentIndex = -1;
        this.showRecents();
    },

    close: function() {
        this.isOpen = false;
        $('#globalSearchModal').modal('hide');
        $('#globalSearchInput').val('');
        this.results = [];
        this.currentIndex = -1;
        clearTimeout(this._searchTimer);
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
                token: window.CMS_TOKEN || '' || ''
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
            $container.html(
                '<div class="text-center text-muted p-4">' +
                '<i class="bi bi-inbox" style="font-size:2rem;"></i>' +
                '<p class="mt-2 mb-0">Sin resultados para <strong>' + this.esc(this.searchTerm) + '</strong></p>' +
                '</div>'
            );
            return;
        }

        var grouped = this.groupResultsByType(this.results);
        var self = this;

        Object.keys(grouped).forEach(function(type) {
            var typeLabel = self.getTypeLabel(type);
            $container.append(
                '<div class="list-group-item bg-light py-1 px-3">' +
                '<small class="text-muted text-uppercase fw-bold" style="font-size:10px">' + typeLabel + '</small>' +
                '</div>'
            );

            grouped[type].forEach(function(result) {
                var icon = result.icon || 'bi-file-text';
                $container.append(
                    '<a href="' + self.esc(result.url) + '" ' +
                    'class="list-group-item list-group-item-action global-search-result px-3 py-2" ' +
                    'data-url="' + self.esc(result.url) + '" ' +
                    'data-title="' + self.esc(result.title) + '" ' +
                    'data-type="' + self.esc(result.type) + '" ' +
                    'data-icon="' + self.esc(icon) + '">' +
                    '<div class="d-flex align-items-center gap-2">' +
                    '<i class="bi ' + self.esc(icon) + ' text-muted flex-shrink-0" style="font-size:14px"></i>' +
                    '<div class="flex-grow-1 overflow-hidden">' +
                    '<div class="fw-semibold text-truncate" style="font-size:13px">' + self.esc(result.title) + '</div>' +
                    (result.description ? '<small class="text-muted">' + self.esc(result.description) + '</small>' : '') +
                    '</div>' +
                    '<i class="bi bi-chevron-right text-muted flex-shrink-0" style="font-size:11px"></i>' +
                    '</div>' +
                    '</a>'
                );
            });
        });

        this.highlightSearchTerm();
    },

    groupResultsByType: function(results) {
        var grouped = {};
        results.forEach(function(result) {
            if (!grouped[result.type]) grouped[result.type] = [];
            grouped[result.type].push(result);
        });
        return grouped;
    },

    getTypeLabel: function(type) {
        var labels = { page: 'Páginas', module: 'Módulos', data: 'Datos', file: 'Archivos' };
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
    
    // ── Recent results (localStorage) ────────────────────

    getRecents: function() {
        try {
            return JSON.parse(localStorage.getItem(this._recentsKey) || '[]');
        } catch(e) { return []; }
    },

    saveRecent: function(item) {
        var recents = this.getRecents().filter(function(r) { return r.url !== item.url; });
        recents.unshift(item);
        if (recents.length > 8) recents = recents.slice(0, 8);
        try { localStorage.setItem(this._recentsKey, JSON.stringify(recents)); } catch(e) {}
    },

    showRecents: function() {
        var recents = this.getRecents();
        var $container = $('#globalSearchResults');
        $container.empty();

        if (recents.length === 0) {
            $container.html(
                '<div class="text-center text-muted p-4">' +
                '<i class="bi bi-search" style="font-size:2rem;"></i>' +
                '<p class="mt-2 mb-0">Escribe para buscar páginas, módulos y datos</p>' +
                '</div>'
            );
            return;
        }

        var self = this;
        $container.append(
            '<div class="list-group-item bg-light py-1 px-3">' +
            '<small class="text-muted text-uppercase fw-bold" style="font-size:10px">Visitados recientemente</small>' +
            '</div>'
        );
        recents.forEach(function(item) {
            var icon = item.icon || 'bi-clock-history';
            $container.append(
                '<a href="' + self.esc(item.url) + '" ' +
                'class="list-group-item list-group-item-action global-search-result px-3 py-2" ' +
                'data-url="' + self.esc(item.url) + '" ' +
                'data-title="' + self.esc(item.title) + '" ' +
                'data-type="' + self.esc(item.type || 'page') + '" ' +
                'data-icon="' + self.esc(icon) + '">' +
                '<div class="d-flex align-items-center gap-2">' +
                '<i class="bi ' + self.esc(icon) + ' text-muted flex-shrink-0" style="font-size:14px"></i>' +
                '<span class="flex-grow-1 text-truncate" style="font-size:13px">' + self.esc(item.title) + '</span>' +
                '<i class="bi bi-chevron-right text-muted flex-shrink-0" style="font-size:11px"></i>' +
                '</div>' +
                '</a>'
            );
        });
    },

    // ── Helpers ───────────────────────────────────────────

    esc: function(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    },

    showLoading: function() {
        $('#globalSearchResults').html(
            '<div class="text-center p-4">' +
            '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>' +
            '<p class="mt-2 text-muted mb-0">Buscando...</p>' +
            '</div>'
        );
    },

    showError: function() {
        $('#globalSearchResults').html(
            '<div class="text-center text-danger p-4">' +
            '<i class="bi bi-exclamation-triangle" style="font-size:2rem;"></i>' +
            '<p class="mt-2 mb-0">Error al realizar la búsqueda</p>' +
            '</div>'
        );
    }
};

// Initialize when DOM is ready
$(document).ready(function() {
    GlobalSearch.init();
});

