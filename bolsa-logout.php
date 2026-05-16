<?php
require_once 'includes/bolsa.php';
bolsaPublicadorLogout();
header('Location: bolsa-login.php');
exit;
