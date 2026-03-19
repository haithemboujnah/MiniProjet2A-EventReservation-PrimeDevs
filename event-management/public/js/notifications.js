class NotificationManager {
    constructor() {
        this.notifications = [];
        this.unreadCount = 0;
        this.panel = document.getElementById('notificationPanel');
        this.bell = document.getElementById('notificationBell');
        this.countBadge = document.getElementById('notificationCount');
        this.list = document.getElementById('notificationList');
        this.markReadBtn = document.getElementById('markAllRead');
        this.tabBtns = document.querySelectorAll('.notification-tabs .tab-btn');
        this.currentTab = 'all';
        
        this.init();
    }

    init() {
        // Toggle panel on bell click
        this.bell.addEventListener('click', (e) => {
            e.stopPropagation();
            this.togglePanel();
        });

        // Close panel when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.panel.contains(e.target) && !this.bell.contains(e.target)) {
                this.panel.classList.remove('show');
            }
        });

        // Tab switching
        this.tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                this.tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.currentTab = btn.dataset.tab;
                this.renderNotifications();
            });
        });

        // Mark all as read
        if (this.markReadBtn) {
            this.markReadBtn.addEventListener('click', () => this.markAllAsRead());
        }

        // Load notifications
        this.loadNotifications();
        
        // Refresh every 30 seconds
        setInterval(() => this.loadNotifications(), 30000);
    }

    togglePanel() {
        this.panel.classList.toggle('show');
        if (this.panel.classList.contains('show')) {
            this.loadNotifications(true);
        }
    }

    async loadNotifications(force = false) {
        try {
            this.showLoading();
            
            // Load count
            const countResponse = await fetch('/admin/notifications/count');
            const countData = await countResponse.json();
            this.updateCount(countData.count);
            
            // Load list if panel is open or force refresh
            if (this.panel.classList.contains('show') || force) {
                const listResponse = await fetch('/admin/notifications/list');
                const listData = await listResponse.json();
                this.notifications = listData.notifications;
                this.renderNotifications();
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            this.showError();
        }
    }

    updateCount(count) {
        this.unreadCount = count;
        this.countBadge.textContent = count > 9 ? '9+' : count;
        this.countBadge.style.display = count > 0 ? 'flex' : 'none';
    }

    renderNotifications() {
        const filtered = this.currentTab === 'unread' 
            ? this.notifications.filter(n => !n.read) 
            : this.notifications;

        if (filtered.length === 0) {
            this.showEmpty();
            return;
        }

        let html = '';
        filtered.forEach(notif => {
            html += `
                <div class="notification-item ${notif.read ? '' : 'unread'}" data-id="${notif.id}" onclick="window.location.href='${notif.link}'">
                    <div class="notification-icon ${notif.color}">
                        <i class="bi ${notif.icon}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notif.title}</div>
                        <div class="notification-message">${notif.message}</div>
                        <div class="notification-time">
                            <i class="bi bi-clock"></i>
                            ${notif.time}
                        </div>
                    </div>
                </div>
            `;
        });

        this.list.innerHTML = html;
    }

    showLoading() {
        this.list.innerHTML = `
            <div class="notification-loading">
                <div class="spinner"></div>
                <p>Loading notifications...</p>
            </div>
        `;
    }

    showEmpty() {
        this.list.innerHTML = `
            <div class="notification-empty">
                <i class="bi bi-bell-slash"></i>
                <p>No notifications</p>
            </div>
        `;
    }

    showError() {
        this.list.innerHTML = `
            <div class="notification-empty">
                <i class="bi bi-exclamation-triangle" style="color: #f56565;"></i>
                <p>Error loading notifications</p>
                <button onclick="notificationManager.loadNotifications(true)" style="
                    margin-top: 1rem;
                    padding: 0.5rem 1rem;
                    background: var(--primary-gradient);
                    color: white;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                ">Retry</button>
            </div>
        `;
    }

    async markAllAsRead() {
        // Here you would call an API to mark all as read
        // For now, just update UI
        document.querySelectorAll('.notification-item').forEach(item => {
            item.classList.remove('unread');
        });
        this.updateCount(0);
    }
}

// Initialize on page load
let notificationManager;
document.addEventListener('DOMContentLoaded', () => {
    notificationManager = new NotificationManager();
});