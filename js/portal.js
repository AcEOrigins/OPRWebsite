// Portal Navigation Functionality
document.addEventListener('DOMContentLoaded', function() {
    const navItems = Array.from(document.querySelectorAll('.nav-item[data-tab]'));
    const navItemWrappers = Array.from(document.querySelectorAll('.nav-item-wrapper'));
    const tabContents = document.querySelectorAll('.tab-content');
    const pageTitle = document.getElementById('page-title');
    const serverInfoContainer = document.getElementById('portal-battlemetrics-grid');
    const ourServersTab = document.querySelector('#manage-content .sub-tab-content[data-sub-tab="our-servers"]');
    const addServerError = document.getElementById('add-server-error');

    const API_ENDPOINTS = {
        list: 'Api/php/getServers.php',
        add: 'Api/php/saveServer.php',
        remove: 'Api/php/deleteServer.php'
    };

    let serversCache = [];

    const tabTitles = {
        'dashboard': 'Dashboard',
        'server-control': 'Server Control',
        'manage-content': 'Manage Site',
        'manage-access': 'Manage Access',
        'players': 'Players',
        'settings': 'Settings',
        'logs': 'Logs',
        'battlemetrics': 'BattleMetrics'
    };

    const subTabsConfig = {
        'manage-content': [
            { id: 'slideshow', label: 'Slideshow' },
            { id: 'server-info', label: 'Server Info' },
            { id: 'our-servers', label: 'Our Servers' },
            { id: 'footer', label: 'Footer' },
            { id: 'navigation', label: 'Navigation' }
        ]
    };

    buildSubNavigation();

    navItems.forEach(item => {
        item.addEventListener('click', (event) => {
            event.preventDefault();
            const tabName = item.dataset.tab;
            if (!tabName) {
                return;
            }

            const wrapper = getNavWrapper(tabName);
            const subNav = wrapper ? wrapper.querySelector('.sub-nav') : null;
            const hasSubNav = subNav && subNav.children.length > 0;

            if (hasSubNav) {
                const isExpanded = wrapper.classList.contains('expanded');
                if (isExpanded) {
                    wrapper.classList.remove('expanded');
                    return;
                }

                setWrapperExpansion(tabName, true);
                setActiveMainTab(tabName, { skipWrapperSync: true });
                return;
            }

            navItemWrappers.forEach(w => w.classList.remove('expanded'));
            setActiveMainTab(tabName);
        });
    });

    const initialNavItem = document.querySelector('.nav-item.active[data-tab]') || navItems[0];
    if (initialNavItem) {
        const initialTab = initialNavItem.dataset.tab;
        if (initialTab) {
            if (getDefaultSubTab(initialTab)) {
                setWrapperExpansion(initialTab, true);
                setActiveMainTab(initialTab, { skipWrapperSync: true });
            } else {
                setActiveMainTab(initialTab);
            }
        }
    }

    function buildSubNavigation() {
        Object.entries(subTabsConfig).forEach(([tabId, subTabs]) => {
            const subNav = document.querySelector(`.sub-nav[data-parent-tab="${tabId}"]`);
            if (!subNav) {
                return;
            }

            subNav.innerHTML = '';

            subTabs.forEach((subTab) => {
                const listItem = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'sub-nav-item';
                button.textContent = subTab.label;
                button.dataset.parentTab = tabId;
                button.dataset.subTab = subTab.id;
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    setActiveMainTab(tabId, { preserveSubTab: true });
                    switchSubTab(tabId, subTab.id);
                });
                listItem.appendChild(button);
                subNav.appendChild(listItem);
            });
        });
    }

    function setActiveMainTab(tabId, options = {}) {
        const { preserveSubTab = false, skipSubTabActivation = false, skipWrapperSync = false } = options;

        navItems.forEach(item => {
            item.classList.toggle('active', item.dataset.tab === tabId);
        });

        tabContents.forEach(content => {
            content.classList.toggle('active', content.id === tabId);
        });

        if (pageTitle && tabTitles[tabId]) {
            pageTitle.textContent = tabTitles[tabId];
        }

        if (!skipWrapperSync) {
            const hasDefaultSub = Boolean(getDefaultSubTab(tabId));
            setWrapperExpansion(tabId, hasDefaultSub);
        }

        if (!preserveSubTab && !skipSubTabActivation) {
            const defaultSubTab = getDefaultSubTab(tabId);
            if (defaultSubTab) {
                switchSubTab(tabId, defaultSubTab);
            } else {
                updateAddServerButton(tabId, null);
            }
        } else if (skipSubTabActivation) {
            updateAddServerButton(tabId, null);
        }
    }

    function getDefaultSubTab(tabId) {
        const subTabs = subTabsConfig[tabId];
        return subTabs && subTabs.length > 0 ? subTabs[0].id : null;
    }

    function switchSubTab(mainTabId, subTabId) {
        const mainTab = document.getElementById(mainTabId);
        if (!mainTab) {
            return;
        }

        setWrapperExpansion(mainTabId, true);

        mainTab.querySelectorAll('.sub-tab-content').forEach(content => {
            content.classList.remove('active');
        });

        const activeContent = mainTab.querySelector(`[data-sub-tab="${subTabId}"]`);
        if (activeContent) {
            activeContent.classList.add('active');
        }

        const subNav = document.querySelector(`.sub-nav[data-parent-tab="${mainTabId}"]`);
        if (subNav) {
            subNav.querySelectorAll('.sub-nav-item').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.subTab === subTabId);
            });
        }

        updateAddServerButton(mainTabId, subTabId);
    }

    function updateAddServerButton(mainTabId, subTabId) {
        const addServerBtn = document.getElementById('add-server-btn');
        if (!addServerBtn) {
            return;
        }

        if (mainTabId === 'manage-content' && subTabId === 'our-servers') {
            addServerBtn.style.display = 'flex';
        } else {
            addServerBtn.style.display = 'none';
        }
    }

    // Modal functionality
    const addServerBtn = document.getElementById('add-server-btn');
    const addServerModal = document.getElementById('add-server-modal');
    const closeModalBtn = document.getElementById('close-modal');
    const cancelBtn = document.getElementById('cancel-add-server');
    const addServerForm = document.getElementById('add-server-form');

    // Open modal
    if (addServerBtn) {
        addServerBtn.addEventListener('click', () => {
            addServerModal.classList.add('active');
        });
    }

    // Close modal
    function closeModal() {
        addServerModal.classList.remove('active');
        if (addServerForm) {
            addServerForm.reset();
        }
        setAddServerError('');
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }

    // Close modal when clicking outside
    if (addServerModal) {
        addServerModal.addEventListener('click', (e) => {
            if (e.target === addServerModal) {
                closeModal();
            }
        });
    }

    // Handle form submission
    if (addServerForm) {
        addServerForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const battlemetricsIdInput = document.getElementById('battlemetrics-id');
            const battlemetricsId = battlemetricsIdInput ? battlemetricsIdInput.value.trim() : '';

            if (!battlemetricsId) {
                setAddServerError('BattleMetrics ID is required.');
                if (battlemetricsIdInput) {
                    battlemetricsIdInput.focus();
                }
                return;
            }

            setAddServerError('');
            toggleAddServerForm(true);

            try {
                await addServer(battlemetricsId);
                closeModal();
                await loadServers();
            } catch (error) {
                console.error('Failed to add server:', error);
                setAddServerError(error.message || 'Unable to add server right now.');
            } finally {
                toggleAddServerForm(false);
            }
        });
    }

    async function loadServers() {
        if (!serverInfoContainer) {
            return;
        }

        setServerInfoMessage('Loading servers...');

        try {
            const response = await fetch(API_ENDPOINTS.list, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            const data = await response.json();
            if (!Array.isArray(data)) {
                throw new Error('Unexpected response format');
            }

            serversCache = data;
            renderServersList(serversCache);

            if (serversCache.length === 0) {
                setServerInfoMessage('No servers configured yet.');
            } else {
                renderServerCards(serversCache);
            }
        } catch (error) {
            console.error('Failed to load servers:', error);
            renderServersList([]);
            setServerInfoMessage('Unable to load servers. Please try again later.');
        }
    }

    function renderServersList(servers) {
        if (!ourServersTab) {
            return;
        }

        if (!Array.isArray(servers) || servers.length === 0) {
            ourServersTab.innerHTML = '<p style="color: #cccccc;">No servers have been added yet.</p>';
            return;
        }

        let html = '<div class="servers-list">';
        servers.forEach(server => {
            const displayName = escapeHtml(server.display_name || server.displayName || 'Unknown Server');
            const battlemetricsId = escapeHtml(server.battlemetrics_id || server.battlemetricsId || 'N/A');
            const serverId = Number(server.id) || 0;

            html += `
                <div class="server-card">
                    <h3>${displayName}</h3>
                    <p><strong>BattleMetrics ID:</strong> ${battlemetricsId}</p>
                    <button class="btn-danger delete-server" data-id="${serverId}">Delete</button>
                </div>
            `;
        });
        html += '</div>';
        ourServersTab.innerHTML = html;

        ourServersTab.querySelectorAll('.delete-server').forEach(btn => {
            btn.addEventListener('click', () => {
                const serverId = parseInt(btn.getAttribute('data-id'), 10);
                if (!Number.isNaN(serverId)) {
                    handleDeleteServer(serverId);
                }
            });
        });
    }

    function renderServerCards(servers) {
        if (!serverInfoContainer || !window.Battlemetrics) {
            return;
        }

        const normalizedServers = Array.isArray(servers)
            ? servers.map(normalizeServerForCard)
            : [];

        window.Battlemetrics.renderCards(serverInfoContainer, normalizedServers);
    }

    function normalizeServerForCard(server = {}) {
        return {
            id: server.id,
            battlemetricsId: server.battlemetrics_id || server.battlemetricsId || '',
            displayName: server.display_name || server.displayName || '',
            gameTitle: server.game_title || server.gameTitle || '',
            region: server.region || server.regionLabel || ''
        };
    }

    async function addServer(battlemetricsId) {
        const response = await fetch(API_ENDPOINTS.add, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ battlemetricsId })
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Failed to add server.');
        }

        return result.server || null;
    }

    async function handleDeleteServer(serverId) {
        const confirmed = window.confirm('Remove this server from the portal?');
        if (!confirmed) {
            return;
        }

        try {
            await deleteServer(serverId);
            await loadServers();
        } catch (error) {
            console.error('Failed to remove server:', error);
            alert(error.message || 'Unable to delete the server right now.');
        }
    }

    async function deleteServer(serverId) {
        const response = await fetch(API_ENDPOINTS.remove, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id: serverId })
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Failed to delete server.');
        }

        return true;
    }

    function toggleAddServerForm(isSubmitting) {
        if (!addServerForm) {
            return;
        }

        const battlemetricsInput = addServerForm.querySelector('#battlemetrics-id');
        const submitButton = addServerForm.querySelector('button[type="submit"]');

        if (battlemetricsInput) {
            battlemetricsInput.disabled = isSubmitting;
        }

        if (submitButton) {
            submitButton.disabled = isSubmitting;
        }
    }

    function setAddServerError(message) {
        if (!addServerError) {
            return;
        }

        addServerError.textContent = message;
        addServerError.classList.toggle('active', Boolean(message));
    }

    function setServerInfoMessage(message) {
        if (!serverInfoContainer) {
            return;
        }

        serverInfoContainer.classList.remove('bm-grid');
        serverInfoContainer.innerHTML = `<p class="bm-empty-state">${escapeHtml(message)}</p>`;
    }

    function escapeHtml(value = '') {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Initial load
    loadServers();

    function getNavWrapper(tabId) {
        const navItem = document.querySelector(`.nav-item[data-tab="${tabId}"]`);
        return navItem ? navItem.closest('.nav-item-wrapper') : null;
    }

    function setWrapperExpansion(tabId, expanded) {
        const wrapper = getNavWrapper(tabId);
        if (!wrapper) {
            return;
        }

        if (expanded) {
            navItemWrappers.forEach(w => {
                if (w !== wrapper) {
                    w.classList.remove('expanded');
                }
            });
            wrapper.classList.add('expanded');
        } else {
            wrapper.classList.remove('expanded');
        }
    }
});

