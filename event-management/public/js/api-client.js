class ApiClient {
    constructor() {
        this.baseUrl = '/api';
        this.token = localStorage.getItem('jwt_token');
        this.refreshToken = localStorage.getItem('refresh_token');
    }

    setTokens(token, refreshToken) {
        this.token = token;
        this.refreshToken = refreshToken;
        localStorage.setItem('jwt_token', token);
        localStorage.setItem('refresh_token', refreshToken);
    }

    clearTokens() {
        this.token = null;
        this.refreshToken = null;
        localStorage.removeItem('jwt_token');
        localStorage.removeItem('refresh_token');
    }

    async request(endpoint, options = {}) {
        const url = this.baseUrl + endpoint;
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        const config = {
            ...options,
            headers
        };

        try {
            let response = await fetch(url, config);
            
            // If token expired, try to refresh
            if (response.status === 401 && this.refreshToken) {
                const refreshed = await this.refreshToken();
                if (refreshed) {
                    // Retry original request with new token
                    headers['Authorization'] = `Bearer ${this.token}`;
                    response = await fetch(url, config);
                }
            }

            return response;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    async refreshToken() {
        try {
            const response = await fetch(this.baseUrl + '/token/refresh', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    refresh_token: this.refreshToken
                })
            });

            if (response.ok) {
                const data = await response.json();
                this.setTokens(data.token, data.refresh_token);
                return true;
            } else {
                this.clearTokens();
                return false;
            }
        } catch (error) {
            this.clearTokens();
            return false;
        }
    }

    async login(email, password) {
        const response = await fetch(this.baseUrl + '/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, password })
        });

        if (response.ok) {
            const data = await response.json();
            this.setTokens(data.token, data.refresh_token);
        }

        return response;
    }

    async register(userData) {
        const response = await fetch(this.baseUrl + '/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userData)
        });

        if (response.ok) {
            const data = await response.json();
            this.setTokens(data.token, data.refresh_token);
        }

        return response;
    }

    async logout() {
        if (this.token) {
            await fetch(this.baseUrl + '/logout', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`
                }
            });
        }
        this.clearTokens();
    }

    async getEvents(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const response = await this.request('/events?' + queryString);
        return response.json();
    }

    async getEvent(id) {
        const response = await this.request(`/events/${id}`);
        return response.json();
    }

    async reserveEvent(id, data) {
        const response = await this.request(`/events/${id}/reserve`, {
            method: 'POST',
            body: JSON.stringify(data)
        });
        return response.json();
    }

    async cancelReservation(eventId) {
        const response = await this.request(`/events/${eventId}/cancel`, {
            method: 'POST'
        });
        return response.json();
    }

    async getMyReservations() {
        const response = await this.request('/reservations');
        return response.json();
    }

    async getCurrentUser() {
        const response = await this.request('/me');
        return response.json();
    }
}

// Usage example:
// const api = new ApiClient();
// await api.login('user@example.com', 'password');
// const events = await api.getEvents({ filter: 'upcoming' });