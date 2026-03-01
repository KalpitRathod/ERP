<?php
// /erp/includes/header.php
// Usage: $page_title = 'Page Name'; include ERP_INC . '/header.php';
$page_title = $page_title ?? APP_NAME;
$css_path   = BASE_URL . '/assets/css/style.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= $css_path ?>">
</head>
<body>
<header>
    <div class="header-left">
        <div class="logo-box">ERP</div>
        <div>
            <div class="brand-name"><?= APP_NAME ?></div>
            <div class="brand-sub"><?= ORG_NAME ?> – <?= APP_TAGLINE ?></div>
        </div>
    </div>
    <div class="header-center">
        <h1><?= h($page_title) ?></h1>
    </div>
    <div class="header-right">
        <div>
            <span class="user-name"><?= h(current_user_name()) ?></span>
            <span class="user-role"><?= h(str_replace('_', ' ', ucwords(current_role()))) ?></span>
        </div>
        <div style="margin-left:14px;">
            <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-default btn-sm">Logout</a>
        </div>
    </div>
</header>
