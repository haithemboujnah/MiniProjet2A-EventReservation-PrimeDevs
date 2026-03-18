// Token management
class TokenManager {
    constructor() {
        this.token = document.querySelector('meta[name="jwt-token"]')?.content;
        this.init();
    }

    init() {
        // Copy token functionality
        document.querySelectorAll('.btn-copy-code').forEach(btn => {
            btn.addEventListener('click', (e) => this.copyCode(e.target));
        });
    }

    copyCode(button) {
        const codeBlock = button.closest('.tab-pane').querySelector('code');
        const code = codeBlock.innerText;
        
        navigator.clipboard.writeText(code).then(() => {
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check-lg"></i>';
            button.classList.add('copied');
            
            setTimeout(() => {
                button.innerHTML = originalHtml;
                button.classList.remove('copied');
            }, 2000);
        });
    }

    decodeToken(token) {
        try {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(c => {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            
            return JSON.parse(jsonPayload);
        } catch (e) {
            console.error('Error decoding token:', e);
            return null;
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    new TokenManager();
});