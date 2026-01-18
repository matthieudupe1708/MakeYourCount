<?php
echo "<pre>";
var_dump($_SERVER['MYC_DB_USER'] ?? null);
var_dump($_SERVER['MYC_DB_PASS'] ?? null);
var_dump($_SERVER['MYC_DB_PASS_B64'] ?? null);
echo "</pre>";
