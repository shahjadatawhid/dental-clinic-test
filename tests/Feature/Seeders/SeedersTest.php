<?php

use App\Enums\RoleName;
use App\Enums\SequenceSeries;
use App\Models\NumberSequence;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DevUsersSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

it('seeds roles, sequences and one login per role, and is safe to run twice', function () {
    $this->seed();
    $this->seed();

    expect(Role::count())->toBe(count(RoleName::cases()))
        ->and(NumberSequence::count())->toBe(count(SequenceSeries::cases()))
        ->and(User::count())->toBe(count(RoleName::cases()));

    foreach (RoleName::cases() as $role) {
        $user = User::where('email', DevUsersSeeder::emailFor($role))->firstOrFail();
        expect($user->hasRole($role->value))->toBeTrue()
            ->and(Hash::check(DevUsersSeeder::PASSWORD, $user->password))->toBeTrue();
    }
});

it('refuses to create fictional data in production', function (string $seeder) {
    $this->artisan('app:sync-permissions');
    app()->detectEnvironment(fn () => 'production');

    try {
        expect(fn () => app($seeder)->setContainer(app())->__invoke())->toThrow(RuntimeException::class, 'must not run in production');
        expect(User::count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
})->with([DevUsersSeeder::class, DemoDataSeeder::class]);

it('only seeds reference data when the main seeder runs in production', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        app(DatabaseSeeder::class)->setContainer(app())->__invoke();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(Role::count())->toBe(count(RoleName::cases()))
        ->and(User::count())->toBe(0);
});

describe('Bangladeshi fake data', function () {
    it('makes 11-digit Bangladeshi mobile numbers', function () {
        foreach (range(1, 50) as $i) {
            expect(fake_bd()->phone())->toMatch('/^01[3-9]\d{8}$/');
        }
    });

    it('makes two-part names and Dhaka addresses', function () {
        expect(fake_bd()->name('female'))->toMatch('/^[A-Z][a-z]+ [A-Z][a-z]+$/')
            ->and(fake_bd()->address())->toMatch('/^House \d+, Road \d+, [A-Za-z ]+, Dhaka$/');
    });

    it('is used by the user factory', function () {
        $user = User::factory()->make();

        expect($user->phone)->toMatch('/^01[3-9]\d{8}$/');
    });
});
