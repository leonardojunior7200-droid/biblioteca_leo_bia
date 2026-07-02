<?php
require_once __DIR__ . '/includes/functions.php';

logout_user();
set_flash('Você saiu do sistema.', 'success');
redirect('login.php');
