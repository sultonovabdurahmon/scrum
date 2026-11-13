<?php
require_once 'config.php';
go(user() ? 'projects.php' : 'login.php');
