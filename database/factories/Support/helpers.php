<?php

use Database\Factories\Support\BangladeshProvider;

if (! function_exists('fake_bd')) {
    /**
     * Fictional Bangladeshi data for factories and demo seeders (dev only):
     *   fake_bd()->name('female'), fake_bd()->phone(), fake_bd()->address()
     */
    function fake_bd(): BangladeshProvider
    {
        return new BangladeshProvider(fake());
    }
}
