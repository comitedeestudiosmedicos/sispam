</main>

<footer class="footer mt-auto py-3 bg-white border-top text-center text-muted small no-print">
    <div class="container">
        <span>&copy; <?= date('Y') ?> <strong><?= htmlspecialchars(APP_NAME) ?></strong>. Todos los derechos reservados.</span>
    </div>
</footer>

<!-- Bootstrap 5.3 JS Bundle con Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar desplegables de menú superior
    var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
    dropdownElementList.forEach(function (dropdownToggleEl) {
        new bootstrap.Dropdown(dropdownToggleEl);
    });
});
</script>

<?php if (isset($_SESSION['user_id']) && ($_SESSION['rol_nombre'] ?? '') === 'Orientador'): ?>
<!-- Script de Notificaciones Push/Toast para Orientadores -->
<script src="assets/js/notifications.js"></script>
<?php endif; ?>

</body>
</html>
