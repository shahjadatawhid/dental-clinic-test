<?php

use App\Settings\Settings;

if (! function_exists('setting')) {
    /**
     * Current value of a clinic setting (see App\Settings\Definitions).
     */
    function setting(string $key): mixed
    {
        return app(Settings::class)->get($key);
    }
}
