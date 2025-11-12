/**
 * OPR Portal Admin Dashboard
 * Clean, modular architecture for server/announcement/user management
 */

// ============================================================================
// API Layer - All endpoints and HTTP communication
// ============================================================================

const API = {
  endpoints: {
    authCheck: 'auth_check.php',
    listServers: 'getServers.php',
    saveServer: 'saveServer.php',
    deleteServer: 'deleteServer.php',
    listAnnouncements: 'getAnnouncements.php',
    saveAnnouncement: 'saveAnnouncement.php',
    deleteAnnouncement: 'deleteAnnouncement.php',
    listUsers: 'listUsers.php',
    addUser: 'addUser.php',
    deactivateUser: 'deactivateUser.php',
    reactivateUser: 'reactivateUser.php',
    resetUserPassword: 'resetUserPassword.php'
  },

  async fetch(endpoint, options = {}) {
    const { method = 'GET', body = null, headers = {} } = options;
    const fetchOpts = {
      method,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', ...headers },
      cache: 'no-store'
    };

    if (body) {
      fetchOpts.headers['Content-Type'] = 'application/json';
      fetchOpts.body = JSON.stringify(body);
    }

    const response = await fetch(endpoint, fetchOpts);
    const contentType = response.headers.get('content-type') || '';

    if (!contentType.includes('application/json')) {
      throw new Error(`Invalid content-type: ${contentType}`);
    }

    const result = await response.json().catch(() => ({}));

    if (!response.ok || result.success === false) {
      throw new Error(result.message || `HTTP ${response.status}`);
    }

    return result;
  },

  checkAuth: () => API.fetch(API.endpoints.authCheck),
  listServers: () => API.fetch(API.endpoints.listServers),
  saveServer: (battlemetricsId) => API.fetch(API.endpoints.saveServer, {
    method: 'POST',
    body: { battlemetricsId }
  }),
  deleteServer: (id) => API.fetch(API.endpoints.deleteServer, {
    method: 'POST',
    body: { id }
  }),
  listAnnouncements: (active = 0) => API.fetch(`${API.endpoints.listAnnouncements}?active=${active}`),
  saveAnnouncement: (payload) => API.fetch(API.endpoints.saveAnnouncement, {
    method: 'POST',
    body: payload
  }),
  deleteAnnouncement: (id) => API.fetch(API.endpoints.deleteAnnouncement, {
    method: 'POST',
    body: { id }
  }),
  listUsers: () => API.fetch(API.endpoints.listUsers),
  addUser: (name, password) => API.fetch(API.endpoints.addUser, {
    method: 'POST',
    body: { name, password }
  }),
  deactivateUser: (id) => API.fetch(API.endpoints.deactivateUser, {
    method: 'POST',
    body: { id }
  }),
  reactivateUser: (id) => API.fetch(API.endpoints.reactivateUser, {
    method: 'POST',
    body: { id }
  }),
  resetUserPassword: (id, password) => API.fetch(API.endpoints.resetUserPassword, {
    method: 'POST',
    body: { id, password }
  })
};

// ============================================================================
// Utility Functions
// ============================================================================

