<?php
return [
    'app_name' => 'MakeYourCount',
    'default_lang' => 'fr',
    'base_url' => (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'
    ) . '://' . $_SERVER['HTTP_HOST']
];

