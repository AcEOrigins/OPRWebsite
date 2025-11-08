// Slideshow functionality
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    let currentSlide = 0;
    let slideInterval;

    // Function to show a specific slide
    function showSlide(index) {
        // Remove active class from all slides and dots
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        
        // Add active class to current slide and dot
        slides[index].classList.add('active');
        dots[index].classList.add('active');
        
        currentSlide = index;
    }

    // Function to go to next slide
    function nextSlide() {
        const next = (currentSlide + 1) % slides.length;
        showSlide(next);
    }

    // Event listeners for dots
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            showSlide(index);
            resetInterval();
        });
    });

    // Auto-play slideshow
    function startInterval() {
        slideInterval = setInterval(nextSlide, 5000); // Change slide every 5 seconds
    }

    function resetInterval() {
        clearInterval(slideInterval);
        startInterval();
    }

    // Start auto-play
    startInterval();

    // Pause on hover
    const slideshowContainer = document.querySelector('.slideshow-container');
    if (slideshowContainer) {
        slideshowContainer.addEventListener('mouseenter', () => {
            clearInterval(slideInterval);
        });
        
        slideshowContainer.addEventListener('mouseleave', () => {
            startInterval();
        });
    }

    // Display servers on frontend
    function renderServers() {
        const serversDisplay = document.getElementById('servers-display');
        if (!serversDisplay) return;

        const servers = JSON.parse(localStorage.getItem('servers') || '[]');
        
        if (servers.length === 0) {
            serversDisplay.innerHTML = '<p style="color: #cccccc; text-align: center; grid-column: 1 / -1;">No servers available at this time.</p>';
            return;
        }

        let html = '';
        servers.forEach(server => {
            html += `
                <div class="server-card-frontend">
                    <h3>${server.name || 'Untitled Server'}</h3>
                </div>
            `;
        });
        serversDisplay.innerHTML = html;
    }

    function renderServerInfo() {
        // Frontend keeps the default static server info section.
    }

    // Initial render
    renderServers();
    renderServerInfo();

    // Listen for server updates
    window.addEventListener('serversUpdated', () => {
        renderServers();
        renderServerInfo();
    });
});

