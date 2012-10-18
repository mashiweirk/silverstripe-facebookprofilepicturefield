<?php

// hat tip: @dhensby
define('MOD_FPPF_PATH',rtrim(dirname(__FILE__), DIRECTORY_SEPARATOR));
$folders = explode(DIRECTORY_SEPARATOR,MOD_FPPF_PATH);
define('MOD_FPPF_DIR',rtrim(array_pop($folders),DIRECTORY_SEPARATOR));
unset($folders);

