/*=============================================
Performance Improvements
=============================================*/

var PerformanceOptimizer = {
    cache: {},
    cacheTimeout: 5 * 60 * 1000, // 5 minutos
    
    init: function() {
        this.setupAjaxCache();
        this.optimizeTableLoading();
        this.setupLazyLoading();
        this.optimizeImages();
    },
    
    setupAjaxCache: function() {
        var self = this;
        var originalAjax = $.ajax;
        
        // Intercept AJAX calls to add cache
        $.ajax = function(options) {
            // Only cache GET requests
            if (options.method === 'GET' || !options.method) {
                var cacheKey = options.url + JSON.stringify(options.data || {});
                
                // Check cache
                if (self.cache[cacheKey]) {
                    var cached = self.cache[cacheKey];
                    if (Date.now() - cached.timestamp < self.cacheTimeout) {
                        // Use cached data
                        if (options.success) {
                            options.success(cached.data);
                        }
                        return $.Deferred().resolve(cached.data);
                    } else {
                        // Remove expired cache
                        delete self.cache[cacheKey];
                    }
                }
                
                // Save to cache after request
                var originalSuccess = options.success;
                options.success = function(data) {
                    self.cache[cacheKey] = {
                        data: data,
                        timestamp: Date.now()
                    };
                    if (originalSuccess) {
                        originalSuccess(data);
                    }
                };
            }
            
            return originalAjax.call(this, options);
        };
    },
    
    optimizeTableLoading: function() {
        // Debounce for table searches
        var searchTimeout;
        
        $(document).on('input', '#searchItem', function() {
            var $input = $(this);
            clearTimeout(searchTimeout);
            
            searchTimeout = setTimeout(function() {
                // Trigger search after 500ms of no typing
                $input.trigger('search');
            }, 500);
        });
        
        // Virtual scrolling for large tables (optional)
        this.setupVirtualScrolling();
    },
    
    setupVirtualScrolling: function() {
        // Basic virtual scrolling implementation
        // Only activates if there are more than 100 records
        $(document).on('DOMNodeInserted', '#loadTable', function() {
            var $table = $(this);
            var $rows = $table.find('tr');
            
            if ($rows.length > 100) {
                // Implement virtual scrolling here if needed
                // For now, just optimize rendering
                $table.css('will-change', 'transform');
            }
        });
    },
    
    setupLazyLoading: function() {
        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    }
                });
            });
            
            // Observe all images with data-src
            document.querySelectorAll('img[data-src]').forEach(function(img) {
                imageObserver.observe(img);
            });
        }
    },
    
    optimizeImages: function() {
        // Convert images to lazy loading
        $(document).on('DOMNodeInserted', 'img', function() {
            var $img = $(this);
            if (!$img.attr('data-src') && $img.attr('src') && !$img.closest('.modal').length) {
                // Only for images outside initial viewport
                var rect = this.getBoundingClientRect();
                if (rect.bottom > window.innerHeight + 500) {
                    $img.attr('data-src', $img.attr('src'));
                    $img.attr('src', 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"%3E%3C/svg%3E');
                }
            }
        });
    },
    
    clearCache: function() {
        this.cache = {};
    },
    
    clearCacheForUrl: function(urlPattern) {
        Object.keys(this.cache).forEach(function(key) {
            if (key.includes(urlPattern)) {
                delete this.cache[key];
            }
        });
    }
};

// Initialize when DOM is ready
$(document).ready(function() {
    PerformanceOptimizer.init();
});

// Clear cache when data is updated
$(document).on('ajaxSuccess', function(event, xhr, settings) {
    if (settings.url && (settings.url.includes('POST') || settings.url.includes('PUT') || settings.url.includes('DELETE'))) {
        // Clear related cache
        var url = settings.url.split('?')[0];
        PerformanceOptimizer.clearCacheForUrl(url);
    }
});

