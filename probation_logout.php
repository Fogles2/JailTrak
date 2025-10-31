<?php
session_start();
session_destroy();
header('Location: probation_login.php');
exit;
?>