const Utils = {
  escapeHtml(str = '') {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(str).replace(/[&<>"']/g, (m) => map[m]);
  },

  normalizeServer(server) {
    return {
      id: server.id,
      battlemetricsId: server.battlemetrics_id || '',
      displayName: server.display_name || '',
      gameTitle: server.game_title || '',
      region: server.region || ''
    };
  },

  getElement(selector) {
    const el = document.querySelector(selector);
    if (!el) console.warn(`Element not found: ${selector}`);
    return el;
  },

  getAllElements(selector) {
    return Array.from(document.querySelectorAll(selector));
  },

  setLoading(el, isLoading) {
    if (el) {
      el.disabled = isLoading;
      el.style.opacity = isLoading ? '0.6' : '1';
    }
  },

  show(el) {
    if (el) el.style.display = '';
  },

  hide(el) {
    if (el) el.style.display = 'none';
  }
};

// ============================================================================
// State Management
// ============================================================================

const State = {
  currentUser: null,
  servers: [],
  
  setCurrentUser(user) {
    this.currentUser = user;
  },

  setServers(servers) {
    this.servers = Array.isArray(servers) ? servers : [];
  },

  getRole() {
    return this.currentUser?.role || 'admin';
  }
};

// ============================================================================
// Authentication & Authorization
// ============================================================================

const Auth = {
  async check() {
    try {
      const auth = await API.checkAuth();
      if (!auth.authenticated) {
        console.log('Not authenticated');
        window.location.replace('portal_login.html');
        return null;
      }
      State.setCurrentUser(auth);
      return auth;
    } catch (error) {
      console.error('Auth check failed:', error);
      window.location.replace('portal_login.html');
      return null;
    }
  },

  applyRoleVisibility(role) {
    const isOwner = role === 'owner';
    const isAdmin = role === 'admin';
    const isStaff = role === 'staff';

    // Helper to hide tab and nav item
    const hideTab = (tabId) => {
      const navItem = Utils.getElement(`.nav-item[data-tab="${tabId}"]`);
      const wrapper = navItem?.closest('.nav-item-wrapper');
      if (wrapper) Utils.hide(wrapper);
      const tab = Utils.getElement(`#${tabId}`);
      if (tab) Utils.hide(tab);
    };

    // Owner: no restrictions
    if (isOwner) return;

    // Admin: hide Manage Access
    if (isAdmin) {
      hideTab('manage-access');
      return;
    }

    // Staff: only Server Announcements
    if (isStaff) {
      hideTab('manage-access');
      const manageContent = Utils.getElement('#manage-content');
      if (manageContent) {
        const otherTabs = manageContent.querySelectorAll('[data-sub-tab]:not([data-sub-tab="server-announcements"])');
        otherTabs.forEach(tab => Utils.hide(tab));
      }
      Utils.getAllElements('.nav-item[data-tab]').forEach(item => {
        if (!['manage-content', 'battlemetrics'].includes(item.dataset.tab)) {
          Utils.hide(item.closest('.nav-item-wrapper'));
          Utils.hide(Utils.getElement(`#${item.dataset.tab}`));
        }
      });
    }
  },

  insertWelcome(user) {
    const navHeader = Utils.getElement('.nav-header');
    if (!navHeader || !user?.userName) return;

    let welcome = navHeader.querySelector('.welcome-text');
    if (!welcome) {
      welcome = document.createElement('div');
      welcome.className = 'welcome-text';
      welcome.style.cssText = 'color: #b9c2d0; font-size: 0.85rem; margin-left: auto;';
      navHeader.appendChild(welcome);
    }
    welcome.textContent = `Welcome ${user.userName}`;
  }
};

// ============================================================================
// Navigation Manager
// ============================================================================

const Nav = {
  tabs: new Map(),
  subTabs: new Map(),

  init() {
    const navItems = Utils.getAllElements('.nav-item[data-tab]');
    const tabContents = Utils.getAllElements('.tab-content');

    navItems.forEach(item => {
      const tabId = item.dataset.tab;
      this.tabs.set(tabId, { navItem: item, wrapper: item.closest('.nav-item-wrapper') });
    });

    tabContents.forEach(content => {
      const tabId = content.id;
      if (!this.tabs.has(tabId)) {
        this.tabs.set(tabId, { navItem: null, content });
      } else {
        this.tabs.get(tabId).content = content;
      }
    });

    // Build sub-tabs for manage-content
    this.buildSubTabs('manage-content', [
      { id: 'slideshow', label: 'Slideshow' },
      { id: 'server-info', label: 'Server Info' },
      { id: 'our-servers', label: 'Our Servers' },
      { id: 'footer', label: 'Footer' },
      { id: 'navigation', label: 'Navigation' },
      { id: 'server-announcements', label: 'Server Announcements' }
    ]);

    this.wireNavigation();
  },

  buildSubTabs(parentTabId, subTabs) {
    const subNav = Utils.getElement(`.sub-nav[data-parent-tab="${parentTabId}"]`);
    if (!subNav) return;

    subNav.innerHTML = '';
    subTabs.forEach(sub => {
      const li = document.createElement('li');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sub-nav-item';
      btn.textContent = sub.label;
      btn.dataset.parentTab = parentTabId;
      btn.dataset.subTab = sub.id;
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        this.selectTab(parentTabId, true);
        this.selectSubTab(parentTabId, sub.id);
      });
      li.appendChild(btn);
      subNav.appendChild(li);
    });

    this.subTabs.set(parentTabId, subTabs);
  },

  wireNavigation() {
    Utils.getAllElements('.nav-item[data-tab]').forEach(item => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        const tabId = item.dataset.tab;
        const hasSubNav = Boolean(this.subTabs.has(tabId));

        if (hasSubNav) {
          const wrapper = item.closest('.nav-item-wrapper');
          const isExpanded = wrapper?.classList.contains('expanded');
          if (isExpanded) {
            wrapper.classList.remove('expanded');
            return;
          }
          this.selectTab(tabId, true);
          return;
        }

        this.selectTab(tabId, false);
      });
    });

    // Initialize first tab
    const firstItem = Utils.getElement('.nav-item.active[data-tab]') || Utils.getAllElements('.nav-item[data-tab]')[0];
    if (firstItem) {
      const tabId = firstItem.dataset.tab;
      const hasSubNav = this.subTabs.has(tabId);
      if (hasSubNav) {
        this.selectTab(tabId, true);
        const firstSub = this.subTabs.get(tabId)[0];
        if (firstSub) this.selectSubTab(tabId, firstSub.id);
      } else {
        this.selectTab(tabId, false);
      }
    }
  },

  selectTab(tabId, expand = false) {
    // Deselect all
    Utils.getAllElements('.nav-item[data-tab]').forEach(item => {
      item.classList.remove('active');
    });
    Utils.getAllElements('.tab-content').forEach(content => {
      content.classList.remove('active');
    });

    // Collapse all wrappers
    Utils.getAllElements('.nav-item-wrapper').forEach(w => {
      w.classList.remove('expanded');
    });

    // Select this tab
    const tab = this.tabs.get(tabId);
    if (tab?.navItem) tab.navItem.classList.add('active');
    if (tab?.content) tab.content.classList.add('active');
    if (tab?.wrapper && expand) tab.wrapper.classList.add('expanded');

    // Update page title
    const titles = {
      'dashboard': 'Dashboard', 'server-control': 'Server Control', 'manage-content': 'Manage Site',
      'manage-access': 'Manage Access', 'players': 'Players', 'settings': 'Settings',
      'logs': 'Logs', 'battlemetrics': 'BattleMetrics'
    };
    const pageTitle = Utils.getElement('#page-title');
    if (pageTitle) pageTitle.textContent = titles[tabId] || tabId;

    // Initialize tab if needed
    if (tabId === 'manage-access') Managers.manageAccess.init();
  },

  selectSubTab(mainTabId, subTabId) {
    const mainTab = Utils.getElement(`#${mainTabId}`);
    if (!mainTab) return;

    // Deselect all sub-tabs
    mainTab.querySelectorAll('.sub-tab-content').forEach(c => c.classList.remove('active'));
    Utils.getAllElements(`.sub-nav[data-parent-tab="${mainTabId}"] .sub-nav-item`).forEach(b => {
      b.classList.remove('active');
    });

    // Select this sub-tab
    const subContent = mainTab.querySelector(`[data-sub-tab="${subTabId}"]`);
    if (subContent) subContent.classList.add('active');

    const subBtn = Utils.getElement(`.sub-nav[data-parent-tab="${mainTabId}"] .sub-nav-item[data-sub-tab="${subTabId}"]`);
    if (subBtn) subBtn.classList.add('active');

    // Initialize sub-tab if needed
    if (mainTabId === 'manage-content' && subTabId === 'our-servers') {
      Managers.servers.init();
    } else if (mainTabId === 'manage-content' && subTabId === 'server-announcements') {
      Managers.announcements.init();
    }
  }
};

