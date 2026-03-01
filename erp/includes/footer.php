<?php // /erp/includes/footer.php ?>
</main>
<footer>
    &copy; <?= date('Y') ?> <?= h(ORG_NAME) ?> – <?= APP_NAME ?> v<?= APP_VERSION ?>
    &nbsp;|&nbsp; <a href="<?= BASE_URL ?>/help.php">Help</a>
    &nbsp;|&nbsp; <a href="<?= BASE_URL ?>/auth/logout.php">Logout</a>
</footer>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
