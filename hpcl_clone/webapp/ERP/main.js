document.addEventListener('DOMContentLoaded', () => {
    // Mobile navigation dropdown toggle
    const navItems = document.querySelectorAll('nav ul li');
    navItems.forEach(item => {
        if (item.querySelector('.dropdown')) {
            item.addEventListener('click', (e) => {
                // Apply toggle only on smaller screens
                if (window.innerWidth <= 768) {
                    // Prevent default to allow opening the menu instead of navigating
                    if (e.target.parentElement === item) {
                        e.preventDefault();
                        item.classList.toggle('open');
                    }
                }
            });
        }
    });
});
