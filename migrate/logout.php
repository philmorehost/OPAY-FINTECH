<?php
session_start();
session_destroy();
header('Location: /migrate/login');
exit;
