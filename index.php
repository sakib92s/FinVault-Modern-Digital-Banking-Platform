<?php
declare(strict_types=1);
// FinVault entry point - send visitors to the customer portal.
require_once __DIR__ . '/includes/config.php';
redirect('/customer/index.php');
