<?php

use App\Support\Settings;

if (! function_exists('system_name')) {
    function system_name(): string
    {
        return Settings::systemName();
    }
}
