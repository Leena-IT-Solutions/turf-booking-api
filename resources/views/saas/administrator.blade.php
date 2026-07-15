<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Administrator') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="sm:px-6 lg:px-8 space-y-6">
            <!-- Welcome Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold">{{ __("Welcome back, ") . auth()->user()->name }}!</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __("You're logged in as administrator.") }}</p>
                    </div>
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 rounded-full text-xs font-bold uppercase tracking-wider">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                        System Online
                    </div>
                </div>
            </div>

            <!-- Git Update Card -->
            <div x-data="gitUpdater()" x-init="init()" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 sm:p-8">
                <div class="flex items-center gap-3 pb-4 mb-6 border-b border-gray-100 dark:border-gray-700">
                    <div class="w-10 h-10 bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">System Updates & Deployment</h3>
                </div>

                <!-- Git Info Box (Full Width on Top) -->
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 rounded-2xl p-5 text-xs font-mono text-gray-600 dark:text-gray-400 flex flex-col gap-3 mb-6">
                    <div class="flex items-center">
                        <span class="text-[9px] uppercase font-black text-gray-400 dark:text-gray-500 w-24 tracking-widest">Branch:</span>
                        <span class="text-indigo-600 dark:text-indigo-400 truncate" x-text="gitInfo.branch + ' @ ' + gitInfo.commit_hash">Loading...</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-[9px] uppercase font-black text-gray-400 dark:text-gray-500 w-24 tracking-widest">Commit:</span>
                        <span class="text-gray-700 dark:text-gray-300 truncate" x-text="'&quot;' + gitInfo.commit_message + '&quot;'">Loading...</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-[9px] uppercase font-black text-gray-400 dark:text-gray-500 w-24 tracking-widest">Timestamp:</span>
                        <span class="text-emerald-600 dark:text-emerald-400 truncate" x-text="gitInfo.commit_date + ' (' + gitInfo.commit_relative + ')'">Loading...</span>
                    </div>
                    
                    <template x-if="gitInfo.diagnostics">
                        <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-800 flex flex-col gap-2 text-[10px]">
                            <div class="flex justify-between">
                                <span class="text-[9px] uppercase font-black text-gray-400 dark:text-gray-500 tracking-wider">PHP User:</span>
                                <span class="text-gray-600 dark:text-gray-400 font-bold" x-text="gitInfo.diagnostics.php_user"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[9px] uppercase font-black text-gray-400 dark:text-gray-500 tracking-wider">.git Readable:</span>
                                <span :class="gitInfo.diagnostics.git_dir_readable ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'" x-text="gitInfo.diagnostics.git_dir_readable ? 'YES' : 'NO'"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Git Self-Update & Artisan Console Commands (Full Width) -->
                <div class="mb-6 space-y-6">
                    <div>
                        <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Git Self-Update</h4>
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-2 leading-relaxed">
                            Deploy the latest updates directly from the remote GitHub repository. This process pulls the latest branch commits, runs any new database migrations, and flushes cache bundles.
                        </p>
                        
                        <div class="mt-6">
                            <button 
                                @click="updateSite()" 
                                :disabled="isUpdating"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white font-bold text-[11px] uppercase tracking-wider rounded-xl shadow transition duration-150 ease-in-out cursor-pointer"
                            >
                                <svg x-show="isUpdating" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" style="display: none;">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="isUpdating ? 'Updating...' : 'Update from GitHub'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Artisan Commands Section -->
                    <div class="border-t border-gray-100 dark:border-gray-700/60 pt-6">
                        <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Artisan Console Commands</h4>
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-2 leading-relaxed mb-6">
                            Run database migrations, clear system cache bundles, or optimize execution performance directly on the active environment.
                        </p>

                        <div class="space-y-4">
                            <!-- Command 1: Migrate -->
                            <div class="bg-gray-50/50 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800 p-5 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2.5 h-2.5 bg-indigo-500 rounded-lg"></span>
                                        <h5 class="text-xs font-bold text-gray-800 dark:text-gray-200">Migrate</h5>
                                    </div>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                                        Runs pending database schema updates. Best use case: After pulling updates that introduce new tables, columns, or relations.
                                    </p>
                                </div>
                                <button 
                                    @click="runCommand('migrate')" 
                                    :disabled="isUpdating"
                                    class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white font-bold text-[10px] uppercase tracking-wider rounded-xl shadow hover:shadow-md transition duration-150 ease-in-out cursor-pointer md:w-auto w-full shrink-0"
                                >
                                    {{ __('Migrate') }}
                                </button>
                            </div>

                            <!-- Command 2: Migrate Fresh & Seed -->
                            <div class="bg-gray-50/50 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800 p-5 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2.5 h-2.5 bg-amber-500 rounded-lg"></span>
                                        <h5 class="text-xs font-bold text-gray-800 dark:text-gray-200">Migrate Fresh & Seed</h5>
                                    </div>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                                        <strong>Caution:</strong> Drops all database tables and runs all seeders from scratch. Best use case: Hard-resetting system to default configurations. Permanent data loss!
                                    </p>
                                </div>
                                <button 
                                    @click="runCommand('migrate-fresh')" 
                                    :disabled="isUpdating"
                                    class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-amber-600 hover:bg-amber-700 disabled:bg-amber-400 text-white font-bold text-[10px] uppercase tracking-wider rounded-xl shadow hover:shadow-md transition duration-150 ease-in-out cursor-pointer md:w-auto w-full shrink-0"
                                >
                                    {{ __('Migrate Fresh & Seed') }}
                                </button>
                            </div>

                            <!-- Command 3: Seed DB -->
                            <div class="bg-gray-50/50 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800 p-5 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2.5 h-2.5 bg-teal-500 rounded-lg"></span>
                                        <h5 class="text-xs font-bold text-gray-800 dark:text-gray-200">Seed DB</h5>
                                    </div>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                                        Populates database tables with default configuration and test relations. Best use case: Restoring missing lookups or default seeded values.
                                    </p>
                                </div>
                                <button 
                                    @click="runCommand('seed')" 
                                    :disabled="isUpdating"
                                    class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-teal-600 hover:bg-teal-700 disabled:bg-teal-400 text-white font-bold text-[10px] uppercase tracking-wider rounded-xl shadow hover:shadow-md transition duration-150 ease-in-out cursor-pointer md:w-auto w-full shrink-0"
                                >
                                    {{ __('Seed DB') }}
                                </button>
                            </div>

                            <!-- Command 4: Optimize Clear -->
                            <div class="bg-gray-50/50 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800 p-5 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2.5 h-2.5 bg-rose-500 rounded-lg"></span>
                                        <h5 class="text-xs font-bold text-gray-800 dark:text-gray-200">Optimize Clear</h5>
                                    </div>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                                        Flushes all system cache compiled bundles (config, route, view). Best use case: If code or config updates are not reflecting.
                                    </p>
                                </div>
                                <button 
                                    @click="runCommand('clear-cache')" 
                                    :disabled="isUpdating"
                                    class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 disabled:bg-rose-400 text-white font-bold text-[10px] uppercase tracking-wider rounded-xl shadow hover:shadow-md transition duration-150 ease-in-out cursor-pointer md:w-auto w-full shrink-0"
                                >
                                    {{ __('Optimize Clear') }}
                                </button>
                            </div>

                            <!-- Command 5: Optimize Cache -->
                            <div class="bg-gray-50/50 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800 p-5 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2.5 h-2.5 bg-emerald-500 rounded-lg"></span>
                                        <h5 class="text-xs font-bold text-gray-800 dark:text-gray-200">Optimize Cache</h5>
                                    </div>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                                        Re-caches config, routes, and views to memory. Best use case: Improving application performance on production environments.
                                    </p>
                                </div>
                                <button 
                                    @click="runCommand('optimize')" 
                                    :disabled="isUpdating"
                                    class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-emerald-400 text-white font-bold text-[10px] uppercase tracking-wider rounded-xl shadow hover:shadow-md transition duration-150 ease-in-out cursor-pointer md:w-auto w-full shrink-0"
                                >
                                    {{ __('Optimize Cache') }}
                                </button>
                            </div>

                            <!-- Command 6: Composer Install -->
                            <div class="bg-gray-50/50 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800 p-5 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2.5 h-2.5 bg-sky-500 rounded-lg"></span>
                                        <h5 class="text-xs font-bold text-gray-800 dark:text-gray-200">Composer Install</h5>
                                    </div>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                                        Installs production package dependencies and optimizes autoloader. Best use case: After updating dependencies or composer.lock files.
                                    </p>
                                </div>
                                <button 
                                    @click="runCommand('composer-install')" 
                                    :disabled="isUpdating"
                                    class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 disabled:bg-sky-400 text-white font-bold text-[10px] uppercase tracking-wider rounded-xl shadow hover:shadow-md transition duration-150 ease-in-out cursor-pointer md:w-auto w-full shrink-0"
                                >
                                    {{ __('Composer Install') }}
                                </button>
                            </div>

                            <!-- Command 7: Permissions 777 -->
                            <div class="bg-gray-50/50 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800 p-5 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-2.5 h-2.5 bg-purple-500 rounded-lg"></span>
                                        <h5 class="text-xs font-bold text-gray-800 dark:text-gray-200">Permissions 777</h5>
                                    </div>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                                        Executes recursive write permissions (`chmod -R 777`) on project files. Best use case: Resolving directory write access errors (e.g. storage, log, cache folder issues).
                                    </p>
                                </div>
                                <button 
                                    @click="runCommand('fix-permissions')" 
                                    :disabled="isUpdating"
                                    class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-purple-600 hover:bg-purple-700 disabled:bg-purple-400 text-white font-bold text-[10px] uppercase tracking-wider rounded-xl shadow hover:shadow-md transition duration-150 ease-in-out cursor-pointer md:w-auto w-full shrink-0"
                                >
                                    {{ __('Permissions 777') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Update Messages -->
                <template x-if="successMessage">
                    <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider mb-6 flex items-center gap-3">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span x-text="successMessage"></span>
                    </div>
                </template>

                <!-- Command Output Log -->
                <template x-if="updateOutput">
                    <div class="mt-4">
                        <label class="block font-black text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">Console Output:</label>
                        <pre class="bg-gray-950 text-emerald-400 p-5 rounded-xl font-mono text-[11px] overflow-x-auto max-h-[250px] overflow-y-auto leading-relaxed border border-gray-900" x-text="updateOutput"></pre>
                    </div>
                </template>

                <!-- Custom Alpine Confirmation Modal -->
                <div x-show="showConfirmModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4" style="display: none;">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" @click="cancelConfirm()"></div>

                    <!-- Modal Container -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-md z-50 border border-gray-100 dark:border-gray-700">
                        <div class="p-6 sm:p-8">
                            <div class="flex items-center gap-4 text-amber-600 dark:text-amber-400 mb-4">
                                <div class="h-12 w-12 rounded-2xl bg-amber-50 dark:bg-amber-950/30 flex items-center justify-center border border-amber-100/50 dark:border-amber-950/50 shrink-0">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100" x-text="confirmTitle"></h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ __('Action Confirmation Required') }}
                                    </p>
                                </div>
                            </div>

                            <p class="text-xs text-gray-605 dark:text-gray-300 mb-6 leading-relaxed" x-text="confirmDescription"></p>

                            <!-- Form Actions -->
                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                                <button type="button" @click="cancelConfirm()" class="px-5 py-2.5 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 rounded-xl font-bold text-xs uppercase text-gray-700 dark:text-gray-300 transition duration-150 cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="button" @click="executeConfirm()" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase shadow transition duration-150 cursor-pointer">
                                    {{ __('Confirm') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function gitUpdater() {
            return {
                gitInfo: {
                    branch: '...',
                    commit_hash: '...',
                    commit_message: '...',
                    commit_date: '...',
                    commit_relative: '...'
                },
                isUpdating: false,
                updateOutput: '',
                successMessage: '',
                
                // Modal Confirmation State
                showConfirmModal: false,
                confirmTitle: '',
                confirmDescription: '',
                confirmAction: null,

                confirm(title, description, callback) {
                    this.confirmTitle = title;
                    this.confirmDescription = description;
                    this.confirmAction = callback;
                    this.showConfirmModal = true;
                },
                cancelConfirm() {
                    this.showConfirmModal = false;
                    this.confirmAction = null;
                },
                executeConfirm() {
                    if (this.confirmAction) {
                        this.confirmAction();
                    }
                    this.cancelConfirm();
                },

                init() {
                    this.fetchInfo();
                },
                
                fetchInfo() {
                    fetch('{{ route('git.info') }}', {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.gitInfo = data;
                        }
                    })
                    .catch(err => console.error('Error fetching git info:', err));
                },
                
                updateSite() {
                    this.confirm(
                        'Update Site from Git',
                        'Are you sure you want to update the site from Git origin? This will hard reset any local changes on the server.',
                        () => {
                            this.isUpdating = true;
                            this.successMessage = '';
                            this.updateOutput = 'Starting update process...\n\n';
                            
                            fetch('{{ route('git.update') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                }
                            })
                            .then(res => res.json())
                            .then(data => {
                                this.updateOutput = data.output;
                                if (data.success) {
                                    this.successMessage = 'Update process completed successfully.';
                                    this.fetchInfo();
                                } else {
                                    this.successMessage = 'Update finished with some errors (check exit codes).';
                                }
                            })
                            .catch(err => {
                                this.updateOutput += '\nError during update request:\n' + err.message;
                                this.successMessage = 'Update request failed.';
                            })
                            .finally(() => {
                                this.isUpdating = false;
                            });
                        }
                    );
                },

                runCommand(commandName) {
                    const performRun = () => {
                        this.isUpdating = true;
                        this.successMessage = '';
                        this.updateOutput = `Running artisan ${commandName} command...\n\n`;
                        
                        fetch('{{ route('artisan.run') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ command: commandName })
                        })
                        .then(res => res.json())
                        .then(data => {
                            this.updateOutput = data.output;
                            if (data.success) {
                                this.successMessage = `Command '${commandName}' executed successfully.`;
                            } else {
                                this.successMessage = `Command '${commandName}' failed. Check console output.`;
                            }
                        })
                        .catch(err => {
                            this.updateOutput += '\nError executing command:\n' + err.message;
                            this.successMessage = 'Request failed.';
                        })
                        .finally(() => {
                            this.isUpdating = false;
                        });
                    };

                    if (commandName === 'migrate-fresh') {
                        this.confirm(
                            'Warning: Migrate Fresh & Seed',
                            'WARNING: This will drop all database tables and re-run all seeders. All transactional records and changes will be permanently deleted. Are you sure you want to proceed?',
                            performRun
                        );
                    } else if (commandName === 'migrate') {
                        this.confirm(
                            'Run Database Migrations',
                            'Are you sure you want to run pending database migrations on the system?',
                            performRun
                        );
                    } else if (commandName === 'seed') {
                        this.confirm(
                            'Seed Database Tables',
                            'Are you sure you want to run the database seeders? This will insert default test and configuration records.',
                            performRun
                        );
                    } else if (commandName === 'clear-cache') {
                        this.confirm(
                            'Optimize Clear',
                            'Are you sure you want to run optimize:clear? This will flush config, route, view, and compiled application caches.',
                            performRun
                        );
                    } else if (commandName === 'optimize') {
                        this.confirm(
                            'Optimize Cache',
                            'Are you sure you want to run optimize? This will cache config, routes, and views to improve load performance.',
                            performRun
                        );
                    } else if (commandName === 'composer-install') {
                        this.confirm(
                            'Composer Install',
                            'Are you sure you want to run composer install --no-dev --optimize-autoloader? This will install production dependencies and optimize the autoloader.',
                            performRun
                        );
                    } else if (commandName === 'fix-permissions') {
                        this.confirm(
                            'Fix Folder Permissions',
                            'Are you sure you want to run chmod -R 777 on all files and folders in this project? This will recursively set write and execute access.',
                            performRun
                        );
                    } else {
                        performRun();
                    }
                }
            }
        }
    </script>
</x-app-layout>
