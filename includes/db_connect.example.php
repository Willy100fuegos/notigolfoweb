<?php
/**
 * Database Connection Example
 * 
 * Copy this file to includes/db_config.local.php or configure environment variables:
 * - DB_HOST
 * - DB_NAME
 * - DB_USER
 * - DB_PASS
 */

return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'dbname' => getenv('DB_NAME') ?: 'notigolfo_db',
    'username' => getenv('DB_USER') ?: 'notigolfo_user',
    'password' => getenv('DB_PASS') ?: '',
];
