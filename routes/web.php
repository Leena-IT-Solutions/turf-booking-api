<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function () {
    Volt::route('saas/sliders', 'saas.slider-manager')
        ->middleware('role:saas-admin')
        ->name('saas.sliders');
    Volt::route('saas/users', 'saas.user-manager')
        ->middleware('role:saas-admin')
        ->name('saas.users');
    Volt::route('saas/slot-categories', 'saas.slot-category-manager')
        ->middleware('role:saas-admin')
        ->name('saas.slot-categories');
    Route::get('/git-info', function () {
        try {
            $basePath = base_path();
            $currentUser = trim(shell_exec('whoami') ?? 'unknown');
            $gitDir = $basePath . '/.git';
            $gitExists = file_exists($gitDir);
            $gitReadable = $gitExists ? is_readable($gitDir) : false;

            $commitHash = trim(shell_exec('git -c safe.directory="' . $basePath . '" rev-parse --short HEAD') ?? '');
            $commitMessage = trim(shell_exec('git -c safe.directory="' . $basePath . '" log -1 --pretty=%B') ?? '');
            $branch = trim(shell_exec('git -c safe.directory="' . $basePath . '" rev-parse --abbrev-ref HEAD') ?? '');
            $commitDate = trim(shell_exec('git -c safe.directory="' . $basePath . '" log -1 --date=format:"%Y-%m-%d %H:%M:%S" --pretty=%cd') ?? '');
            $commitRelative = trim(shell_exec('git -c safe.directory="' . $basePath . '" log -1 --date=relative --pretty=%cd') ?? '');
            $remotes = trim(shell_exec('git -c safe.directory="' . $basePath . '" remote -v') ?? 'None');

            return response()->json([
                'success' => true,
                'branch' => ($branch && $branch !== 'HEAD') ? $branch : 'main',
                'commit_hash' => $commitHash ?: 'N/A',
                'commit_message' => $commitMessage ? strtok($commitMessage, "\n") : 'Git not initialized or not accessible',
                'commit_date' => $commitDate ?: 'N/A',
                'commit_relative' => $commitRelative ?: 'N/A',
                'diagnostics' => [
                    'php_user' => $currentUser,
                    'git_dir_exists' => $gitExists,
                    'git_dir_readable' => $gitReadable,
                    'remotes' => $remotes
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    })->name('git.info');

    Route::post('/git-update', function () {
        $basePath = base_path();
        
        $branch = trim(shell_exec('git -c safe.directory="' . $basePath . '" rev-parse --abbrev-ref HEAD') ?? 'main');
        if ($branch === 'HEAD' || empty($branch) || $branch === 'Unknown') {
            $branch = 'main';
        }
        
        $commands = [
            'git -c safe.directory="' . $basePath . '" reset --hard HEAD 2>&1',
            'git -c safe.directory="' . $basePath . '" pull origin ' . $branch . ' 2>&1',
            'php artisan migrate --force 2>&1',
            'php artisan optimize:clear 2>&1',
        ];

        $output = ["Starting update process on branch '{$branch}'...\n"];
        $success = true;

        foreach ($commands as $command) {
            $output[] = "$ " . $command;
            $cmdOutput = [];
            $status = null;
            exec("cd " . $basePath . " && " . $command, $cmdOutput, $status);
            $output[] = implode("\n", $cmdOutput);
            $output[] = "Exit Code: " . $status . "\n";
            if ($status !== 0) {
                $success = false;
            }
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
            $output[] = "OPcache reset successfully.\n";
        }

        return response()->json([
            'success' => $success,
            'output' => implode("\n", $output),
        ]);
    })->name('git.update');
});

require __DIR__.'/auth.php';
