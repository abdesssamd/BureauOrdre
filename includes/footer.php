</div> <!-- END .content-area -->

<?php if (file_exists(__DIR__ . '/chat_widget.php')) include __DIR__ . '/chat_widget.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('mySidebar');
    const content = document.querySelector('.content-area');
    const overlay = document.getElementById('overlay');

    function openSidebar() {
        sidebar.classList.add('active');
        overlay.classList.add('active');
    }

    function closeSidebar() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }

    function toggleSidebar() {
        if (sidebar.classList.contains('active')) {
			
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    // Click bouton menu
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
		
        toggleSidebar();
    });

    // Click overlay -> fermer
    overlay.addEventListener('click', function () {
        closeSidebar();
    });

    // Click contenu -> fermer en mobile
    content.addEventListener('click', function () {
        if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });

    // Empêcher click dans la sidebar de fermer le menu
    sidebar.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    // Sur redimensionnement : si on passe en desktop, on nettoie
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }
    });
});
</script>

</body>
</html>
