/*=============================================
Notifications System
=============================================*/

var NotificationSystem = {
    notifications: [],
    unreadCount: 0,
    checkInterval: null,
    
    init: function() {
        this.createNotificationContainer();
        this.addNotificationBell();
        this.startPolling();
        this.bindEvents();
    },
    
    createNotificationContainer: function() {
        var containerHTML = `
            <div id="notificationContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 9999; margin-top: 60px;">
                <div id="notificationList"></div>
            </div>
        `;
        $('body').append(containerHTML);
    },
    
    addNotificationBell: function() {
        // Add notification bell to nav
        if ($('#notificationBell').length === 0) {
            var bellHTML = `
                <div class="p-2 position-relative" id="notificationBell">
                    <a href="#" class="text-dark" id="notificationBellLink" title="Notificaciones">
                        <i class="bi bi-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge" style="font-size: 0.6rem; display: none;">
                            0
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg border-0" id="notificationDropdown" style="width: 380px; max-height: 600px; overflow-y: auto; display: none; position: absolute; top: 100%; right: 0; margin-top: 5px; z-index: 1050;">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-light">
                            <h6 class="mb-0 fw-bold">Notificaciones</h6>
                            <button class="btn btn-sm btn-link text-muted p-0 text-decoration-none" id="markAllRead" style="font-size: 0.75rem;">Marcar todas como leídas</button>
                        </div>
                        <div id="notificationDropdownContent">
                            <div class="text-center text-muted p-4">
                                <i class="bi bi-bell-slash" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No hay notificaciones</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Insert before profile in nav
            $('.navbar .d-flex').prepend(bellHTML);
            
            // Check for updates and add to notifications after a short delay
            var self = this;
            setTimeout(function() {
                self.checkForUpdates();
            }, 1000);
        }
    },
    
    checkForUpdates: function() {
        var self = this;
        
        // Check if user is superadmin or admin
        var role = $('a[href="#myProfile"]').text().trim();
        if (role !== 'superadmin' && role !== 'admin') {
            return;
        }
        
        // Check for updates via AJAX
        $.ajax({
            url: CMS_AJAX_PATH + '/updates.ajax.php',
            method: 'POST',
            data: { action: 'check' },
            success: function(response) {
                try {
                    var data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    // Debug: log response
                    if (data.success && data.data) {
                        // Check if update is available (can be true, "true", or 1)
                        var updateAvailable = data.data.update_available;
                        if (updateAvailable === true || updateAvailable === 'true' || updateAvailable === 1) {
                            // Add update notification to the list
                            self.addUpdateNotification(data.data);
                        }
                    }
                } catch (e) {
                    // Silently fail - don't break the page
                }
            },
            error: function(xhr, status, error) {
                // Silently fail - don't break the page
            }
        });
    },
    
    addUpdateNotification: function(updateData) {
        // Check if update notification already exists in array
        var existingUpdate = this.notifications.find(function(n) {
            return n.id_notification === 'update';
        });
        
        if (existingUpdate) {
            // Update existing notification
            existingUpdate.message_notification = 'Hay una nueva versión disponible: ' + (updateData.latest_version || 'Nueva versión');
            existingUpdate.read_notification = 0;
            this.renderDropdown();
            return;
        }
        
        var updateItem = {
            id_notification: 'update',
            title_notification: 'Actualización disponible',
            message_notification: 'Hay una nueva versión disponible: ' + (updateData.latest_version || 'Nueva versión'),
            type_notification: 'warning',
            icon_notification: 'bi-download',
            url_notification: window.CMS_BASE_PATH ? window.CMS_BASE_PATH + '/updates' : '/updates',
            read_notification: 0,
            read: 0,
            date_created_notification: new Date().toISOString()
        };
        
        // Add to beginning of notifications array
        this.notifications.unshift(updateItem);
        this.updateUnreadCount(this.unreadCount);
        this.renderDropdown();
    },
    
    bindEvents: function() {
        var self = this;
        
        // Toggle dropdown
        $(document).on('click', '#notificationBellLink', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $dropdown = $('#notificationDropdown');
            var isVisible = $dropdown.is(':visible');
            
            // Close all other dropdowns
            $('.dropdown-menu').not($dropdown).hide();
            
            // Toggle this dropdown
            if (isVisible) {
                $dropdown.hide();
            } else {
                $dropdown.show();
            }
        });
        
        // Close when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#notificationBell').length) {
                $('#notificationDropdown').hide();
            }
        });
        
        // Prevent dropdown from closing when clicking inside
        $(document).on('click', '#notificationDropdown', function(e) {
            e.stopPropagation();
        });
        
        // Mark all as read
        $(document).on('click', '#markAllRead', function(e) {
            e.preventDefault();
            e.stopPropagation();
            self.markAllAsRead();
        });
        
        // Mark one as read (except update notifications)
        $(document).on('click', '.notification-item:not(.update-notification)', function(e) {
            var id = $(this).data('id');
            if (id && id !== 'update') {
                self.markAsRead(id);
            }
        });
    },
    
    startPolling: function() {
        var self = this;
        
        // Check notifications every 30 seconds
        this.checkNotifications();
        this.checkInterval = setInterval(function() {
            self.checkNotifications();
            // Also check for updates periodically (every 5 minutes)
            self.checkForUpdates();
        }, 30000);
        
        // Check for updates every 5 minutes
        this.updateCheckInterval = setInterval(function() {
            self.checkForUpdates();
        }, 300000); // 5 minutes
    },
    
    checkNotifications: function() {
        var self = this;
        
        $.ajax({
            url: CMS_AJAX_PATH + '/notifications.ajax.php',
            method: 'POST',
            data: {
                action: 'get',
                token: localStorage.getItem('tokenAdmin') || ''
            },
            success: function(response) {
                try {
                    var data = typeof response === 'string' ? JSON.parse(response) : response;
                    if (data.success) {
                        // Update regular notifications
                        self.updateNotifications(data.notifications || []);
                        // Update count (will include updates automatically)
                        self.updateUnreadCount(data.unread_count || 0);
                        // Re-check for updates periodically
                        self.checkForUpdates();
                    }
                } catch (e) {
                    console.error('Error parsing notifications:', e);
                }
            },
            error: function() {
                // Silently fail - don't break the page
            }
        });
    },
    
    updateNotifications: function(notifications) {
        this.notifications = notifications;
        this.renderDropdown();
    },
    
    updateUnreadCount: function(count) {
        // Count regular notifications
        var regularCount = count || 0;
        
        // Count update notifications
        var updateCount = this.notifications.filter(function(n) {
            return n.id_notification === 'update' && (n.read_notification == 0 || n.read == 0);
        }).length;
        
        // Total unread count
        this.unreadCount = regularCount + updateCount;
        var $badge = $('#notificationBadge');
        
        if (this.unreadCount > 0) {
            $badge.text(this.unreadCount > 99 ? '99+' : this.unreadCount).show();
        } else {
            $badge.hide();
        }
    },
    
    renderDropdown: function() {
        var self = this;
        var $content = $('#notificationDropdownContent');
        $content.empty();
        
        if (this.notifications.length === 0) {
            $content.html(`
                <div class="text-center text-muted p-4">
                    <i class="bi bi-bell-slash" style="font-size: 2rem;"></i>
                    <p class="mt-2 mb-0">No hay notificaciones</p>
                </div>
            `);
            return;
        }
        
        // Separate update notifications from regular notifications
        var updateNotifications = this.notifications.filter(function(n) {
            return n.id_notification === 'update';
        });
        var regularNotifications = this.notifications.filter(function(n) {
            return n.id_notification !== 'update';
        });
        
        // Render update notifications first
        if (updateNotifications.length > 0) {
            updateNotifications.forEach(function(notification) {
                var $item = self.createNotificationItem(notification, true);
                $content.append($item);
            });
            
            // Add separator if there are regular notifications
            if (regularNotifications.length > 0) {
                $content.append('<div class="dropdown-divider"></div>');
            }
        }
        
        // Render regular notifications
        regularNotifications.forEach(function(notification) {
            var $item = self.createNotificationItem(notification, false);
            $content.append($item);
        });
    },
    
    createNotificationItem: function(notification, isUpdate) {
        var self = this;
        var isUnread = notification.read_notification == 0 || notification.read == 0;
        var itemClass = 'dropdown-item notification-item p-3 border-bottom';
        if (isUnread) {
            itemClass += ' bg-light';
        }
        if (isUpdate) {
            itemClass += ' update-notification';
        }
        
        var $item = $('<a href="#" class="' + itemClass + '" data-id="' + notification.id_notification + '">' +
            '<div class="d-flex align-items-start">' +
            '<div class="flex-shrink-0 me-3">' +
            '<i class="bi ' + (notification.icon_notification || 'bi-info-circle') + ' text-' + 
            (notification.type_notification || 'primary') + '" style="font-size: 1.25rem;"></i>' +
            '</div>' +
            '<div class="flex-grow-1">' +
            '<h6 class="mb-1' + (isUnread ? ' fw-bold' : '') + '">' + notification.title_notification + '</h6>' +
            '<p class="mb-1 small text-muted" style="line-height: 1.4;">' + notification.message_notification + '</p>' +
            '<small class="text-muted">' + self.formatDate(notification.date_created_notification) + '</small>' +
            '</div>' +
            (isUnread ? '<div class="flex-shrink-0 ms-2"><span class="badge bg-primary rounded-pill" style="font-size: 0.5rem;">Nuevo</span></div>' : '') +
            '</div>' +
            '</a>');
        
        if (notification.url_notification) {
            $item.attr('href', notification.url_notification);
            $item.on('click', function(e) {
                if (notification.id_notification !== 'update') {
                    // Mark as read when clicked (except for update notifications)
                    self.markAsRead(notification.id_notification);
                }
            });
        }
        
        return $item;
    },
    
    showNotification: function(notification) {
        // Show toast notification
        var type = notification.type_notification || 'info';
        var icon = notification.icon_notification || 'bi-info-circle';
        
        var toastHTML = `
            <div class="toast show align-items-center text-white bg-${type} border-0" role="alert" style="min-width: 300px;">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi ${icon} me-2"></i>
                        <strong>${notification.title_notification}</strong><br>
                        <small>${notification.message_notification}</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        var $toast = $(toastHTML);
        $('#notificationList').prepend($toast);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $toast.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Update counter
        this.updateUnreadCount(this.unreadCount + 1);
    },
    
    markAsRead: function(id) {
        var self = this;
        
        $.ajax({
            url: CMS_AJAX_PATH + '/notifications.ajax.php',
            method: 'POST',
            data: {
                action: 'mark_read',
                id: id,
                token: localStorage.getItem('tokenAdmin') || ''
            },
            success: function() {
                // Update locally
                var notification = self.notifications.find(function(n) {
                    return n.id_notification == id;
                });
                if (notification) {
                    notification.read = 1;
                    self.updateUnreadCount(Math.max(0, self.unreadCount - 1));
                    self.renderDropdown();
                }
            }
        });
    },
    
    markAllAsRead: function() {
        var self = this;
        
        $.ajax({
            url: CMS_AJAX_PATH + '/notifications.ajax.php',
            method: 'POST',
            data: {
                action: 'mark_all_read',
                token: localStorage.getItem('tokenAdmin') || ''
            },
            success: function() {
                self.notifications.forEach(function(n) {
                    n.read = 1;
                });
                self.updateUnreadCount(0);
                self.renderDropdown();
            }
        });
    },
    
    formatDate: function(dateString) {
        if (!dateString) return '';
        
        var date = new Date(dateString);
        var now = new Date();
        var diff = now - date;
        var minutes = Math.floor(diff / 60000);
        var hours = Math.floor(diff / 3600000);
        var days = Math.floor(diff / 86400000);
        
        if (minutes < 1) return 'Just now';
        if (minutes < 60) return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
        if (hours < 24) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
        if (days < 7) return days + ' day' + (days > 1 ? 's' : '') + ' ago';
        
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
};

// Initialize when DOM is ready
$(document).ready(function() {
    NotificationSystem.init();
});

