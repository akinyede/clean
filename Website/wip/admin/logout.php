<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

auth_logout();

header('Location: index.php?logged_out=1');
exit;