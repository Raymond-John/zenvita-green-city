<?php

/**
 * Backward-compatible shim — canonical config lives in /config/database.php
 */
return require dirname(__DIR__, 2) . '/config/database.php';
