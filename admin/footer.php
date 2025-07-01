<?php
// -------------------- Admin footer -------------------- //
?>

</main>

<!-- ПРИМЕР: сюда можно вставить футер‑контент -->
<!-- <footer class="text-center py-4 small text-muted">© <?= date('Y') ?> TeleAdm</footer> -->

<!-- Bootstrap JS (из CDN) и общий JS админ‑панели -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/admin.js?v=1"></script>

<?php
// Закрываем буфер, если он ещё активен (защита от ob_end_flush warning)
if (function_exists('ob_get_level') && ob_get_level()) {
    ob_end_flush();
}
?>
</body>
</html>
