/*=============================================
Global Authentication Interceptor
Automatically handles expired tokens
=============================================*/

var AuthInterceptor = {
    isLoggingOut: false,
    
    init: function() {
        var self = this;
        
        $(document).ajaxComplete(function(event, xhr, settings) {
            self.handleResponse(xhr, settings);
        });
        
        $(document).ajaxError(function(event, xhr, settings, thrownError) {
            self.handleError(xhr, settings, thrownError);
        });
    },
    
    handleResponse: function(xhr, settings) {
        if (this.isLoggingOut) {
            return;
        }
        
        var url = settings.url || '';
        if (url.indexOf('login') !== -1 || url.indexOf('logout') !== -1) {
            return;
        }
        
        var status = xhr.status;
        var responseText = xhr.responseText || '';
        
        if (status === 303) {
            this.handleTokenExpired();
            return;
        }
        
        try {
            var response = typeof responseText === 'string' ? JSON.parse(responseText) : responseText;
            
            if (response && response.results) {
                var results = typeof response.results === 'string' ? response.results : JSON.stringify(response.results);
                
                if (results.indexOf('token has expired') !== -1 || 
                    results.indexOf('token expirado') !== -1 ||
                    results.indexOf('The token has expired') !== -1) {
                    this.handleTokenExpired();
                    return;
                }
            }
            
            if (response && response.status === 303) {
                this.handleTokenExpired();
                return;
            }
        } catch (e) {
            if (responseText.indexOf('token has expired') !== -1 || 
                responseText.indexOf('token expirado') !== -1 ||
                responseText.indexOf('The token has expired') !== -1) {
                this.handleTokenExpired();
                return;
            }
        }
    },
    
    handleError: function(xhr, settings, thrownError) {
        if (this.isLoggingOut) {
            return;
        }
        
        var url = settings.url || '';
        if (url.indexOf('login') !== -1 || url.indexOf('logout') !== -1) {
            return;
        }
        
        if (xhr.status === 303) {
            this.handleTokenExpired();
            return;
        }
        
        var responseText = xhr.responseText || '';
        
        try {
            var response = typeof responseText === 'string' ? JSON.parse(responseText) : responseText;
            
            if (response && response.results) {
                var results = typeof response.results === 'string' ? response.results : JSON.stringify(response.results);
                
                if (results.indexOf('token has expired') !== -1 || 
                    results.indexOf('token expirado') !== -1 ||
                    results.indexOf('The token has expired') !== -1) {
                    this.handleTokenExpired();
                    return;
                }
            }
        } catch (e) {
            if (responseText.indexOf('token has expired') !== -1 || 
                responseText.indexOf('token expirado') !== -1 ||
                responseText.indexOf('The token has expired') !== -1) {
                this.handleTokenExpired();
                return;
            }
        }
    },
    
    handleTokenExpired: function() {
        if (this.isLoggingOut) {
            return;
        }
        
        this.isLoggingOut = true;
        
        if (typeof fncSweetAlert !== 'undefined') {
            fncSweetAlert(
                "warning",
                "Tu sesión ha expirado",
                "Por favor, inicia sesión nuevamente para continuar",
                function() {
                    AuthInterceptor.logout();
                }
            );
        } else {
            alert("Tu sesión ha expirado. Serás redirigido al inicio de sesión.");
            this.logout();
        }
    },
    
    logout: function() {
        localStorage.removeItem('tokenAdmin');
        
        var cmsBasePath = window.CMS_BASE_PATH || '';
        
        if (cmsBasePath) {
            window.location.href = cmsBasePath + '/logout';
        } else {
            window.location.href = '/logout';
        }
    }
};

// Validate token on page load
function validateTokenOnLoad() {
    var token = window.CMS_TOKEN || localStorage.getItem("tokenAdmin");
    
    if (!token) {
        // No token available, redirect to login
        var cmsBasePath = window.CMS_BASE_PATH || '';
        if (cmsBasePath) {
            window.location.href = cmsBasePath + '/login';
        } else {
            window.location.href = '/login';
        }
        return;
    }
    
    // Validate token by making a simple request
    $.ajax({
        url: (window.CMS_AJAX_PATH || '/ajax') + '/session-init.php',
        method: 'GET',
        cache: false,
        success: function(response) {
            // Token is valid, continue
        },
        error: function(xhr, status, error) {
            // If token expired, handle it
            if (xhr.status === 303 || 
                (xhr.responseText && (xhr.responseText.indexOf('token has expired') !== -1 || 
                                      xhr.responseText.indexOf('token expirado') !== -1 ||
                                      xhr.responseText.indexOf('The token has expired') !== -1))) {
                AuthInterceptor.handleTokenExpired();
            }
        }
    });
}

$(document).ready(function() {
    AuthInterceptor.init();
    
    // Validate token on page load (only if we're not on login/logout pages)
    var currentPath = window.location.pathname;
    if (currentPath.indexOf('/login') === -1 && 
        currentPath.indexOf('/logout') === -1 &&
        currentPath.indexOf('/install') === -1) {
        validateTokenOnLoad();
    }
});

