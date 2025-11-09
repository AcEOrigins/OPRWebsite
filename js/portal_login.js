(() => {
    const ADMIN_PASSWORD = 'OPRAdmin2024!';
    const REDIRECT_URL = 'portal.html';
    const LOADING_DELAY_MS = 1200;

    let isProcessing = false;

    function getElements() {
        return {
            passwordInput: document.getElementById('passwordInput'),
            passwordError: document.getElementById('passwordError'),
            loadingScreen: document.getElementById('loadingScreen'),
            enterButton: document.querySelector('.password-modal .add-server-btn')
        };
    }

    function showError(message) {
        const { passwordError, passwordInput } = getElements();
        if (!passwordError || !passwordInput) {
            return;
        }

        passwordError.textContent = message;
        passwordError.style.display = 'block';
        passwordInput.setAttribute('aria-invalid', 'true');
        passwordInput.classList.add('input-error');
    }

    function clearError() {
        const { passwordError, passwordInput } = getElements();
        if (!passwordError || !passwordInput) {
            return;
        }

        passwordError.style.display = 'none';
        passwordError.textContent = '';
        passwordInput.removeAttribute('aria-invalid');
        passwordInput.classList.remove('input-error');
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

    function handlePasswordCheck() {
        if (isProcessing) {
            return;
        }

        const { passwordInput } = getElements();
        if (!passwordInput) {
            return;
        }

        const password = passwordInput.value.trim();
        if (!password) {
            showError('Password is required.');
            return;
        }

        clearError();
        isProcessing = true;

        if (password !== ADMIN_PASSWORD) {
            showError('Incorrect password. Please try again.');
            passwordInput.focus();
            passwordInput.select();
            isProcessing = false;
            return;
        }

        setLoadingState(true);
        setTimeout(() => {
            window.location.href = REDIRECT_URL;
        }, LOADING_DELAY_MS);

        isProcessing = false;
    }

    function init() {
        const { passwordInput, enterButton } = getElements();
        if (!passwordInput || !enterButton) {
            return;
        }

        passwordInput.addEventListener('input', () => {
            if (passwordInput.value.trim().length > 0) {
                clearError();
            }
        });

        passwordInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                handlePasswordCheck();
            }
        });

        enterButton.addEventListener('click', (event) => {
            event.preventDefault();
            handlePasswordCheck();
        });
    }

    window.checkPassword = handlePasswordCheck;

    document.addEventListener('DOMContentLoaded', init);
})();

