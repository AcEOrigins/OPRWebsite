(() => {
    const API_ENDPOINT = 'Api/php/login.php';
    const REDIRECT_URL = 'portal.html';
    const LOADING_DELAY_MS = 1200;

    let isProcessing = false;

    function getElements() {
        return {
            usernameInput: document.getElementById('usernameInput'),
            passwordInput: document.getElementById('passwordInput'),
            loginError: document.getElementById('loginError'),
            loadingScreen: document.getElementById('loadingScreen'),
            enterButton: document.querySelector('.password-modal .add-server-btn'),
            loginForm: document.getElementById('loginForm')
        };
    }

    function toggleFieldError(field, hasError) {
        if (!field) {
            return;
        }
        field.classList.toggle('input-error', Boolean(hasError));
        if (hasError) {
            field.setAttribute('aria-invalid', 'true');
        } else {
            field.removeAttribute('aria-invalid');
        }
    }

    function showError(message, fieldKey) {
        const { loginError, usernameInput, passwordInput } = getElements();
        if (!loginError) {
            return;
        }

        loginError.textContent = message;
        loginError.style.display = message ? 'block' : 'none';

        toggleFieldError(usernameInput, fieldKey === 'name');
        toggleFieldError(passwordInput, fieldKey === 'password');
    }

    function clearError() {
        const { loginError, usernameInput, passwordInput } = getElements();
        if (!loginError) {
            return;
        }

        loginError.textContent = '';
        loginError.style.display = 'none';

        toggleFieldError(usernameInput, false);
        toggleFieldError(passwordInput, false);
    }

    function setLoadingState(isLoading) {
        const { loadingScreen, enterButton } = getElements();
        if (enterButton) {
            enterButton.disabled = isLoading;
        }

        if (!loadingScreen) {
            return;
        }

        if (isLoading) {
            loadingScreen.style.display = 'flex';
            requestAnimationFrame(() => {
                loadingScreen.classList.add('rotating');
            });
        } else {
            loadingScreen.classList.remove('rotating');
            loadingScreen.style.display = 'none';
        }
    }

    async function handleLogin(event) {
        if (event) {
            event.preventDefault();
        }

        if (isProcessing) {
            return;
        }

        const { usernameInput, passwordInput } = getElements();
        if (!usernameInput || !passwordInput) {
            return;
        }

        const name = usernameInput.value.trim();
        const password = passwordInput.value;

        if (!name) {
            showError('Name is required.', 'name');
            usernameInput.focus();
            return;
        }

        if (!password) {
            showError('Password is required.', 'password');
            passwordInput.focus();
            return;
        }

        clearError();
        isProcessing = true;
        setLoadingState(true);

        let loginSuccessful = false;

        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ name, password }),
                credentials: 'same-origin'
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok || !result.success) {
                const message = result.message || 'Invalid credentials. Please try again.';
                showError(message);
                passwordInput.focus();
                passwordInput.select();
                return;
            }

            loginSuccessful = true;

            setTimeout(() => {
                window.location.href = result.redirectUrl || REDIRECT_URL;
            }, LOADING_DELAY_MS);
        } catch (error) {
            console.error('Login error:', error);
            showError('Unable to reach the server. Please try again.');
        } finally {
            if (!loginSuccessful) {
                setLoadingState(false);
                isProcessing = false;
            } else {
                isProcessing = false;
            }
        }
    }

    function init() {
        const { usernameInput, passwordInput, loginForm } = getElements();
        if (!usernameInput || !passwordInput || !loginForm) {
            return;
        }

        usernameInput.addEventListener('input', () => {
            if (usernameInput.value.trim().length > 0) {
                clearError();
            }
        });

        passwordInput.addEventListener('input', () => {
            if (passwordInput.value.trim().length > 0) {
                clearError();
            }
        });

        loginForm.addEventListener('submit', handleLogin);
    }

    document.addEventListener('DOMContentLoaded', init);
})();

