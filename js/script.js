// js/script.js

document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.querySelector('.hamburger-menu');
    const navLinks = document.querySelector('.nav-links');

    if (hamburger && navLinks) {
        // Toggle menu and hamburger icon on click
        hamburger.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            hamburger.classList.toggle('active'); // Toggles the 'X' animation
        });

        // Close menu when a navigation link is clicked (useful for single-page apps or when user navigates)
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                // Check if the menu is active before trying to remove classes
                if (navLinks.classList.contains('active')) {
                    navLinks.classList.remove('active');
                    hamburger.classList.remove('active');
                }
            });
        });
    }
});