// ============================================================================
// Server Manager
// ============================================================================

const Managers = {
  servers: {
    container: null,
    tabEl: null,

    init() {
      this.container = Utils.getElement('#portal-battlemetrics-grid');
      this.tabEl = Utils.getElement('#manage-content .sub-tab-content[data-sub-tab="our-servers"]');
      if (!this.container || !this.tabEl || this.tabEl.dataset.initialized) return;
      
      this.tabEl.dataset.initialized = 'true';
      this.load();
      this.wireAddServerButton();
    },

    wireAddServerButton() {
      const btn = Utils.getElement('#add-server-btn');
      if (!btn) return;

      btn.addEventListener('click', () => this.openModal());

      const modal = Utils.getElement('#add-server-modal');
      const form = Utils.getElement('#add-server-form');
      const closeBtn = Utils.getElement('#close-modal');
      const cancelBtn = Utils.getElement('#cancel-add-server');

      if (closeBtn) closeBtn.addEventListener('click', () => this.closeModal());
      if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeModal());
      if (modal) {
        modal.addEventListener('click', (e) => {
          if (e.target === modal) this.closeModal();
        });
      }

      if (form) {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          await this.handleFormSubmit();
        });
      }
    },

    openModal() {
      const modal = Utils.getElement('#add-server-modal');
      if (modal) modal.classList.add('active');
    },

    closeModal() {
      const modal = Utils.getElement('#add-server-modal');
      if (modal) modal.classList.remove('active');
      const form = Utils.getElement('#add-server-form');
      if (form) form.reset();
      this.clearError();
    },

    async handleFormSubmit() {
      const input = Utils.getElement('#battlemetrics-id');
      const bmId = input?.value?.trim() || '';

      if (!bmId) {
        this.setError('BattleMetrics ID is required.');
        input?.focus();
        return;
      }

      this.clearError();
      const btn = Utils.getElement('#add-server-form button[type="submit"]');
      Utils.setLoading(btn, true);

      try {
        await API.saveServer(bmId);
        this.closeModal();
        await this.load();
      } catch (error) {
        console.error('Failed to add server:', error);
        this.setError(error.message);
      } finally {
        Utils.setLoading(btn, false);
      }
    },

    async load() {
      if (!this.container || !this.tabEl) return;

      this.container.innerHTML = '<p class="bm-empty-state">Loading servers...</p>';

      try {
        const data = await API.listServers();
        State.setServers(data);
        this.renderList(data);
        this.renderCards(data);
      } catch (error) {
        console.error('Failed to load servers:', error);
        this.container.innerHTML = '<p class="bm-empty-state">Unable to load servers.</p>';
      }
    },

    renderList(servers) {
      if (!this.tabEl) return;

      if (!Array.isArray(servers) || servers.length === 0) {
        this.tabEl.innerHTML = '<p style="color: #cccccc;">No servers configured yet.</p>';
        return;
      }

      let html = '<div class="servers-list">';
      servers.forEach(s => {
        html += `
          <div class="server-card">
            <h3>${Utils.escapeHtml(s.display_name || 'Unknown')}</h3>
            <p><strong>BattleMetrics ID:</strong> ${Utils.escapeHtml(s.battlemetrics_id || 'N/A')}</p>
            <button class="btn-danger delete-server" data-id="${s.id}">Delete</button>
          </div>
        `;
      });
      html += '</div>';
      this.tabEl.innerHTML = html;

      this.tabEl.querySelectorAll('.delete-server').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = parseInt(btn.dataset.id, 10);
          if (!Number.isNaN(id)) await this.handleDelete(id);
        });
      });
    },

    renderCards(servers) {
      if (!this.container || !window.Battlemetrics) return;
      const normalized = servers.map(Utils.normalizeServer);
      window.Battlemetrics.renderCards(this.container, normalized);
    },

    async handleDelete(id) {
      if (!window.confirm('Remove this server from the portal?')) return;

      try {
        await API.deleteServer(id);
        await this.load();
      } catch (error) {
        console.error('Failed to delete server:', error);
        alert(error.message);
      }
    },

    setError(msg) {
      const el = Utils.getElement('#add-server-error');
      if (el) {
        el.textContent = msg;
        el.classList.add('active');
      }
    },

    clearError() {
      const el = Utils.getElement('#add-server-error');
      if (el) {
        el.textContent = '';
        el.classList.remove('active');
      }
    }
  },

  // ========================================================================
  // Announcements Manager
  // ========================================================================

  announcements: {
    tabEl: null,

    init() {
      this.tabEl = Utils.getElement('#manage-content .sub-tab-content[data-sub-tab="server-announcements"]');
      if (!this.tabEl || this.tabEl.dataset.initialized) return;

      this.tabEl.dataset.initialized = 'true';
      this.render();
    },

    render() {
      if (!this.tabEl) return;

      this.tabEl.innerHTML = `
        <div style="margin-bottom: 2rem;">
          <button class="btn-primary" id="announcement-add-btn" type="button">
            <i class="fas fa-plus"></i> New Announcement
          </button>
        </div>
        <div class="announce-list-wrapper">
          <h3 style="margin-bottom: 1rem; color: #ffffff;">Existing Announcements</h3>
          <div id="announcements-list"></div>
        </div>
      `;

      const addBtn = this.tabEl.querySelector('#announcement-add-btn');
      if (addBtn) addBtn.addEventListener('click', () => this.openModal());

      this.load();
    },

    openModal() {
      let modal = Utils.getElement('#add-announcement-modal');
      if (!modal) modal = this.createModal();

      const serverSelect = Utils.getElement('#announcement-server');
      if (serverSelect) {
        serverSelect.innerHTML = '<option value="">All Servers</option>';
        State.servers.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.id;
          opt.textContent = Utils.escapeHtml(s.display_name || 'Server');
          serverSelect.appendChild(opt);
        });
      }

      const form = Utils.getElement('#add-announcement-form');
      if (form) form.reset();

      this.clearError();
      modal.classList.add('active');
    },

    closeModal() {
      const modal = Utils.getElement('#add-announcement-modal');
      if (modal) modal.classList.remove('active');
      const form = Utils.getElement('#add-announcement-form');
      if (form) form.reset();
      this.clearError();
    },

    createModal() {
      const modal = document.createElement('div');
      modal.className = 'modal-overlay';
      modal.id = 'add-announcement-modal';
      modal.innerHTML = `
        <div class="modal-content">
          <div class="modal-header">
            <h2>New Announcement</h2>
            <button class="modal-close" type="button" id="close-add-announcement-modal">
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

      document.body.appendChild(modal);

      const closeBtn = modal.querySelector('#close-add-announcement-modal');
      const cancelBtn = modal.querySelector('#cancel-add-announcement');
      const form = modal.querySelector('#add-announcement-form');

      if (closeBtn) closeBtn.addEventListener('click', () => this.closeModal());
      if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeModal());
      if (form) {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          await this.handleFormSubmit();
        });
      }

      modal.addEventListener('click', (e) => {
        if (e.target === modal) this.closeModal();
      });

      return modal;
    },

    async handleFormSubmit() {
      const msgEl = Utils.getElement('#announcement-message');
      const msg = msgEl?.value?.trim() || '';

      if (!msg) {
        this.setError('Message is required.');
        return;
      }

      this.clearError();
      const btn = Utils.getElement('#add-announcement-form button[type="submit"]');
      Utils.setLoading(btn, true);

      try {
        const payload = {
          message: msg,
          severity: Utils.getElement('#announcement-severity')?.value || 'info',
          serverId: Utils.getElement('#announcement-server')?.value || null,
          startsAt: Utils.getElement('#announcement-start')?.value || '',
          endsAt: Utils.getElement('#announcement-end')?.value || '',
          isActive: 1
        };

        await API.saveAnnouncement(payload);
        this.closeModal();
        await this.load();
      } catch (error) {
        console.error('Failed to save announcement:', error);
        this.setError(error.message);
      } finally {
        Utils.setLoading(btn, false);
      }
    },

    async load() {
      const listEl = Utils.getElement('#announcements-list');
      if (!listEl) return;

      listEl.innerHTML = '<p style="color: #cccccc;">Loading announcements...</p>';

      try {
        const data = await API.listAnnouncements(0);
        this.renderList(Array.isArray(data) ? data : []);
      } catch (error) {
        console.error('Failed to load announcements:', error);
        listEl.innerHTML = '<p style="color: #ff6b6b;">Unable to load announcements.</p>';
      }
    },

    renderList(announcements) {
      const listEl = Utils.getElement('#announcements-list');
      if (!listEl) return;

      if (announcements.length === 0) {
        listEl.innerHTML = '<p style="color: #cccccc;">No announcements yet.</p>';
        return;
      }

      let html = '<div class="announcements-list">';
      announcements.forEach(a => {
        html += `
          <div class="announcement-card">
            <div class="announcement-main">
              <div class="announcement-header">
                <span class="badge badge-${a.severity}">${a.severity.toUpperCase()}</span>
                <span class="announcement-target">${Utils.escapeHtml(a.server_name || 'All Servers')}</span>
                <span class="announcement-status">${Number(a.is_active) === 1 ? 'Active' : 'Inactive'}</span>
              </div>
              <p class="announcement-message">${Utils.escapeHtml(a.message || '')}</p>
              <div class="announcement-schedule">
                <span><strong>Starts:</strong> ${a.starts_at ? Utils.escapeHtml(a.starts_at) : 'Immediate'}</span>
                <span><strong>Ends:</strong> ${a.ends_at ? Utils.escapeHtml(a.ends_at) : 'Open-ended'}</span>
              </div>
            </div>
            <div class="announcement-actions">
              <button class="btn-danger delete-announcement" data-id="${a.id}">Delete</button>
            </div>
          </div>
        `;
      });
      html += '</div>';
      listEl.innerHTML = html;

      listEl.querySelectorAll('.delete-announcement').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = parseInt(btn.dataset.id, 10);
          if (!Number.isNaN(id)) await this.handleDelete(id);
        });
      });
    },

    async handleDelete(id) {
      if (!window.confirm('Delete this announcement?')) return;

      try {
        await API.deleteAnnouncement(id);
        await this.load();
      } catch (error) {
        console.error('Failed to delete announcement:', error);
        alert(error.message);
      }
    },

    setError(msg) {
      const el = Utils.getElement('#add-announcement-error');
      if (el) {
        el.textContent = msg;
        el.classList.add('active');
      }
    },

    clearError() {
      const el = Utils.getElement('#add-announcement-error');
      if (el) {
        el.textContent = '';
        el.classList.remove('active');
      }
    }
  },

  // ========================================================================
  // User Management (Manage Access)
  // ========================================================================

  manageAccess: {
    tabEl: null,

    init() {
      this.tabEl = Utils.getElement('#manage-access');
      if (!this.tabEl || this.tabEl.dataset.initialized) return;

      this.tabEl.dataset.initialized = 'true';
      this.wireButtons();
      this.setupAddUserModal();
      this.load();
    },

    wireButtons() {
      const viewBtn = this.tabEl?.querySelector('#view-accounts-btn');
      const addBtn = this.tabEl?.querySelector('#add-user-btn');

      if (viewBtn) {
        viewBtn.addEventListener('click', () => {
          this.tabEl?.querySelector('#accounts-section')?.scrollIntoView({ behavior: 'smooth' });
          this.load();
        });
      }

      if (addBtn) {
        addBtn.addEventListener('click', () => this.openAddUserModal());
      }
    },

    setupAddUserModal() {
      const modal = Utils.getElement('#add-user-modal');
      const form = Utils.getElement('#add-user-form');
      const closeBtn = Utils.getElement('#close-add-user-modal');
      const cancelBtn = Utils.getElement('#cancel-add-user');

      if (closeBtn) closeBtn.addEventListener('click', () => this.closeAddUserModal());
      if (cancelBtn) cancelBtn.addEventListener('click', () => this.closeAddUserModal());

      if (modal) {
        modal.addEventListener('click', (e) => {
          if (e.target === modal) this.closeAddUserModal();
        });
      }

      if (form) {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();
          await this.handleAddUserSubmit();
        });
      }
    },

    openAddUserModal() {
      const modal = Utils.getElement('#add-user-modal');
      if (modal) modal.classList.add('active');
    },

    closeAddUserModal() {
      const modal = Utils.getElement('#add-user-modal');
      if (modal) modal.classList.remove('active');
      const form = Utils.getElement('#add-user-form');
      if (form) form.reset();
      this.clearAddUserError();
    },

    async handleAddUserSubmit() {
      const nameEl = Utils.getElement('#user-name');
      const passEl = Utils.getElement('#user-password');
      const name = nameEl?.value?.trim() || '';
      const password = passEl?.value || '';

      if (!name || !password) {
        this.setAddUserError('Username and password are required.');
        return;
      }

      this.clearAddUserError();
      const btn = Utils.getElement('#add-user-form button[type="submit"]');
      Utils.setLoading(btn, true);

      try {
        await API.addUser(name, password);
        this.closeAddUserModal();
        await this.load();
      } catch (error) {
        console.error('Failed to add user:', error);
        this.setAddUserError(error.message);
      } finally {
        Utils.setLoading(btn, false);
      }
    },

    async load() {
      const section = this.tabEl?.querySelector('#accounts-section');
      if (!section) return;

      section.innerHTML = '<p style="color: #cccccc;">Loading users...</p>';

      try {
        const data = await API.listUsers();
        this.renderUsers(Array.isArray(data) ? data : []);
      } catch (error) {
        console.error('Failed to load users:', error);
        section.innerHTML = '<p style="color: #ff6b6b;">Unable to load users.</p>';
      }
    },

    renderUsers(users) {
      const section = this.tabEl?.querySelector('#accounts-section');
      if (!section) return;

      if (users.length === 0) {
        section.innerHTML = '<p style="color: #cccccc;">No users found.</p>';
        return;
      }

      let html = '<div class="users-list">';
      users.forEach(u => {
        const active = Number(u.is_active) === 1;
        html += `
          <div class="user-row">
            <div class="user-main">
              <strong>${Utils.escapeHtml(u.name || '')}</strong>
              <span class="user-role">${Utils.escapeHtml(u.role || 'admin')}</span>
              <span class="user-status">${active ? 'Active' : 'Inactive'}</span>
            </div>
            <div class="user-actions">
              <button class="btn-secondary reset-pass" data-id="${u.id}">Reset Password</button>
              ${active ? `<button class="btn-danger deactivate-user" data-id="${u.id}">Deactivate</button>` : `<button class="btn-primary reactivate-user" data-id="${u.id}">Reactivate</button>`}
            </div>
          </div>
        `;
      });
      html += '</div>';
      section.innerHTML = html;

      // Wire up action buttons
      section.querySelectorAll('.reset-pass').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = parseInt(btn.dataset.id, 10);
          const newPass = window.prompt('Enter new password:');
          if (!newPass) return;
          try {
            await API.resetUserPassword(id, newPass);
            alert('Password updated.');
          } catch (error) {
            alert(error.message);
          }
        });
      });

      section.querySelectorAll('.deactivate-user').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = parseInt(btn.dataset.id, 10);
          if (!window.confirm('Deactivate this user?')) return;
          try {
            await API.deactivateUser(id);
            await this.load();
          } catch (error) {
            alert(error.message);
          }
        });
      });

      section.querySelectorAll('.reactivate-user').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = parseInt(btn.dataset.id, 10);
          try {
            await API.reactivateUser(id);
            await this.load();
          } catch (error) {
            alert(error.message);
          }
        });
      });
    },

    setAddUserError(msg) {
      const el = Utils.getElement('#add-user-error');
      if (el) {
        el.textContent = msg;
        el.classList.add('active');
      }
    },

    clearAddUserError() {
      const el = Utils.getElement('#add-user-error');
      if (el) {
        el.textContent = '';
        el.classList.remove('active');
      }
    }
  }
};

// ============================================================================
// Application Initialization
// ============================================================================

document.addEventListener('DOMContentLoaded', async function() {
  // Hide portal until auth verified
  const portalRoot = Utils.getElement('#portal-root');
  if (portalRoot) Utils.hide(portalRoot);

  // Check authentication
  const auth = await Auth.check();
  if (!auth) return;

  // Apply role-based visibility
  Auth.applyRoleVisibility(auth.role);

  // Insert welcome message
  Auth.insertWelcome(auth);

  // Hide loading screen
  const loadingScreen = Utils.getElement('#auth-loading');
  if (loadingScreen) Utils.hide(loadingScreen);

  // Show portal
  if (portalRoot) Utils.show(portalRoot);

  // Initialize navigation
  Nav.init();

  // Load initial servers (for announcements/cards)
  try {
    const servers = await API.listServers();
    State.setServers(servers);
  } catch (error) {
    console.error('Failed to load initial servers:', error);
  }
});
