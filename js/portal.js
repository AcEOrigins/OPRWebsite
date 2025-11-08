// Portal Navigation Functionality
document.addEventListener('DOMContentLoaded', function() {
    const navItems = document.querySelectorAll('.nav-item');
    const tabContents = document.querySelectorAll('.tab-content');
    const pageTitle = document.getElementById('page-title');
    const subTabsNav = document.getElementById('sub-tabs-nav');
    const subTabsContainer = subTabsNav.querySelector('.sub-tabs-container');

    // Tab titles mapping
    const tabTitles = {
        'dashboard': 'Dashboard',
        'server-control': 'Server Control',
        'manage-content': 'Manage Site',
        'players': 'Players',
        'settings': 'Settings',
        'logs': 'Logs',
        'battlemetrics': 'BattleMetrics'
    };

    // Sub-tabs configuration for each main tab
    const subTabsConfig = {
        'manage-content': [
            { id: 'slideshow', label: 'Slideshow' },
            { id: 'server-info', label: 'Server Info' },
            { id: 'our-servers', label: 'Our Servers' },
            { id: 'footer', label: 'Footer' },
            { id: 'navigation', label: 'Navigation' }
        ]
        // Add more tabs with sub-tabs here as needed
        // 'dashboard': [
        //     { id: 'overview', label: 'Overview' },
        //     { id: 'analytics', label: 'Analytics' }
        // ]
    };

    // Function to render sub-tabs
    function renderSubTabs(tabId) {
        const subTabs = subTabsConfig[tabId];
        
        if (subTabs && subTabs.length > 0) {
            subTabsContainer.innerHTML = '';
            subTabs.forEach((subTab, index) => {
                const btn = document.createElement('button');
                btn.className = `sub-tab-btn ${index === 0 ? 'active' : ''}`;
                btn.textContent = subTab.label;
                btn.setAttribute('data-sub-tab', subTab.id);
                btn.addEventListener('click', () => switchSubTab(tabId, subTab.id));
                subTabsContainer.appendChild(btn);
            });
            subTabsNav.style.display = 'block';
            // Activate first sub-tab
            switchSubTab(tabId, subTabs[0].id);
        } else {
            subTabsNav.style.display = 'none';
        }
    }

    // Function to switch sub-tabs
    function switchSubTab(mainTabId, subTabId) {
        const mainTab = document.getElementById(mainTabId);
        if (!mainTab) return;

        // Remove active class from all sub-tab buttons
        subTabsContainer.querySelectorAll('.sub-tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Remove active class from all sub-tab contents
        mainTab.querySelectorAll('.sub-tab-content').forEach(content => {
            content.classList.remove('active');
        });

        // Add active class to clicked sub-tab button
        const activeBtn = subTabsContainer.querySelector(`[data-sub-tab="${subTabId}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        // Add active class to corresponding sub-tab content
        const activeContent = mainTab.querySelector(`[data-sub-tab="${subTabId}"]`);
        if (activeContent) {
            activeContent.classList.add('active');
        }

        // Show/hide add server button based on sub-tab
        const addServerBtn = document.getElementById('add-server-btn');
        if (addServerBtn) {
            if (mainTabId === 'manage-content' && subTabId === 'our-servers') {
                addServerBtn.style.display = 'flex';
            } else {
                addServerBtn.style.display = 'none';
            }
        }
    }

    // Handle navigation clicks
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all nav items and tabs
            navItems.forEach(nav => nav.classList.remove('active'));
            tabContents.forEach(tab => tab.classList.remove('active'));
            
            // Add active class to clicked nav item
            this.classList.add('active');
            
            // Get the tab to show
            const tabName = this.getAttribute('data-tab');
            const targetTab = document.getElementById(tabName);
            
            // Show the target tab
            if (targetTab) {
                targetTab.classList.add('active');
                
                // Update page title
                if (pageTitle && tabTitles[tabName]) {
                    pageTitle.textContent = tabTitles[tabName];
                }

                // Render sub-tabs if they exist
                renderSubTabs(tabName);
            }
        });
    });

    // Initialize sub-tabs for the active tab on page load
    const activeNavItem = document.querySelector('.nav-item.active');
    if (activeNavItem) {
        const activeTabId = activeNavItem.getAttribute('data-tab');
        renderSubTabs(activeTabId);
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
        addServerForm.reset();
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
        addServerForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const serverData = {
                id: Date.now(),
                name: document.getElementById('server-name').value,
                battlemetricsId: document.getElementById('battlemetrics-id').value
            };

            // Get existing servers from localStorage
            let servers = JSON.parse(localStorage.getItem('servers') || '[]');
            servers.push(serverData);
            localStorage.setItem('servers', JSON.stringify(servers));

            // Close modal and refresh display
            closeModal();
            
            // Refresh the servers list in portal
            renderServersList();
            
            // Trigger custom event to update frontend
            window.dispatchEvent(new CustomEvent('serversUpdated'));
        });
    }

    // Function to render servers list in portal
    function renderServersList() {
        const ourServersTab = document.querySelector('#manage-content .sub-tab-content[data-sub-tab="our-servers"]');
        if (!ourServersTab) return;

        const servers = JSON.parse(localStorage.getItem('servers') || '[]');
        
        if (servers.length === 0) {
            ourServersTab.innerHTML = '<p style="color: #cccccc;">Our Servers</p>';
            return;
        }

        let html = '<div class="servers-list">';
        servers.forEach(server => {
            html += `
                <div class="server-card">
                    <h3>${server.name || 'Untitled Server'}</h3>
                    <p><strong>BattleMetrics ID:</strong> ${server.battlemetricsId || 'N/A'}</p>
                    <button class="btn-danger delete-server" data-id="${server.id}">Delete</button>
                </div>
            `;
        });
        html += '</div>';
        ourServersTab.innerHTML = html;

        // Add delete functionality
        ourServersTab.querySelectorAll('.delete-server').forEach(btn => {
            btn.addEventListener('click', function() {
                const serverId = parseInt(this.getAttribute('data-id'));
                let servers = JSON.parse(localStorage.getItem('servers') || '[]');
                servers = servers.filter(s => s.id !== serverId);
                localStorage.setItem('servers', JSON.stringify(servers));
                renderServersList();
                renderServerInfo();
                window.dispatchEvent(new CustomEvent('serversUpdated'));
            });
        });
    }

    // Function to render server info cards in portal
    function renderServerInfo() {
        const serverInfoTab = document.querySelector('#manage-content .sub-tab-content[data-sub-tab="server-info"]');
        if (!serverInfoTab) return;

        const servers = JSON.parse(localStorage.getItem('servers') || '[]');
        
        if (servers.length === 0) {
            serverInfoTab.innerHTML = '<p style="color: #cccccc;">No server information available.</p>';
            return;
        }

        let html = '<div class="server-info-cards">';
        servers.forEach(server => {
            html += `
                <div class="server-info-card">
                    <h3>${server.name || 'Untitled Server'}</h3>
                    <div class="server-info-details">
                        <p class="server-platforms">Available on: XBOX, PLAYSTATION, PC</p>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        serverInfoTab.innerHTML = html;
    }

    // Initial render
    renderServersList();
    renderServerInfo();
    
    // Listen for server updates
    window.addEventListener('serversUpdated', () => {
        renderServersList();
        renderServerInfo();
    });
});

