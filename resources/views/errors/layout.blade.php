{{--
    Shared layout for error pages. Kept free of database calls so it still renders
    when something deeper is broken.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $heading])
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-900">
        <main class="flex min-h-svh items-center justify-center p-6">
            <div class="flex w-full max-w-md flex-col items-center gap-4 text-center">
                <div class="flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon :name="$icon" class="size-7 text-zinc-500" />
                </div>

                <p class="font-mono text-sm text-zinc-400">{{ $code }}</p>
                <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>
                <flux:text>{{ $message }}</flux:text>

                <div class="mt-2 flex flex-wrap justify-center gap-3">
                    @auth
                        <flux:button variant="primary" icon="home" href="{{ route('dashboard') }}">{{ __('Go to dashboard') }}</flux:button>
                    @else
                        <flux:button variant="primary" icon="arrow-right-end-on-rectangle" href="{{ route('login') }}">{{ __('Log in') }}</flux:button>
                    @endauth
                    <flux:button variant="ghost" icon="arrow-left" href="{{ url()->previous() }}">{{ __('Go back') }}</flux:button>
                </div>
            </div>
        </main>
        @fluxScripts
    </body>
</html>
