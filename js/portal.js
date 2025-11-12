// Portal Navigation Functionality
document.addEventListener('DOMContentLoaded', async function() {
    const navItems = Array.from(document.querySelectorAll('.nav-item[data-tab]'));
    const navItemWrappers = Array.from(document.querySelectorAll('.nav-item-wrapper'));
    const tabContents = document.querySelectorAll('.tab-content');
    const pageTitle = document.getElementById('page-title');
    const serverInfoContainer = document.getElementById('portal-battlemetrics-grid');
    const ourServersTab = document.querySelector('#manage-content .sub-tab-content[data-sub-tab="our-servers"]');
	const announcementsTab = document.querySelector('#manage-content .sub-tab-content[data-sub-tab="server-announcements"]');
    const addServerError = document.getElementById('add-server-error');
	const manageAccessTab = document.getElementById('manage-access');
	const portalRoot = document.getElementById('portal-root');

	const API_ENDPOINTS = {
		list: 'getServers.php',
		add: 'saveServer.php',
		remove: 'deleteServer.php',
		listAnnouncements: 'getAnnouncements.php',
		addAnnouncement: 'saveAnnouncement.php',
		deleteAnnouncement: 'deleteAnnouncement.php',
		listUsers: 'listUsers.php',
		addUser: 'addUser.php',
		deactivateUser: 'deactivateUser.php',
		reactivateUser: 'reactivateUser.php',
		resetUserPassword: 'resetUserPassword.php',
		authCheck: 'auth_check.php'
	};

    let serversCache = [];
	let currentUser = null;

	// Enforce authentication and apply role-based visibility
	// Ensure portal content is hidden until auth is verified
	if (portalRoot) {
		portalRoot.style.display = 'none';
	}
	
	try {
		const authRes = await fetch(API_ENDPOINTS.authCheck, { 
			credentials: 'same-origin', 
			headers: { 'Accept': 'application/json' }, 
			cache: 'no-store' 
		});
		
		if (!authRes.ok) {
			// If auth endpoint doesn't exist or fails, redirect to login
			console.error('Auth check failed: HTTP', authRes.status);
			window.location.replace('portal_login.html');
			return;
		}
		
		const contentType = authRes.headers.get('content-type') || '';
		if (!contentType.includes('application/json')) {
			// Not JSON response, redirect to login
			console.error('Auth check failed: Invalid content type', contentType);
			window.location.replace('portal_login.html');
			return;
		}
		
		const auth = await authRes.json();
		if (!auth || auth.authenticated !== true) {
			// Not authenticated, redirect to login
			console.log('Not authenticated, redirecting to login');
			window.location.replace('portal_login.html');
			return;
		}
		
		// Authenticated - show portal
		currentUser = auth;
		applyRoleVisibility(auth);
		insertWelcome(auth);
		
		// Hide loading screen
		const loadingScreen = document.getElementById('auth-loading');
		if (loadingScreen) {
			loadingScreen.style.display = 'none';
		}
		
		if (portalRoot) {
			portalRoot.style.display = '';
		}
	} catch (e) {
		// Any error - redirect to login
		console.error('Auth check failed:', e);
		window.location.replace('portal_login.html');
		return;
	}

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
			{ id: 'navigation', label: 'Navigation' },
			{ id: 'server-announcements', label: 'Server Announcements' }
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

		// Initialize Manage Access UI when switching to that tab
		if (tabId === 'manage-access') {
			ensureManageAccessUI();
			loadUsers();
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

		// Initialize dynamic UIs on demand
		if (mainTabId === 'manage-content' && subTabId === 'server-announcements') {
			ensureAnnouncementsUI();
		}
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

	// =============================
	// Announcements Management UI
	// =============================
	function ensureAnnouncementsUI() {
		if (!announcementsTab) {
			return;
		}
		if (announcementsTab.dataset.announcementsInit === 'true') {
			return;
		}

		renderAnnouncementsUI();
		announcementsTab.dataset.announcementsInit = 'true';
	}

	function applyRoleVisibility(auth) {
		const role = (auth && auth.role || 'admin').toLowerCase();

		// Helper to hide a nav item and its tab content
		function hideTab(tabId) {
			const navItem = document.querySelector(`.nav-item[data-tab="${tabId}"]`);
			const wrapper = navItem ? navItem.closest('.nav-item-wrapper') : null;
			if (wrapper) {
				wrapper.style.display = 'none';
			}
			const tab = document.getElementById(tabId);
			if (tab) {
				tab.style.display = 'none';
			}
		}

		if (role === 'owner') {
			return; // no restrictions
		}

		if (role === 'admin') {
			// Hide Manage Access
			hideTab('manage-access');
			return;
		}

		if (role === 'staff') {
			// Hide Manage Access
			hideTab('manage-access');

			// Restrict Manage Site sub-tabs to only Server Announcements
			if (Array.isArray(subTabsConfig['manage-content'])) {
				subTabsConfig['manage-content'] = subTabsConfig['manage-content'].filter(st => st.id === 'server-announcements');
			}

			// Hide other top-level tabs if any (keep battlemetrics and manage-content)
			const allowedTop = new Set(['manage-content', 'battlemetrics']);
			document.querySelectorAll('.nav-item[data-tab]').forEach(item => {
				const tabId = item.getAttribute('data-tab');
				if (!allowedTop.has(tabId)) {
					const wrap = item.closest('.nav-item-wrapper');
					if (wrap) wrap.style.display = 'none';
					const content = document.getElementById(tabId);
					if (content) content.style.display = 'none';
				}
			});
		}
	}

	function insertWelcome(auth) {
		const navHeader = document.querySelector('.nav-header');
		if (!navHeader) return;
		const name = auth && auth.userName ? String(auth.userName) : '';
		if (!name) return;
		// Add or update a small welcome line
		let welcome = navHeader.querySelector('.welcome-text');
		if (!welcome) {
			welcome = document.createElement('div');
			welcome.className = 'welcome-text';
			welcome.style.color = '#b9c2d0';
			welcome.style.fontSize = '0.85rem';
			welcome.style.marginLeft = 'auto';
			navHeader.appendChild(welcome);
		}
		welcome.textContent = `Welcome ${name}`;
	}

	// =============================
	// Manage Access (Users)
	// =============================
	function ensureManageAccessUI() {
		if (!manageAccessTab) return;
		if (manageAccessTab.dataset.accountsInit === 'true') return;

		const viewBtn = manageAccessTab.querySelector('#view-accounts-btn');
		const addBtn = manageAccessTab.querySelector('#add-user-btn');
		const section = manageAccessTab.querySelector('#accounts-section');

		if (viewBtn) {
			viewBtn.addEventListener('click', () => {
				section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
				loadUsers();
			});
		}

		if (addBtn) {
			addBtn.addEventListener('click', openAddUserModal);
		}

		manageAccessTab.dataset.accountsInit = 'true';
	}

	async function loadUsers() {
		if (!manageAccessTab) return;
		const section = manageAccessTab.querySelector('#accounts-section');
		if (!section) return;

		section.innerHTML = '<p style="color: #cccccc;">Loading users...</p>';
		try {
			const response = await fetch(API_ENDPOINTS.listUsers, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
			if (!response.ok) {
				throw new Error(`Request failed with status ${response.status}`);
			}
			const users = await response.json();
			renderUsersList(Array.isArray(users) ? users : []);
		} catch (error) {
			console.error('Failed to load users:', error);
			section.innerHTML = '<p style="color:#ff6b6b;">Unable to load users.</p>';
		}
	}

	function renderUsersList(users) {
		if (!manageAccessTab) return;
		const section = manageAccessTab.querySelector('#accounts-section');
		if (!section) return;

		if (!Array.isArray(users) || users.length === 0) {
			section.innerHTML = '<p style="color: #cccccc;">No users found.</p>';
			return;
		}

		let html = '<div class="users-list">';
		users.forEach(u => {
			const id = Number(u.id) || 0;
			const name = escapeHtml(u.name || '');
			const role = escapeHtml(u.role || 'admin');
			const active = Number(u.is_active) === 1;
			const status = active ? 'Active' : 'Inactive';

			html += `
				<div class="user-row">
					<div class="user-main">
						<strong>${name}</strong>
						<span class="user-role">${role}</span>
						<span class="user-status">${status}</span>
					</div>
					<div class="user-actions">
						<button class="btn-secondary reset-pass" data-id="${id}">Reset Password</button>
						${active
							? `<button class="btn-danger deactivate-user" data-id="${id}">Deactivate</button>`
							: `<button class="btn-primary reactivate-user" data-id="${id}">Reactivate</button>`
						}
					</div>
				</div>
			`;
		});
		html += '</div>';

		section.innerHTML = html;

		section.querySelectorAll('.deactivate-user').forEach(btn => {
			btn.addEventListener('click', async () => {
				const id = parseInt(btn.getAttribute('data-id'), 10);
				if (Number.isNaN(id)) return;
				const confirmed = window.confirm('Deactivate this user?');
				if (!confirmed) return;
				try {
					await fetchJsonOk(API_ENDPOINTS.deactivateUser, { id });
					await loadUsers();
				} catch (err) {
					console.error('Failed to deactivate user:', err);
					alert(err?.message || 'Unable to deactivate user.');
				}
			});
		});

		section.querySelectorAll('.reactivate-user').forEach(btn => {
			btn.addEventListener('click', async () => {
				const id = parseInt(btn.getAttribute('data-id'), 10);
				if (Number.isNaN(id)) return;
				try {
					await fetchJsonOk(API_ENDPOINTS.reactivateUser, { id });
					await loadUsers();
				} catch (err) {
					console.error('Failed to reactivate user:', err);
					alert(err?.message || 'Unable to reactivate user.');
				}
			});
		});

		section.querySelectorAll('.reset-pass').forEach(btn => {
			btn.addEventListener('click', async () => {
				const id = parseInt(btn.getAttribute('data-id'), 10);
				if (Number.isNaN(id)) return;
				const newPass = window.prompt('Enter a new password:');
				if (!newPass) return;
				try {
					await fetchJsonOk(API_ENDPOINTS.resetUserPassword, { id, password: newPass });
					alert('Password updated.');
				} catch (err) {
					console.error('Failed to reset password:', err);
					alert(err?.message || 'Unable to reset password.');
				}
			});
		});
	}

	function openAddUserModal() {
		const modal = document.getElementById('add-user-modal');
		if (!modal) return;
		modal.classList.add('active');
	}

	// Add User modal behavior
	const addUserModal = document.getElementById('add-user-modal');
	const closeAddUserModalBtn = document.getElementById('close-add-user-modal');
	const cancelAddUserBtn = document.getElementById('cancel-add-user');
	const addUserForm = document.getElementById('add-user-form');
	const addUserError = document.getElementById('add-user-error');

	function closeAddUserModal() {
		if (!addUserModal) return;
		addUserModal.classList.remove('active');
		if (addUserForm) addUserForm.reset();
		if (addUserError) {
			addUserError.textContent = '';
			addUserError.classList.remove('active');
		}
	}

	if (closeAddUserModalBtn) {
		closeAddUserModalBtn.addEventListener('click', closeAddUserModal);
	}
	if (cancelAddUserBtn) {
		cancelAddUserBtn.addEventListener('click', closeAddUserModal);
	}
	if (addUserModal) {
		addUserModal.addEventListener('click', (e) => {
			if (e.target === addUserModal) {
				closeAddUserModal();
			}
		});
	}
	if (addUserForm) {
		addUserForm.addEventListener('submit', async (e) => {
			e.preventDefault();
			const nameEl = document.getElementById('user-name');
			const passEl = document.getElementById('user-password');
			const name = (nameEl?.value || '').trim();
			const password = passEl?.value || '';
			if (!name || !password) {
				if (addUserError) {
					addUserError.textContent = 'Username and password are required.';
					addUserError.classList.add('active');
				}
				return;
			}
			if (addUserError) {
				addUserError.textContent = '';
				addUserError.classList.remove('active');
			}

			setAddUserSubmitting(true);
			try {
				await fetchJsonOk(API_ENDPOINTS.addUser, { name, password });
				closeAddUserModal();
				await loadUsers();
			} catch (err) {
				console.error('Failed to add user:', err);
				if (addUserError) {
					addUserError.textContent = err?.message || 'Unable to add user.';
					addUserError.classList.add('active');
				}
			} finally {
				setAddUserSubmitting(false);
			}
		});
	}

	function setAddUserSubmitting(isSubmitting) {
		if (!addUserForm) return;
		addUserForm.querySelectorAll('input, button').forEach(el => {
			el.disabled = isSubmitting;
		});
	}

	async function fetchJsonOk(url, payload) {
		const response = await fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
			body: JSON.stringify(payload || {})
		});
		const result = await response.json().catch(() => ({}));
		if (!response.ok || result.success === false) {
			throw new Error(result.message || `Request failed (${response.status})`);
		}
		return result;
	}

	function renderAnnouncementsUI() {
		if (!announcementsTab) {
			return;
		}

		announcementsTab.innerHTML = `
			<div style="margin-bottom: 2rem;">
				<button class="btn-primary" type="button" id="add-announcement-btn">
					<i class="fas fa-plus"></i> New Announcement
				</button>
			</div>

			<div class="announce-list-wrapper">
				<h3 style="margin-bottom: 1rem; color: #ffffff;">Existing Announcements</h3>
				<div id="announcements-list"></div>
			</div>
		`;

		// Wire up button to open modal
		const addBtn = announcementsTab.querySelector('#add-announcement-btn');
		if (addBtn) {
			addBtn.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();
				openAnnouncementModal();
			});
		}

		// Load existing announcements
		loadAnnouncementsManagement();
	}

	function openAnnouncementModal() {
		let modal = document.getElementById('add-announcement-modal');

		// If the modal markup is missing for any reason, create it on the fly
		if (!modal) {
			modal = createAnnouncementModal();
		}

		// Populate server dropdown
		const serverSelect = document.getElementById('announcement-server');
		if (serverSelect && Array.isArray(serversCache)) {
			serverSelect.innerHTML = '<option value="">All Servers</option>';
			serversCache.forEach(s => {
				const option = document.createElement('option');
				option.value = Number(s.id) || 0;
				option.textContent = escapeHtml(s.display_name || s.displayName || 'Server');
				serverSelect.appendChild(option);
			});
		}

		// Reset form
		const form = document.getElementById('add-announcement-form');
		if (form) {
			form.reset();
		}

		// Clear error
		const errorEl = document.getElementById('add-announcement-error');
		if (errorEl) {
			errorEl.textContent = '';
			errorEl.classList.remove('active');
		}

		// Show modal
		modal.classList.add('active');
		// Hard fallback in case CSS isn't applied yet (e.g. caching or dev server quirks)
		modal.style.display = 'flex';
	}

	function closeAnnouncementModal() {
		const modal = document.getElementById('add-announcement-modal');
		if (modal) {
			modal.classList.remove('active');
			modal.style.display = ''; // revert any hard fallback
		}
		const form = document.getElementById('add-announcement-form');
		if (form) {
			form.reset();
		}
		const errorEl = document.getElementById('add-announcement-error');
		if (errorEl) {
			errorEl.textContent = '';
			errorEl.classList.remove('active');
		}
	}

	function setupAnnouncementModal() {
		const modal = document.getElementById('add-announcement-modal');
		const form = document.getElementById('add-announcement-form');
		const closeBtn = document.getElementById('close-add-announcement-modal');
		const cancelBtn = document.getElementById('cancel-add-announcement');

		if (!modal || !form) return;

		// Close modal handlers
		if (closeBtn) {
			closeBtn.addEventListener('click', closeAnnouncementModal);
		}
		if (cancelBtn) {
			cancelBtn.addEventListener('click', closeAnnouncementModal);
		}

		// Close on overlay click
		modal.addEventListener('click', (e) => {
			if (e.target === modal) {
				closeAnnouncementModal();
			}
		});

		// Form submission
		form.addEventListener('submit', async (e) => {
			e.preventDefault();

			const messageEl = document.getElementById('announcement-message');
			const severityEl = document.getElementById('announcement-severity');
			const serverEl = document.getElementById('announcement-server');
			const startEl = document.getElementById('announcement-start');
			const endEl = document.getElementById('announcement-end');

			const payload = {
				message: (messageEl?.value || '').trim(),
				severity: (severityEl?.value || 'info'),
				serverId: (serverEl?.value || '') === '' ? null : Number(serverEl.value),
				startsAt: startEl?.value || '',
				endsAt: endEl?.value || '',
				isActive: 1
			};

			if (!payload.message) {
				return setAnnouncementError('Message is required.');
			}

			setAnnouncementError('');
			setAnnouncementFormSubmitting(true);
			try {
				await addAnnouncement(payload);
				closeAnnouncementModal();
				await loadAnnouncementsManagement();
			} catch (err) {
				console.error('Failed to save announcement:', err);
				setAnnouncementError(err?.message || 'Unable to save announcement.');
			} finally {
				setAnnouncementFormSubmitting(false);
			}
		});
	}

	// Creates the announcement modal dynamically when missing
	function createAnnouncementModal() {
		const overlay = document.createElement('div');
		overlay.className = 'modal-overlay active';
		overlay.id = 'add-announcement-modal';
		overlay.style.display = 'flex'; // ensure visible even if CSS isn't loaded yet

		overlay.innerHTML = `
			<div class="modal-content">
				<div class="modal-header">
					<h2>New Announcement</h2>
					<button class="modal-close" id="close-add-announcement-modal">
						<i class="fas fa-times"></i>
					</button>
				</div>
				<div class="modal-body">
					<form id="add-announcement-form">
						<div class="form-group">
							<label for="announcement-message">Message</label>
							<textarea id="announcement-message" class="form-input" rows="3" placeholder="Type announcement message..." required></textarea>
						</div>

						<div class="form-row">
							<div class="form-group">
								<label for="announcement-severity">Severity</label>
								<select id="announcement-severity" class="form-input">
									<option value="info">Info</option>
									<option value="success">Success</option>
									<option value="warning">Warning</option>
									<option value="error">Error</option>
								</select>
							</div>
							<div class="form-group">
								<label for="announcement-server">Target Server</label>
								<select id="announcement-server" class="form-input">
									<option value="">All Servers</option>
								</select>
							</div>
						</div>

						<div class="form-row">
							<div class="form-group">
								<label for="announcement-start">Starts At (optional)</label>
								<input type="datetime-local" id="announcement-start" class="form-input" />
							</div>
							<div class="form-group">
								<label for="announcement-end">Ends At (optional)</label>
								<input type="datetime-local" id="announcement-end" class="form-input" />
							</div>
						</div>

						<p class="form-error" id="add-announcement-error" role="alert" aria-live="assertive"></p>

						<div class="modal-actions">
							<button type="button" class="btn-secondary" id="cancel-add-announcement">Cancel</button>
							<button type="submit" class="btn-primary">Create Announcement</button>
						</div>
					</form>
				</div>
			</div>
		`;

		document.body.appendChild(overlay);
		// Wire up handlers for the newly created DOM
		setupAnnouncementModal();
		return overlay;
	}

	function setAnnouncementFormSubmitting(isSubmitting) {
		const form = document.getElementById('add-announcement-form');
		if (!form) return;
		form.querySelectorAll('input, textarea, select, button').forEach(el => {
			el.disabled = isSubmitting;
		});
	}

	function setAnnouncementError(message) {
		const errorEl = document.getElementById('add-announcement-error');
		if (!errorEl) return;
		errorEl.textContent = message || '';
		errorEl.classList.toggle('active', Boolean(message));
	}

	async function loadAnnouncementsManagement() {
		if (!announcementsTab) return;
		const listContainer = announcementsTab.querySelector('#announcements-list');
		if (!listContainer) return;

		listContainer.innerHTML = '<p style="color: #cccccc;">Loading announcements...</p>';

		try {
			const response = await fetch(API_ENDPOINTS.listAnnouncements + '?active=0', {
				headers: { 'Accept': 'application/json' },
				cache: 'no-store'
			});
			if (!response.ok) {
				throw new Error(`Request failed with status ${response.status}`);
			}
			const data = await response.json();
			renderAnnouncementsList(Array.isArray(data) ? data : []);
		} catch (error) {
			console.error('Failed to load announcements:', error);
			listContainer.innerHTML = '<p style="color: #ff6b6b;">Unable to load announcements.</p>';
		}
	}

	function renderAnnouncementsList(announcements) {
		if (!announcementsTab) return;
		const listContainer = announcementsTab.querySelector('#announcements-list');
		if (!listContainer) return;

		if (!Array.isArray(announcements) || announcements.length === 0) {
			listContainer.innerHTML = '<p style="color: #cccccc;">No announcements yet.</p>';
			return;
		}

		let html = '<div class="announcements-list">';
		announcements.forEach(a => {
			const id = Number(a.id) || 0;
			const serverName = escapeHtml(a.server_name || 'All Servers');
			const severity = escapeHtml(a.severity || 'info');
			const msg = escapeHtml(a.message || '');
			const starts = a.starts_at ? escapeHtml(a.starts_at) : 'Immediate';
			const ends = a.ends_at ? escapeHtml(a.ends_at) : 'Open-ended';
			const active = Number(a.is_active) === 1 ? 'Active' : 'Inactive';

			html += `
				<div class="announcement-card">
					<div class="announcement-main">
						<div class="announcement-header">
							<span class="badge badge-${severity}">${severity.toUpperCase()}</span>
							<span class="announcement-target">${serverName}</span>
							<span class="announcement-status">${active}</span>
						</div>
						<p class="announcement-message">${msg}</p>
						<div class="announcement-schedule">
							<span><strong>Starts:</strong> ${starts}</span>
							<span><strong>Ends:</strong> ${ends}</span>
						</div>
					</div>
					<div class="announcement-actions">
						<button class="btn-danger delete-announcement" data-id="${id}">Delete</button>
					</div>
				</div>
			`;
		});
		html += '</div>';

		listContainer.innerHTML = html;

		listContainer.querySelectorAll('.delete-announcement').forEach(btn => {
			btn.addEventListener('click', async () => {
				const id = parseInt(btn.getAttribute('data-id'), 10);
				if (Number.isNaN(id)) return;
				const confirmed = window.confirm('Delete this announcement?');
				if (!confirmed) return;
				try {
					await deleteAnnouncement(id);
					await loadAnnouncementsManagement();
				} catch (err) {
					console.error('Failed to delete announcement:', err);
					alert(err?.message || 'Unable to delete announcement.');
				}
			});
		});
	}

	async function addAnnouncement(payload) {
		const response = await fetch(API_ENDPOINTS.addAnnouncement, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
			body: JSON.stringify(payload)
		});
		const result = await response.json().catch(() => ({}));
		if (!response.ok || !result.success) {
			throw new Error(result.message || 'Failed to save announcement.');
		}
		return result.announcement || null;
	}

	async function deleteAnnouncement(id) {
		const response = await fetch(API_ENDPOINTS.deleteAnnouncement, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
			body: JSON.stringify({ id })
		});
		const result = await response.json().catch(() => ({}));
		if (!response.ok || !result.success) {
			throw new Error(result.message || 'Failed to delete announcement.');
		}
		return true;
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
	
	// Setup announcement modal once on page load
	setupAnnouncementModal();

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

