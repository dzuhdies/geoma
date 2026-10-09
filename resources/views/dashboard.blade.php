<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GeoMA — Validasi Batas RT/RW Kota Malang</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                        mono: ['JetBrains Mono', 'SF Mono', 'Consolas', 'monospace'],
                    }
                }
            }
        }
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.14.8/dist/cdn.min.js"></script>

    <style>
        * { font-family: 'Inter', system-ui, -apple-system, sans-serif; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }

        .leaflet-container { background: #111 !important; }

        .leaflet-popup-content-wrapper {
            background: #1a1a1a !important;
            color: #e5e5e5 !important;
            border-radius: 8px !important;
            border: 1px solid #333 !important;
            box-shadow: 0 4px 16px rgba(0,0,0,0.5) !important;
            padding: 0 !important;
            overflow: hidden;
        }
        .leaflet-popup-content { margin: 0 !important; line-height: 1.5 !important; }
        .leaflet-popup-tip { background: #1a1a1a !important; }

        .panel {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        /* Inputs */
        select, input[type="text"] {
            font-family: 'Inter', system-ui, sans-serif;
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full w-full overflow-hidden bg-neutral-950 text-neutral-200" x-data="geoValidationApp()">

    <!-- MAP -->
    <div id="map" class="absolute inset-0 z-0"></div>

    <!-- TOP-RIGHT: stats + actions -->
    <div class="absolute top-3 right-3 z-20 flex items-center gap-2">
        <div class="panel rounded-lg px-3 py-1.5 flex items-center gap-3 text-xs">
            <span class="text-neutral-500" x-text="stats.total_regions.toLocaleString() + ' RT'"></span>
            <button
                @click="openChangedModal = true"
                class="text-amber-400 font-medium hover:text-amber-300 hover:underline flex items-center gap-1 transition-colors cursor-pointer"
                title="Buka Daftar RT Berubah"
            >
                <i data-lucide="edit-3" class="w-3 h-3 text-amber-500"></i>
                <span x-text="stats.total_changed + ' berubah'"></span>
            </button>
            <span class="text-neutral-500" x-text="stats.total_relations + ' relasi'"></span>
        </div>

        <button
            @click="openChangedModal = true"
            class="panel rounded-lg px-3 py-1.5 text-xs font-medium flex items-center gap-1.5 transition-colors text-amber-400 hover:bg-amber-500/10 border-amber-500/30 cursor-pointer"
            title="Lihat seluruh daftar RT yang ditandai berubah"
        >
            <i data-lucide="layers" class="w-3.5 h-3.5"></i>
            <span>Daftar Perubahan</span>
            <span x-show="stats.total_changed > 0" class="px-1.5 py-0.2 rounded-full bg-amber-500/20 text-amber-300 text-[10px] font-bold" x-text="stats.total_changed"></span>
        </button>

        <button
            @click="openValidationModal = true"
            class="panel rounded-lg px-3 py-1.5 text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer"
            :class="stats.total_warnings > 0 ? 'text-red-400 hover:bg-red-500/10 border-red-500/30' : 'text-neutral-400 hover:text-neutral-200'"
        >
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
            <span x-text="stats.total_warnings + ' warning'"></span>
        </button>

        <!-- LOGIN / USER PROFILE BUTTON (Beside Warning, same size) -->
        <template x-if="!isAuthenticated">
            <button
                @click="openLoginModal = true; loginError = '';"
                class="panel rounded-lg px-3 py-1.5 text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer text-blue-400 hover:text-blue-300 hover:bg-blue-500/10 border-blue-500/30"
                title="Masuk untuk mengedit data"
            >
                <i data-lucide="log-in" class="w-3.5 h-3.5 text-blue-400"></i>
                <span>Login</span>
            </button>
        </template>

        <template x-if="isAuthenticated">
            <div class="relative" x-data="{ openProfileMenu: false }">
                <button
                    @click="openProfileMenu = !openProfileMenu"
                    @click.outside="openProfileMenu = false"
                    class="panel rounded-full w-8 h-8 flex items-center justify-center transition-all duration-200 cursor-pointer border border-neutral-700 hover:border-blue-500 bg-neutral-900 text-neutral-200 hover:text-white shadow-sm"
                    :class="openProfileMenu ? 'ring-2 ring-blue-500/50 border-blue-500' : ''"
                    title="Menu Akun"
                >
                    <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center text-xs font-bold uppercase tracking-wider shadow-inner">
                        <span x-text="authUser?.username ? authUser.username.substring(0, 2).toUpperCase() : 'U'"></span>
                    </div>
                </button>

                <!-- Profile Dropdown Popup -->
                <div
                    x-show="openProfileMenu"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                    class="panel absolute right-0 mt-2 w-56 rounded-xl border border-neutral-700/80 bg-neutral-900/95 backdrop-blur-md shadow-2xl p-1.5 z-50 divide-y divide-neutral-800"
                >
                    <!-- Username header -->
                    <div class="px-3 py-2.5">
                        <div class="text-[10px] uppercase font-bold tracking-wider text-neutral-500">Akun Terhubung</div>
                        <div class="text-sm font-semibold text-neutral-100 truncate mt-0.5 flex items-center gap-1.5">
                            <i data-lucide="user" class="w-3.5 h-3.5 text-blue-400 shrink-0"></i>
                            <span x-text="authUser?.username || 'User'"></span>
                        </div>
                        <div class="text-[10px] text-emerald-400 flex items-center gap-1 mt-1 font-mono">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>Mode Editor Aktif</span>
                        </div>
                    </div>

                    <!-- Menu items -->
                    <div class="py-1">
                        <button
                            @click="openProfileMenu = false; openRegisterModal = true; registerError = ''; registerSuccess = ''; registerForm = { username: '', name: '', password: '' }"
                            class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium text-neutral-200 hover:text-white hover:bg-neutral-800 flex items-center gap-2 transition-colors cursor-pointer"
                        >
                            <i data-lucide="user-plus" class="w-3.5 h-3.5 text-emerald-400"></i>
                            <span>Tambah Akun</span>
                        </button>
                    </div>

                    <div class="pt-1">
                        <button
                            @click="openProfileMenu = false; logout()"
                            class="w-full text-left px-3 py-2 rounded-lg text-xs font-medium text-red-400 hover:text-red-300 hover:bg-red-500/10 flex items-center gap-2 transition-colors cursor-pointer"
                        >
                            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                            <span>Logout</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- LEFT PANEL -->
    <div class="absolute top-3 left-3 z-30 w-80 flex flex-col max-h-[calc(100vh-1.5rem)] pointer-events-none">

        <!-- Search card -->
        <div class="panel rounded-lg pointer-events-auto shrink-0 relative mb-2">
            <!-- Brand / Home -->
            <div
                @click="resetToCityView()"
                class="flex items-center justify-between px-3 py-2 border-b border-neutral-800 cursor-pointer hover:bg-neutral-800/50 transition-colors"
            >
                <span class="text-sm font-semibold text-neutral-100 tracking-tight">GeoMA</span>
                <span class="text-[11px] text-neutral-500 flex items-center gap-1 hover:text-neutral-300 transition-colors">
                    <i data-lucide="rotate-ccw" class="w-3 h-3"></i>
                    Reset
                </span>
            </div>

            <!-- Search input -->
            <div class="p-2 flex items-center gap-2">
                <div class="relative flex-1">
                    <input
                        type="text"
                        x-model="globalSearchQuery"
                        @input.debounce.200ms="onGlobalSearchInput()"
                        @focus="showSearchDropdown = true"
                        placeholder="Cari ID SLS, nama RT..."
                        class="w-full bg-neutral-900 border border-neutral-700 rounded-md pl-8 pr-7 py-1.5 text-xs text-neutral-200 placeholder-neutral-500 focus:outline-none focus:border-neutral-500 transition-colors"
                    >
                    <i data-lucide="search" class="w-3.5 h-3.5 absolute left-2.5 top-[7px] text-neutral-500"></i>
                    <button
                        x-show="globalSearchQuery"
                        @click="globalSearchQuery = ''; globalSearchResults = [];"
                        class="absolute right-2 top-[7px] text-neutral-500 hover:text-neutral-200"
                    >
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
                <button
                    @click="showFilterSection = !showFilterSection"
                    class="p-1.5 rounded-md border transition-colors shrink-0"
                    :class="showFilterSection ? 'bg-neutral-800 border-neutral-600 text-neutral-200' : 'border-neutral-700 text-neutral-500 hover:text-neutral-200'"
                >
                    <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                </button>
            </div>

            <!-- Search dropdown -->
            <div
                x-show="globalSearchResults.length > 0 && showSearchDropdown"
                x-cloak
                @click.outside="showSearchDropdown = false"
                class="absolute left-0 right-0 top-full mt-1 bg-neutral-900 border border-neutral-700 rounded-lg shadow-xl max-h-64 overflow-y-auto z-[99999]"
            >
                <div class="px-3 py-1.5 text-[11px] text-neutral-500 border-b border-neutral-800 flex justify-between">
                    <span>Hasil pencarian</span>
                    <span x-text="globalSearchResults.length + ' ditemukan'"></span>
                </div>
                <template x-for="item in globalSearchResults" :key="'gsearch_' + item.id">
                    <div
                        @click="jumpToRegion(item.id, item.nmkec, item.nmdesa, item.rw); showSearchDropdown = false; globalSearchQuery = item.nmsls || item.idsubsls;"
                        class="px-3 py-2 hover:bg-neutral-800 cursor-pointer transition-colors border-b border-neutral-800/50 last:border-0"
                    >
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 pr-2">
                                <div class="text-xs font-medium text-neutral-200 truncate" x-text="item.nmsls || item.nmsubsls"></div>
                                <div class="text-[11px] text-neutral-500 truncate mt-0.5">
                                    <span x-text="item.nmdesa"></span> · <span x-text="item.nmkec"></span>
                                </div>
                            </div>
                            <template x-if="item.is_changed">
                                <span class="shrink-0 text-[10px] font-medium text-amber-500">berubah</span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Filters + RT list -->
        <div
            class="panel rounded-lg flex flex-col min-h-0 overflow-hidden pointer-events-auto transition-all duration-200"
            :class="showFilterSection ? 'flex-1' : 'max-h-0 opacity-0 pointer-events-none p-0 border-0'"
        >
            <!-- Filters -->
            <div class="p-3 border-b border-neutral-800 space-y-2 shrink-0">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[11px] font-medium text-neutral-400 uppercase tracking-wider">Filter</span>
                    <button @click="resetFilter()" class="text-[11px] text-neutral-500 hover:text-neutral-300 transition-colors">Reset</button>
                </div>

                <select
                    x-model="filters.kecamatan"
                    @change="onKecamatanChange()"
                    class="w-full bg-neutral-900 border border-neutral-700 rounded-md px-2.5 py-1.5 text-xs text-neutral-300 focus:outline-none focus:border-neutral-500"
                >
                    <option value="">Semua Kecamatan</option>
                    <template x-for="kec in kecamatanOptions" :key="kec">
                        <option :value="kec" x-text="kec"></option>
                    </template>
                </select>

                <div class="grid grid-cols-2 gap-2">
                    <select
                        x-model="filters.desa"
                        @change="onDesaChange()"
                        :disabled="!filters.kecamatan || desaOptions.length === 0"
                        class="w-full bg-neutral-900 border border-neutral-700 rounded-md px-2 py-1.5 text-xs text-neutral-300 focus:outline-none focus:border-neutral-500 disabled:opacity-30"
                    >
                        <option value="">Semua Kelurahan</option>
                        <template x-for="desa in desaOptions" :key="desa">
                            <option :value="desa" x-text="desa"></option>
                        </template>
                    </select>

                    <select
                        x-model="filters.rw"
                        @change="onRwChange()"
                        :disabled="!filters.desa || rwOptions.length === 0"
                        class="w-full bg-neutral-900 border border-neutral-700 rounded-md px-2 py-1.5 text-xs text-neutral-300 focus:outline-none focus:border-neutral-500 disabled:opacity-30"
                    >
                        <option value="">Semua RW</option>
                        <template x-for="rw in rwOptions" :key="rw">
                            <option :value="rw" x-text="rw"></option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- RT list header -->
            <div class="px-3 py-1.5 border-b border-neutral-800 flex items-center justify-between shrink-0">
                <div class="relative flex-1 mr-2">
                    <input
                        type="text"
                        x-model="rtSearchQuery"
                        placeholder="Filter daftar..."
                        class="w-full bg-transparent border-0 pl-5 pr-2 py-0.5 text-[11px] text-neutral-300 placeholder-neutral-600 focus:outline-none"
                    >
                    <i data-lucide="search" class="w-3 h-3 absolute left-0 top-[5px] text-neutral-600"></i>
                </div>
                <span class="text-[11px] text-neutral-500 tabular-nums" x-text="filteredRtList.length + ' RT'"></span>
            </div>

            <!-- RT list -->
            <div class="flex-1 overflow-y-auto">
                <template x-if="filteredRtList.length === 0">
                    <div class="text-center py-8 text-xs text-neutral-600">
                        Tidak ada RT dalam filter ini.
                    </div>
                </template>

                <template x-for="rtItem in filteredRtList" :key="'rt_' + rtItem.id">
                    <div
                        @click="selectRegionById(rtItem.id)"
                        class="px-3 py-2 cursor-pointer transition-colors flex items-center justify-between text-xs border-b border-neutral-800/50"
                        :class="selectedId === rtItem.id ? 'bg-blue-500/10 text-white' : 'hover:bg-neutral-800/50 text-neutral-400'"
                    >
                        <div class="min-w-0 pr-2">
                            <div class="font-medium text-neutral-200 truncate" x-text="rtItem.nmsls || rtItem.nmsubsls"></div>
                            <div class="text-[10px] text-neutral-500 truncate" x-text="rtItem.nmsubsls"></div>
                        </div>
                        <template x-if="rtItem.is_changed">
                            <span class="shrink-0 text-[10px] font-medium text-amber-500">berubah</span>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- RIGHT DETAIL PANEL -->
    <div
        x-show="selectedRegion"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-4"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 translate-x-4"
        class="absolute top-3 right-3 bottom-3 z-30 w-80 panel rounded-lg flex flex-col overflow-hidden"
    >
        <template x-if="selectedRegion">
            <div class="h-full flex flex-col min-h-0">
                <!-- Header -->
                <div class="p-3 border-b border-neutral-800 shrink-0">
                    <div class="flex items-start justify-between">
                        <div class="min-w-0 pr-2">
                            <h2 class="text-sm font-semibold text-neutral-100 truncate" x-text="selectedRegion.nmsls || selectedRegion.nmsubsls"></h2>
                            <p class="text-[11px] text-neutral-500 font-mono mt-0.5" x-text="selectedRegion.idsubsls"></p>
                        </div>
                        <button
                            @click="clearHighlights()"
                            class="p-1 rounded text-neutral-500 hover:text-neutral-200 transition-colors"
                        >
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1 mt-2 text-[10px] text-neutral-400">
                        <span x-text="selectedRegion.nmkec"></span>
                        <span class="text-neutral-600">·</span>
                        <span x-text="selectedRegion.nmdesa"></span>
                        <template x-if="selectedRegion.rw">
                            <span>
                                <span class="text-neutral-600">·</span>
                                <span x-text="selectedRegion.rw"></span>
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-3 space-y-3">

                    <!-- ============================================== -->
                    <!-- GUEST / USER VIEW (Not Authenticated)          -->
                    <!-- ============================================== -->
                    <template x-if="!isAuthenticated">
                        <div class="space-y-3">
                            <!-- 1. Kondisi: TIDAK BERUBAH -->
                            <template x-if="!selectedRegion.is_changed">
                                <div class="rounded-lg border border-emerald-900/50 bg-emerald-950/25 p-3.5 space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-5 h-5 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0">
                                            <i data-lucide="check" class="w-3 h-3"></i>
                                        </div>
                                        <span class="text-xs font-semibold text-emerald-400">Tidak Ada Perubahan</span>
                                    </div>
                                    <p class="text-[11px] text-neutral-400 leading-relaxed pl-7">
                                        Wilayah ini berada dalam kondisi batas normal dan tidak memiliki perubahan/penyesuaian.
                                    </p>
                                </div>
                            </template>

                            <!-- 2. Kondisi: BERUBAH -->
                            <template x-if="selectedRegion.is_changed">
                                <div class="space-y-3">
                                    <!-- Status Badge Card -->
                                    <div class="rounded-lg border border-amber-800/60 bg-amber-950/30 p-3.5 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <div class="w-5 h-5 rounded-full bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 shrink-0">
                                                    <i data-lucide="edit-3" class="w-3 h-3"></i>
                                                </div>
                                                <span class="text-xs font-semibold text-amber-400">Wilayah Berubah</span>
                                            </div>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">BERUBAH</span>
                                        </div>
                                        <p class="text-[11px] text-neutral-300 leading-relaxed pl-7">
                                            Wilayah ini tercatat mengalami perubahan atau penyesuaian batas wilayah.
                                        </p>
                                    </div>

                                    <!-- Berubah dengan siapa (List Pasangan) -->
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[11px] font-semibold text-neutral-300 uppercase tracking-wider flex items-center gap-1.5">
                                                <i data-lucide="git-merge" class="w-3.5 h-3.5 text-amber-400"></i>
                                                <span>Berubah Dengan:</span>
                                            </span>
                                            <span class="text-[10px] text-neutral-500 font-mono" x-text="selectedRelatedNeighbors.length + ' Pasangan'"></span>
                                        </div>

                                        <template x-if="selectedRelatedNeighbors.length === 0">
                                            <div class="rounded-lg border border-neutral-800/80 bg-neutral-900/60 p-3 text-center text-xs text-neutral-500">
                                                Belum ada pasangan relasi yang ditetapkan untuk wilayah ini.
                                            </div>
                                        </template>

                                        <template x-if="selectedRelatedNeighbors.length > 0">
                                            <div class="space-y-1.5">
                                                <template x-for="n in selectedRelatedNeighbors" :key="'guest_rel_' + n.id">
                                                    <div
                                                        @click="jumpToRegion(n.id, n.nmkec, n.nmdesa, n.rw)"
                                                        class="p-2.5 rounded-lg border border-amber-900/40 bg-amber-950/15 hover:bg-amber-900/25 hover:border-amber-700/60 transition-all cursor-pointer flex items-center justify-between text-xs group"
                                                    >
                                                        <div class="min-w-0 pr-2">
                                                            <div class="text-neutral-200 font-medium truncate flex items-center gap-1.5">
                                                                <span x-text="n.nmsls || n.nmsubsls"></span>
                                                                <template x-if="n.is_cross_border_desa">
                                                                    <span class="text-[9px] px-1.5 py-0.2 rounded bg-purple-500/20 text-purple-300 border border-purple-500/30">Beda Kel.</span>
                                                                </template>
                                                            </div>
                                                            <div class="text-[10px] text-neutral-400 truncate mt-0.5">
                                                                <span x-text="n.nmdesa"></span>
                                                                <template x-if="n.rw">
                                                                    <span x-text="' · RW ' + n.rw"></span>
                                                                </template>
                                                                <template x-if="n.rt">
                                                                    <span x-text="' · RT ' + n.rt"></span>
                                                                </template>
                                                            </div>
                                                            <div class="text-[10px] text-neutral-500 font-mono truncate" x-text="n.idsubsls || n.idsls"></div>
                                                        </div>
                                                        <div class="shrink-0 flex items-center gap-1 text-amber-400 group-hover:text-amber-300">
                                                            <span class="text-[10px] font-medium">Buka</span>
                                                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- 3. Kondisi: WARNING VALIDASI -->
                            <template x-if="selectedRegion.warnings && selectedRegion.warnings.length > 0">
                                <div class="space-y-2 pt-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-semibold text-red-400 uppercase tracking-wider flex items-center gap-1.5">
                                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-red-400"></i>
                                            <span>Warning Validasi:</span>
                                        </span>
                                        <span class="text-[10px] font-bold text-red-400 bg-red-500/20 px-1.5 py-0.2 rounded-full border border-red-500/30" x-text="selectedRegion.warnings.length"></span>
                                    </div>

                                    <div class="space-y-1.5">
                                        <template x-for="w in selectedRegion.warnings" :key="w.message">
                                            <div class="rounded-lg border border-red-900/60 bg-red-950/30 p-2.5 text-xs text-red-300 space-y-2">
                                                <p class="leading-relaxed" x-text="w.message"></p>
                                                <template x-if="w.target_region_id">
                                                    <button
                                                        @click="jumpToRegion(w.target_region_id, w.target_kec, w.target_desa, w.target_rw)"
                                                        class="text-[11px] text-red-400 hover:text-red-300 font-medium flex items-center gap-1 bg-red-950/60 hover:bg-red-900/40 px-2.5 py-1 rounded border border-red-800/60 transition-colors cursor-pointer"
                                                    >
                                                        <span>Lihat</span>
                                                        <span class="font-bold" x-text="w.target_region_name"></span>
                                                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- ============================================== -->
                    <!-- EDITOR VIEW (Authenticated User)               -->
                    <!-- ============================================== -->
                    <template x-if="isAuthenticated">
                        <div class="space-y-3">
                            <!-- Change toggle -->
                            <div class="rounded-md border p-3 space-y-2" :class="selectedRegion.is_changed ? 'border-amber-800/50 bg-amber-950/20' : 'border-neutral-800 bg-neutral-900/50'">
                                <label class="flex items-center gap-2.5 select-none cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="selectedRegion.is_changed"
                                        @change="toggleChangeStatus($event.target.checked)"
                                        :disabled="savingStatus"
                                        class="w-4 h-4 rounded-sm bg-neutral-900 border-neutral-600 text-amber-500 focus:ring-amber-500 focus:ring-offset-0 cursor-pointer"
                                    >
                                    <span class="text-xs font-medium" :class="selectedRegion.is_changed ? 'text-amber-400' : 'text-neutral-400'">
                                        Tandai berubah
                                    </span>
                                </label>
                                <p class="text-[11px] text-neutral-500 leading-relaxed pl-6">
                                    <template x-if="selectedRegion.is_changed">
                                        <span>Tersimpan sebagai RT berubah. Pilih tetangga untuk relasi.</span>
                                    </template>
                                    <template x-if="!selectedRegion.is_changed">
                                        <span>Wilayah normal.</span>
                                    </template>
                                </p>
                            </div>

                            <!-- Warnings for Editor -->
                            <template x-if="selectedRegion.warnings && selectedRegion.warnings.length > 0">
                                <div class="space-y-1.5">
                                    <template x-for="w in selectedRegion.warnings" :key="w.message">
                                        <div class="rounded-md border border-red-900/50 bg-red-950/20 p-2.5 text-xs text-red-300 space-y-2">
                                            <p class="leading-relaxed" x-text="w.message"></p>
                                            <template x-if="w.target_region_id">
                                                <button
                                                    @click="jumpToRegion(w.target_region_id, w.target_kec, w.target_desa, w.target_rw)"
                                                    class="text-[11px] text-red-400 hover:text-red-300 font-medium flex items-center gap-1"
                                                >
                                                    Lihat <span x-text="w.target_region_name"></span>
                                                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Relations Selector (if changed) -->
                            <template x-if="selectedRegion.is_changed">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] font-medium text-neutral-400 uppercase tracking-wider">Berubah dengan</span>
                                    </div>

                                    <div class="space-y-0.5">
                                        <template x-if="!selectedRegion.neighbors || selectedRegion.neighbors.length === 0">
                                            <div class="py-3 text-xs text-neutral-600 text-center">Tidak ada tetangga geometris.</div>
                                        </template>

                                        <template x-for="n in selectedRegion.neighbors" :key="'rel_' + n.id">
                                            <div class="py-1.5 flex items-center justify-between text-xs hover:bg-neutral-800/30 rounded px-1 transition-colors">
                                                <label class="flex items-center gap-2 select-none min-w-0 pr-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        :checked="isRelationWith(n.id)"
                                                        @change="toggleRelation(n.id, $event.target.checked)"
                                                        class="w-3.5 h-3.5 rounded-sm bg-neutral-900 border-neutral-600 text-blue-500 focus:ring-blue-500 focus:ring-offset-0 cursor-pointer"
                                                    >
                                                    <div class="truncate">
                                                        <div class="text-neutral-300 truncate">
                                                            <span x-text="n.nmsls || n.nmsubsls"></span>
                                                            <template x-if="n.is_cross_border_desa">
                                                                <span class="text-[9px] text-purple-400 ml-1">beda kel.</span>
                                                            </template>
                                                        </div>
                                                        <div class="text-[10px] text-neutral-500 truncate">
                                                            <span x-text="n.nmdesa"></span>
                                                            <template x-if="n.rw">
                                                                <span x-text="' · RW ' + n.rw"></span>
                                                            </template>
                                                            <template x-if="n.rt">
                                                                <span x-text="' · RT ' + n.rt"></span>
                                                            </template>
                                                        </div>
                                                        <div class="text-[10px] text-neutral-400 font-mono truncate" x-text="n.idsubsls || n.idsls"></div>
                                                    </div>
                                                </label>
                                                <span
                                                    class="text-[10px] shrink-0"
                                                    :class="n.is_changed ? 'text-amber-500' : 'text-neutral-600'"
                                                    x-text="n.is_changed ? 'berubah' : '—'"
                                                ></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- All neighbors overview -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-medium text-neutral-400 uppercase tracking-wider">Tetangga</span>
                            <span class="text-[11px] text-neutral-600 tabular-nums" x-text="(selectedRegion.neighbors ? selectedRegion.neighbors.length : 0)"></span>
                        </div>

                        <div class="space-y-0.5 max-h-48 overflow-y-auto">
                            <template x-for="n in selectedRegion.neighbors" :key="'overview_' + n.id">
                                <div
                                    @click="jumpToRegion(n.id, n.nmkec, n.nmdesa, n.rw)"
                                    class="py-1.5 px-1 rounded cursor-pointer hover:bg-neutral-800/30 transition-colors flex items-center justify-between text-xs"
                                >
                                    <div class="min-w-0 pr-2">
                                        <div class="text-neutral-300 truncate">
                                            <span x-text="n.nmsls || n.nmsubsls"></span>
                                            <template x-if="n.is_cross_border_desa">
                                                <span class="text-[9px] text-purple-400 ml-1">beda kel.</span>
                                            </template>
                                        </div>
                                        <div class="text-[10px] text-neutral-500 truncate">
                                            <span x-text="n.nmdesa"></span>
                                            <template x-if="n.rw">
                                                <span x-text="' · RW ' + n.rw"></span>
                                            </template>
                                            <template x-if="n.rt">
                                                <span x-text="' · RT ' + n.rt"></span>
                                            </template>
                                            <template x-if="n.is_cross_border_kec">
                                                <span class="text-purple-400/70" x-text="' · Kec. ' + n.nmkec"></span>
                                            </template>
                                        </div>
                                        <div class="text-[10px] text-neutral-400 font-mono truncate" x-text="n.idsubsls || n.idsls"></div>
                                    </div>
                                    <div class="shrink-0 flex items-center gap-1.5">
                                        <span
                                            class="text-[10px]"
                                            :class="n.is_changed ? 'text-amber-500' : 'text-neutral-600'"
                                            x-text="n.is_changed ? 'berubah' : ''"
                                        ></span>
                                        <i data-lucide="chevron-right" class="w-3 h-3 text-neutral-600"></i>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </div>
        </template>
    </div>

    <!-- BOTTOM-LEFT: basemap + legend -->
    <div class="absolute bottom-3 left-3 z-20 flex items-end gap-2">
        <div class="panel rounded-md p-1 flex items-center gap-0.5">
            <button
                @click="switchBasemap('satellite')"
                class="px-2.5 py-1 rounded text-[11px] font-medium transition-colors"
                :class="activeBasemap === 'satellite' ? 'bg-neutral-700 text-white' : 'text-neutral-500 hover:text-neutral-300'"
            >Satelit</button>
            <button
                @click="switchBasemap('dark')"
                class="px-2.5 py-1 rounded text-[11px] font-medium transition-colors"
                :class="activeBasemap === 'dark' ? 'bg-neutral-700 text-white' : 'text-neutral-500 hover:text-neutral-300'"
            >Dark</button>
        </div>

        <div class="panel rounded-md px-2.5 py-1 text-[10px] hidden md:flex items-center gap-3 text-neutral-500">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-blue-500/80 border border-blue-400"></span> dipilih</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-amber-500/70 border border-amber-400"></span> berubah</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-cyan-500/50 border border-cyan-400 border-dashed"></span> tetangga</span>
        </div>
    </div>

    <!-- CHANGED REGIONS MODAL -->
    <div
        x-show="openChangedModal"
        x-cloak
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4 sm:p-6 bg-black/75 backdrop-blur-sm"
        @keydown.escape.window="openChangedModal = false"
    >
        <div
            @click.outside="openChangedModal = false"
            class="bg-neutral-900 border border-neutral-800 rounded-xl max-w-3xl w-full max-h-[88vh] flex flex-col shadow-2xl overflow-hidden"
        >
            <!-- Modal header -->
            <div class="px-5 py-3.5 border-b border-neutral-800 flex items-center justify-between shrink-0 bg-neutral-900/90">
                <div class="flex items-center gap-2.5">
                    <div class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-100 flex items-center gap-2">
                            <span>Daftar Wilayah Berubah</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30 font-mono" x-text="changedRegionsList.length + ' RT'"></span>
                        </h3>
                        <p class="text-[11px] text-neutral-400 mt-0.5">Daftar seluruh RT yang ditandai mengalami perubahan batas dan relasi pasangannya</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <template x-if="changedRegionsList && changedRegionsList.length > 0">
                        <button
                            type="button"
                            @click="exportChangedCsv()"
                            class="px-2.5 py-1 bg-emerald-600/10 hover:bg-emerald-600/25 text-emerald-400 border border-emerald-500/30 rounded-md text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer"
                            title="Download seluruh daftar RT berubah sebagai file CSV"
                        >
                            <i data-lucide="download" class="w-3.5 h-3.5"></i>
                            <span>Export CSV</span>
                        </button>
                    </template>
                    <button
                        @click="openChangedModal = false"
                        class="p-1.5 text-neutral-500 hover:text-neutral-200 hover:bg-neutral-800 rounded-lg transition-colors cursor-pointer"
                    >
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-px bg-neutral-800 border-b border-neutral-800 shrink-0 text-xs">
                <div class="bg-neutral-900/95 px-4 py-2.5">
                    <div class="text-[10px] text-neutral-500 uppercase tracking-wider">Total RT Berubah</div>
                    <div class="text-base font-bold text-amber-400 tabular-nums mt-0.5" x-text="changedRegionsList.length">0</div>
                </div>
                <div class="bg-neutral-900/95 px-4 py-2.5">
                    <div class="text-[10px] text-emerald-500/90 uppercase tracking-wider">Relasi Lengkap</div>
                    <div class="text-base font-bold text-emerald-400 tabular-nums mt-0.5" x-text="changedSummary.valid">0</div>
                </div>
                <div class="bg-neutral-900/95 px-4 py-2.5">
                    <div class="text-[10px] text-amber-500/90 uppercase tracking-wider">Perlu Perhatian</div>
                    <div class="text-base font-bold text-amber-300 tabular-nums mt-0.5" x-text="changedSummary.attention">0</div>
                </div>
                <div class="bg-neutral-900/95 px-4 py-2.5">
                    <div class="text-[10px] text-blue-500/90 uppercase tracking-wider">Kecamatan Terdampak</div>
                    <div class="text-base font-bold text-blue-400 tabular-nums mt-0.5" x-text="changedKecamatanOptions.length">0</div>
                </div>
            </div>

            <!-- Filter Section (Kecamatan -> Kelurahan cascading + Search) -->
            <div class="px-5 py-3 bg-neutral-900 border-b border-neutral-800 space-y-2.5 shrink-0">
                <!-- Search & Status Row -->
                <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center justify-between">
                    <div class="relative flex-1">
                        <input
                            type="text"
                            x-model="changedSearchQuery"
                            placeholder="Cari nama RT, ID SLS, RW, catatan..."
                            class="w-full bg-neutral-950/80 border border-neutral-700/80 rounded-lg pl-8 pr-7 py-1.5 text-xs text-neutral-200 placeholder-neutral-500 focus:outline-none focus:border-amber-500/60 transition-colors"
                        >
                        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-2.5 top-[9px] text-neutral-500"></i>
                        <button
                            x-show="changedSearchQuery"
                            @click="changedSearchQuery = ''"
                            class="absolute right-2 top-[9px] text-neutral-500 hover:text-neutral-200"
                        >
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>

                    <div class="flex items-center gap-1 shrink-0 text-xs">
                        <button
                            type="button"
                            @click="changedFilterStatus = 'ALL'"
                            class="px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors cursor-pointer"
                            :class="changedFilterStatus === 'ALL' ? 'bg-neutral-700 text-white font-semibold' : 'bg-neutral-950/60 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800'"
                        >
                            Semua Status
                        </button>
                        <button
                            type="button"
                            @click="changedFilterStatus = 'VALID'"
                            class="px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors cursor-pointer"
                            :class="changedFilterStatus === 'VALID' ? 'bg-emerald-600/30 text-emerald-300 border border-emerald-500/50 font-semibold' : 'bg-neutral-950/60 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800'"
                        >
                            Lengkap
                        </button>
                        <button
                            type="button"
                            @click="changedFilterStatus = 'ATTENTION'"
                            class="px-2.5 py-1 rounded-md text-[11px] font-medium transition-colors cursor-pointer"
                            :class="changedFilterStatus === 'ATTENTION' ? 'bg-amber-600/30 text-amber-300 border border-amber-500/50 font-semibold' : 'bg-neutral-950/60 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800'"
                        >
                            Perlu Cek
                        </button>
                    </div>
                </div>

                <!-- Step 1: Kecamatan pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto py-0.5 no-scrollbar text-xs">
                    <span class="text-[11px] text-neutral-500 font-medium mr-1 uppercase tracking-wider shrink-0">Kecamatan:</span>
                    <button
                        type="button"
                        @click="changedFilterKecamatan = 'ALL'; changedFilterDesa = 'ALL'"
                        class="px-2.5 py-1 rounded-md text-xs font-medium transition-colors shrink-0 cursor-pointer"
                        :class="changedFilterKecamatan === 'ALL' ? 'bg-amber-400 text-neutral-950 font-bold' : 'bg-neutral-800 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-700'"
                    >
                        Semua (<span x-text="changedRegionsList.length"></span>)
                    </button>
                    <template x-for="kec in changedKecamatanOptions" :key="'ckec_' + kec.name">
                        <button
                            type="button"
                            @click="changedFilterKecamatan = kec.name; changedFilterDesa = 'ALL'"
                            class="px-2.5 py-1 rounded-md text-xs font-medium transition-colors flex items-center gap-1.5 shrink-0 cursor-pointer"
                            :class="changedFilterKecamatan === kec.name ? 'bg-amber-400 text-neutral-950 font-bold' : 'bg-neutral-800 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-700'"
                        >
                            <span x-text="kec.name"></span>
                            <span class="text-[10px] px-1.5 py-0.2 rounded" :class="changedFilterKecamatan === kec.name ? 'bg-neutral-950/30 text-neutral-950 font-bold' : 'bg-neutral-700/70 text-neutral-400'" x-text="kec.count"></span>
                        </button>
                    </template>
                </div>

                <!-- Step 2: Kelurahan pills (visible when a specific Kecamatan is selected) -->
                <template x-if="changedFilterKecamatan !== 'ALL' && changedDesaOptions.length > 0">
                    <div class="flex items-center gap-1.5 overflow-x-auto pt-1.5 pb-0.5 no-scrollbar text-xs border-t border-neutral-800/80">
                        <span class="text-[10px] text-amber-500/90 font-medium mr-1 uppercase tracking-wider shrink-0 flex items-center gap-1">
                            <i data-lucide="corner-down-right" class="w-3 h-3"></i>
                            Kelurahan:
                        </span>
                        <button
                            type="button"
                            @click="changedFilterDesa = 'ALL'"
                            class="px-2.5 py-0.5 rounded text-[11px] font-medium transition-colors shrink-0 cursor-pointer"
                            :class="changedFilterDesa === 'ALL' ? 'bg-amber-300 text-neutral-950 font-bold' : 'bg-neutral-800/80 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-700'"
                        >
                            Semua Kelurahan
                        </button>
                        <template x-for="desa in changedDesaOptions" :key="'cdesa_' + desa.name">
                            <button
                                type="button"
                                @click="changedFilterDesa = desa.name"
                                class="px-2.5 py-0.5 rounded text-[11px] font-medium transition-colors flex items-center gap-1 shrink-0 cursor-pointer"
                                :class="changedFilterDesa === desa.name ? 'bg-amber-300 text-neutral-950 font-bold' : 'bg-neutral-800/80 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-700'"
                            >
                                <span x-text="desa.name"></span>
                                <span class="text-[9px] px-1.5 py-0.2 rounded" :class="changedFilterDesa === desa.name ? 'bg-neutral-950/30 text-neutral-950 font-bold' : 'bg-neutral-700/60 text-neutral-400'" x-text="desa.count"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Changed regions list content -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3">
                <!-- Empty state when no changed regions exist in database -->
                <template x-if="changedRegionsList.length === 0">
                    <div class="text-center py-16 px-6">
                        <div class="w-12 h-12 rounded-full bg-neutral-800 text-neutral-500 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="layers" class="w-6 h-6"></i>
                        </div>
                        <div class="text-sm font-semibold text-neutral-200">Belum ada RT yang ditandai berubah</div>
                        <p class="text-xs text-neutral-500 mt-1 max-w-sm mx-auto">
                            Pilih salah satu RT di peta atau daftar samping, kemudian aktifkan centang "Tandai berubah".
                        </p>
                    </div>
                </template>

                <!-- Empty state when filters return 0 results -->
                <template x-if="changedRegionsList.length > 0 && filteredChangedRegions.length === 0">
                    <div class="text-center py-12 px-6">
                        <div class="text-neutral-500 mb-2">
                            <i data-lucide="filter" class="w-6 h-6 mx-auto"></i>
                        </div>
                        <div class="text-xs font-medium text-neutral-300">Tidak ada RT berubah sesuai filter pencarian</div>
                        <button
                            @click="changedFilterKecamatan = 'ALL'; changedFilterDesa = 'ALL'; changedFilterStatus = 'ALL'; changedSearchQuery = ''"
                            class="mt-3 px-3.5 py-1.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-200 rounded-lg text-xs font-medium transition-colors cursor-pointer"
                        >
                            Reset Semua Filter
                        </button>
                    </div>
                </template>

                <!-- Cards of changed regions -->
                <template x-for="item in filteredChangedRegions" :key="'changed_' + item.id">
                    <div class="border border-neutral-800 bg-neutral-900/60 hover:bg-neutral-800/40 hover:border-neutral-700/80 rounded-lg p-3.5 sm:p-4 space-y-3 transition-colors">
                        <!-- Top header row -->
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="space-y-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 font-semibold border border-neutral-700" x-text="'Kel. ' + (item.nmdesa || '-')"></span>
                                    <span class="px-2 py-0.5 rounded bg-neutral-800/60 text-neutral-400 border border-neutral-800" x-text="'Kec. ' + (item.nmkec || '-')"></span>
                                    <template x-if="item.rw">
                                        <span class="px-1.5 py-0.5 rounded bg-neutral-800/40 text-neutral-400 font-mono text-[11px]" x-text="'RW ' + item.rw"></span>
                                    </template>
                                    <template x-if="item.rt">
                                        <span class="px-1.5 py-0.5 rounded bg-neutral-800/40 text-neutral-400 font-mono text-[11px]" x-text="'RT ' + item.rt"></span>
                                    </template>
                                </div>
                                <div class="text-sm font-semibold text-neutral-100 truncate" x-text="item.nmsls || item.nmsubsls"></div>
                                <div class="text-[11px] text-neutral-500 font-mono" x-text="item.idsubsls"></div>
                            </div>

                            <!-- Status Badge & Action -->
                            <div class="flex flex-col sm:items-end gap-2 shrink-0">
                                <template x-if="item.status === 'valid'">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-emerald-950/40 text-emerald-400 border border-emerald-800/60">
                                        <i data-lucide="check-circle" class="w-3 h-3"></i>
                                        Relasi Lengkap (2 Arah)
                                    </span>
                                </template>
                                <template x-if="item.status === 'no_relations'">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-purple-950/40 text-purple-300 border border-purple-800/60">
                                        <i data-lucide="help-circle" class="w-3 h-3"></i>
                                        Belum Pilih Pasangan
                                    </span>
                                </template>
                                <template x-if="item.status === 'partner_not_marked'">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-red-950/40 text-red-300 border border-red-800/60">
                                        <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                        Pasangan Belum Ditandai
                                    </span>
                                </template>
                                <template x-if="item.status === 'pending_reciprocal'">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-amber-950/40 text-amber-300 border border-amber-800/60">
                                        <i data-lucide="clock" class="w-3 h-3"></i>
                                        Menunggu Timbal Balik
                                    </span>
                                </template>

                                <button
                                    @click="jumpToRegion(item.region_id, item.nmkec, item.nmdesa, item.rw); openChangedModal = false;"
                                    class="px-3 py-1.5 bg-blue-600/10 hover:bg-blue-600/25 text-blue-400 border border-blue-500/30 rounded-lg text-xs font-medium transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                                >
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                                    <span>Buka di Peta</span>
                                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Notes (if any) -->
                        <template x-if="item.notes">
                            <div class="text-xs text-neutral-300 bg-neutral-950/60 border border-neutral-800/80 rounded-md px-3 py-2 flex items-start gap-2">
                                <i data-lucide="file-text" class="w-3.5 h-3.5 text-neutral-500 shrink-0 mt-0.5"></i>
                                <span class="leading-relaxed" x-text="item.notes"></span>
                            </div>
                        </template>

                        <!-- Relations / Pasangan Perubahan List -->
                        <div class="bg-neutral-950/50 rounded-lg p-2.5 border border-neutral-800/70 space-y-2">
                            <div class="flex items-center justify-between text-[11px] text-neutral-400">
                                <span class="font-medium uppercase tracking-wider text-[10px] text-neutral-500">Pasangan Perubahan</span>
                                <span class="font-mono text-[10px]" x-text="(item.partners ? item.partners.length : 0) + ' tetangga dipilih'"></span>
                            </div>

                            <template x-if="!item.partners || item.partners.length === 0">
                                <div class="text-[11px] text-neutral-500 italic py-1 flex items-center gap-1.5">
                                    <i data-lucide="info" class="w-3 h-3 text-neutral-600"></i>
                                    <span>Belum ada RT tetangga yang dipilih sebagai pasangan perubahan untuk RT ini.</span>
                                </div>
                            </template>

                            <template x-if="item.partners && item.partners.length > 0">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <template x-for="p in item.partners" :key="'p_' + item.id + '_' + p.id">
                                        <div class="bg-neutral-900/90 border rounded-md p-2 flex items-center justify-between gap-2" :class="p.is_reciprocal ? 'border-emerald-900/40 bg-emerald-950/10' : (!p.is_changed ? 'border-red-900/40 bg-red-950/10' : 'border-amber-900/40 bg-amber-950/10')">
                                            <div class="min-w-0 pr-1">
                                                <div class="text-xs font-medium text-neutral-200 truncate flex items-center gap-1">
                                                    <span x-text="p.name"></span>
                                                    <template x-if="p.nmdesa !== item.nmdesa">
                                                        <span class="text-[9px] px-1 rounded bg-purple-500/20 text-purple-300 font-normal">beda kel.</span>
                                                    </template>
                                                </div>
                                                <div class="text-[10px] text-neutral-500 truncate mt-0.5">
                                                    <span x-text="'Kel. ' + (p.nmdesa || '-')"></span>
                                                    <template x-if="p.rw">
                                                        <span x-text="' · RW ' + p.rw"></span>
                                                    </template>
                                                    <template x-if="p.rt">
                                                        <span x-text="' · RT ' + p.rt"></span>
                                                    </template>
                                                </div>
                                                <div class="text-[10px] text-neutral-400 font-mono truncate" x-text="p.idsubsls || p.idsls"></div>
                                            </div>

                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <template x-if="p.is_reciprocal">
                                                    <span class="text-[10px] font-medium text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-1.5 py-0.5 rounded">2 arah</span>
                                                </template>
                                                <template x-if="!p.is_changed">
                                                    <span class="text-[10px] font-medium text-red-400 bg-red-500/10 border border-red-500/20 px-1.5 py-0.5 rounded">Belum berubah</span>
                                                </template>
                                                <template x-if="p.is_changed && !p.is_reciprocal">
                                                    <span class="text-[10px] font-medium text-amber-400 bg-amber-500/10 border border-amber-500/20 px-1.5 py-0.5 rounded">1 arah</span>
                                                </template>

                                                <button
                                                    @click="jumpToRegion(p.id, p.nmkec, p.nmdesa, p.rw); openChangedModal = false;"
                                                    class="p-1 text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800 rounded transition-colors cursor-pointer"
                                                    title="Buka pasangan di peta"
                                                >
                                                    <i data-lucide="external-link" class="w-3 h-3"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal footer -->
            <div class="px-5 py-3 border-t border-neutral-800 flex items-center justify-between shrink-0 bg-neutral-900/90 text-xs">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-neutral-500" x-text="'Menampilkan ' + filteredChangedRegions.length + ' dari ' + changedRegionsList.length + ' RT berubah'"></span>
                    <template x-if="changedRegionsList && changedRegionsList.length > 0">
                        <button
                            type="button"
                            @click="exportChangedCsv()"
                            class="text-[11px] text-emerald-400 hover:text-emerald-300 hover:underline flex items-center gap-1 cursor-pointer"
                        >
                            <i data-lucide="download" class="w-3 h-3"></i>
                            <span>Download CSV (<span x-text="changedRegionsList.length"></span> RT)</span>
                        </button>
                    </template>
                </div>
                <button
                    @click="openChangedModal = false"
                    class="px-4 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-200 text-xs font-medium transition-colors cursor-pointer"
                >Tutup</button>
            </div>
        </div>
    </div>

    <!-- VALIDATION MODAL -->
    <div
        x-show="openValidationModal"
        x-cloak
        class="fixed inset-0 z-[99999] flex items-center justify-center p-6 bg-black/70"
        @keydown.escape.window="openValidationModal = false"
    >
        <div
            @click.outside="openValidationModal = false"
            class="bg-neutral-900 border border-neutral-800 rounded-lg max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl overflow-hidden"
        >
            <!-- Modal header -->
            <div class="px-5 py-3 border-b border-neutral-800 flex items-center justify-between shrink-0">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-100">Validasi Perubahan</h3>
                    <p class="text-[11px] text-neutral-500 mt-0.5">Klik "Buka" untuk navigasi ke RT pada peta</p>
                </div>
                <div class="flex items-center gap-2">
                    <template x-if="validationWarnings && validationWarnings.length > 0">
                        <button
                            type="button"
                            @click="exportWarningsCsv()"
                            class="px-2.5 py-1 bg-emerald-600/10 hover:bg-emerald-600/25 text-emerald-400 border border-emerald-500/30 rounded-md text-xs font-medium transition-colors flex items-center gap-1.5 cursor-pointer"
                            title="Download seluruh daftar warning sebagai file CSV"
                        >
                            <i data-lucide="download" class="w-3.5 h-3.5"></i>
                            <span>Export CSV</span>
                        </button>
                    </template>
                    <button
                        @click="openValidationModal = false"
                        class="p-1 text-neutral-500 hover:text-neutral-200 rounded transition-colors cursor-pointer"
                    >
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-4 gap-px bg-neutral-800 border-b border-neutral-800 shrink-0">
                <div class="bg-neutral-900 px-4 py-3">
                    <div class="text-[10px] text-neutral-500 uppercase tracking-wider">Total RT</div>
                    <div class="text-lg font-semibold text-neutral-200 tabular-nums mt-0.5" x-text="stats.total_regions.toLocaleString()">0</div>
                </div>
                <div class="bg-neutral-900 px-4 py-3">
                    <div class="text-[10px] text-amber-500/80 uppercase tracking-wider">Berubah</div>
                    <div class="text-lg font-semibold text-amber-400 tabular-nums mt-0.5" x-text="stats.total_changed.toLocaleString()">0</div>
                </div>
                <div class="bg-neutral-900 px-4 py-3">
                    <div class="text-[10px] text-neutral-500 uppercase tracking-wider">Relasi</div>
                    <div class="text-lg font-semibold text-neutral-200 tabular-nums mt-0.5" x-text="stats.total_relations.toLocaleString()">0</div>
                </div>
                <div class="bg-neutral-900 px-4 py-3">
                    <div class="text-[10px] uppercase tracking-wider" :class="stats.total_warnings > 0 ? 'text-red-500/80' : 'text-emerald-500/80'">Warning</div>
                    <div class="text-lg font-semibold tabular-nums mt-0.5" :class="stats.total_warnings > 0 ? 'text-red-400' : 'text-emerald-400'" x-text="stats.total_warnings.toLocaleString()">0</div>
                </div>
            </div>

            <!-- Filter by Kecamatan & Kelurahan -->
            <div class="px-5 py-2.5 bg-neutral-900 border-b border-neutral-800 space-y-2 shrink-0">
                <!-- Kecamatan pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto py-0.5 no-scrollbar text-xs">
                    <span class="text-[11px] text-neutral-500 font-medium mr-1.5 uppercase tracking-wider shrink-0">Kecamatan:</span>
                    <button
                        type="button"
                        @click="warningFilterKecamatan = 'ALL'; warningFilterDesa = 'ALL'"
                        class="px-2.5 py-1 rounded text-xs font-medium transition-colors shrink-0"
                        :class="warningFilterKecamatan === 'ALL' ? 'bg-neutral-200 text-neutral-900 font-semibold' : 'bg-neutral-800 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-700'"
                    >
                        Semua (<span x-text="validationWarnings.length"></span>)
                    </button>
                    <template x-for="kec in warningKecamatanOptions" :key="kec.name">
                        <button
                            type="button"
                            @click="warningFilterKecamatan = kec.name; warningFilterDesa = 'ALL'"
                            class="px-2.5 py-1 rounded text-xs font-medium transition-colors flex items-center gap-1.5 shrink-0"
                            :class="warningFilterKecamatan === kec.name ? 'bg-neutral-200 text-neutral-900 font-semibold' : 'bg-neutral-800 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-700'"
                        >
                            <span x-text="kec.name"></span>
                            <span class="text-[10px] px-1.5 py-0.2 rounded" :class="warningFilterKecamatan === kec.name ? 'bg-neutral-400/40 text-neutral-900 font-semibold' : 'bg-neutral-700/60 text-neutral-400'" x-text="kec.count"></span>
                        </button>
                    </template>
                </div>

                <!-- Kelurahan pills (appears after selecting a specific Kecamatan) -->
                <template x-if="warningFilterKecamatan !== 'ALL' && warningDesaOptions.length > 0">
                    <div class="flex items-center gap-1.5 overflow-x-auto pt-1.5 pb-0.5 no-scrollbar text-xs border-t border-neutral-800/80">
                        <span class="text-[10px] text-neutral-500 font-medium mr-1 uppercase tracking-wider shrink-0">Kelurahan:</span>
                        <button
                            type="button"
                            @click="warningFilterDesa = 'ALL'"
                            class="px-2 py-0.5 rounded text-[11px] font-medium transition-colors shrink-0"
                            :class="warningFilterDesa === 'ALL' ? 'bg-neutral-300 text-neutral-900 font-semibold' : 'bg-neutral-800/80 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-700'"
                        >
                            Semua Kelurahan
                        </button>
                        <template x-for="desa in warningDesaOptions" :key="desa.name">
                            <button
                                type="button"
                                @click="warningFilterDesa = desa.name"
                                class="px-2 py-0.5 rounded text-[11px] font-medium transition-colors flex items-center gap-1 shrink-0"
                                :class="warningFilterDesa === desa.name ? 'bg-neutral-300 text-neutral-900 font-semibold' : 'bg-neutral-800/80 text-neutral-400 hover:text-neutral-200 hover:bg-neutral-700'"
                            >
                                <span x-text="desa.name"></span>
                                <span class="text-[9px] px-1 py-0.2 rounded" :class="warningFilterDesa === desa.name ? 'bg-neutral-500/30 text-neutral-900 font-semibold' : 'bg-neutral-700/50 text-neutral-400'" x-text="desa.count"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            <!-- Warnings list -->
            <div class="flex-1 overflow-y-auto">
                <template x-if="validationWarnings.length === 0">
                    <div class="text-center py-16 px-6">
                        <div class="text-emerald-500 mb-2">
                            <i data-lucide="check-circle-2" class="w-8 h-8 mx-auto"></i>
                        </div>
                        <div class="text-sm font-medium text-neutral-200">Data konsisten</div>
                        <p class="text-xs text-neutral-500 mt-1">Tidak ada konflik perubahan wilayah.</p>
                    </div>
                </template>

                <template x-if="validationWarnings.length > 0 && filteredValidationWarnings.length === 0">
                    <div class="text-center py-12 px-6">
                        <div class="text-neutral-500 mb-2">
                            <i data-lucide="filter" class="w-6 h-6 mx-auto"></i>
                        </div>
                        <div class="text-xs font-medium text-neutral-300">Tidak ada warning untuk filter ini</div>
                        <button
                            @click="warningFilterKecamatan = 'ALL'; warningFilterDesa = 'ALL'"
                            class="mt-3 px-3 py-1 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 rounded text-xs transition-colors"
                        >
                            Reset Filter Warning
                        </button>
                    </div>
                </template>

                <template x-for="(warn, idx) in filteredValidationWarnings" :key="warn.message + '_' + idx">
                    <div class="px-5 py-3.5 border-b border-neutral-800 hover:bg-neutral-800/30 transition-colors space-y-2.5">
                        <!-- Location & status header badge -->
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
                                <template x-if="!warn.desa_b || (warn.desa_a === warn.desa_b && warn.kec_a === warn.kec_b)">
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 font-medium border border-neutral-700" x-text="'Kel. ' + (warn.desa_a || '-')"></span>
                                        <span class="px-2 py-0.5 rounded bg-neutral-800/60 text-neutral-400 border border-neutral-800" x-text="'Kec. ' + (warn.kec_a || '-')"></span>
                                    </div>
                                </template>
                                <template x-if="warn.desa_b && (warn.desa_a !== warn.desa_b || warn.kec_a !== warn.kec_b)">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 border border-neutral-700" x-text="'Kel. ' + (warn.desa_a || '-') + ' (' + (warn.kec_a || '-') + ')'"></span>
                                        <span class="text-neutral-500 font-bold">↔</span>
                                        <span class="px-2 py-0.5 rounded bg-neutral-800 text-neutral-200 border border-neutral-700" x-text="'Kel. ' + (warn.desa_b || '-') + ' (' + (warn.kec_b || '-') + ')'"></span>
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">Beda Wilayah</span>
                                    </div>
                                </template>
                            </div>

                            <span
                                class="text-[10px] font-mono px-1.5 py-0.5 rounded border"
                                :class="warn.type === 'unreciprocated_relation' ? 'bg-amber-950/30 text-amber-400 border-amber-800/50' : (warn.type === 'no_relations_selected' ? 'bg-purple-950/30 text-purple-400 border-purple-800/50' : 'bg-red-950/30 text-red-400 border-red-800/50')"
                                x-text="warn.type === 'unreciprocated_relation' ? 'Pasangan Belum Cocok' : (warn.type === 'no_relations_selected' ? 'Belum Pilih Pasangan' : 'Konflik Status')"
                            ></span>
                        </div>

                        <!-- Message -->
                        <p class="text-xs text-neutral-300 leading-relaxed font-normal" x-text="warn.message"></p>

                        <!-- Region Detail Card(s) with direct jump -->
                        <div class="grid gap-2 bg-neutral-950/60 rounded p-2.5 text-[11px] border border-neutral-800/80" :class="warn.region_id_b ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1'">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-medium text-neutral-200 truncate" x-text="warn.name_a"></div>
                                    <div class="text-neutral-500 text-[10px] truncate" x-text="'Kel. ' + (warn.desa_a || '-') + ' · ' + (warn.rw_a || '')"></div>
                                </div>
                                <button
                                    @click="jumpToRegion(warn.region_id_a, warn.kec_a, warn.desa_a, warn.rw_a); openValidationModal = false;"
                                    class="px-2.5 py-1 bg-blue-600/10 hover:bg-blue-600/20 text-blue-400 border border-blue-500/30 rounded text-[11px] font-medium transition-colors shrink-0 flex items-center gap-1"
                                >
                                    <span>Buka Peta</span>
                                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                </button>
                            </div>
                            <template x-if="warn.region_id_b">
                                <div class="flex items-center justify-between gap-2 border-t sm:border-t-0 sm:border-l border-neutral-800/80 pt-2 sm:pt-0 sm:pl-2.5">
                                    <div class="min-w-0">
                                        <div class="font-medium text-neutral-200 truncate" x-text="warn.name_b"></div>
                                        <div class="text-neutral-500 text-[10px] truncate" x-text="'Kel. ' + (warn.desa_b || '-') + ' · ' + (warn.rw_b || '')"></div>
                                    </div>
                                    <button
                                        @click="jumpToRegion(warn.region_id_b, warn.kec_b, warn.desa_b, warn.rw_b); openValidationModal = false;"
                                        class="px-2.5 py-1 bg-red-600/10 hover:bg-red-600/20 text-red-400 border border-red-500/30 rounded text-[11px] font-medium transition-colors shrink-0 flex items-center gap-1"
                                    >
                                        <span>Buka Peta</span>
                                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal footer -->
            <div class="px-5 py-2.5 border-t border-neutral-800 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-neutral-600">GeoMA</span>
                    <template x-if="validationWarnings && validationWarnings.length > 0">
                        <button
                            type="button"
                            @click="exportWarningsCsv()"
                            class="text-[11px] text-emerald-400 hover:text-emerald-300 hover:underline flex items-center gap-1 cursor-pointer"
                        >
                            <i data-lucide="download" class="w-3 h-3"></i>
                            <span>Download CSV (<span x-text="validationWarnings.length"></span> warning)</span>
                        </button>
                    </template>
                </div>
                <button
                    @click="openValidationModal = false"
                    class="px-3 py-1 rounded-md bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-xs font-medium transition-colors cursor-pointer"
                >Tutup</button>
            </div>
        </div>
    </div>

    <!-- LOGIN MODAL POPUP -->
    <div
        x-show="openLoginModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
        @keydown.escape.window="openLoginModal = false"
    >
        <div
            class="panel rounded-xl w-full max-w-md bg-neutral-900 border border-neutral-700/90 shadow-2xl overflow-hidden"
            @click.outside="openLoginModal = false"
        >
            <!-- Modal Header -->
            <div class="px-5 py-4 border-b border-neutral-800 flex items-center justify-between bg-neutral-900/90">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 border border-blue-500/30 flex items-center justify-center text-blue-400">
                        <i data-lucide="lock" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-100">Login Pengguna</h3>
                        <p class="text-[11px] text-neutral-400">Masuk untuk mengedit dan mengubah status wilayah RT</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="openLoginModal = false"
                    class="text-neutral-400 hover:text-neutral-200 p-1 rounded-md hover:bg-neutral-800 transition-colors cursor-pointer"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form @submit.prevent="login()" class="p-5 space-y-4">
                <template x-if="loginError">
                    <div class="p-3 rounded-lg bg-red-950/40 border border-red-800/60 text-red-300 text-xs flex items-start gap-2.5">
                        <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-red-400 mt-0.5"></i>
                        <span x-text="loginError"></span>
                    </div>
                </template>

                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-neutral-300">Username</label>
                    <div class="relative">
                        <i data-lucide="user" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-500"></i>
                        <input
                            type="text"
                            x-model="loginForm.username"
                            required
                            autocomplete="username"
                            placeholder="Masukkan username"
                            class="w-full pl-9 pr-3 py-2 text-xs bg-neutral-950/90 border border-neutral-700/80 rounded-lg text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                        >
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-neutral-300">Password</label>
                    <div class="relative">
                        <i data-lucide="key-round" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-500"></i>
                        <input
                            :type="showLoginPassword ? 'text' : 'password'"
                            x-model="loginForm.password"
                            required
                            autocomplete="current-password"
                            placeholder="Masukkan password"
                            class="w-full pl-9 pr-10 py-2 text-xs bg-neutral-950/90 border border-neutral-700/80 rounded-lg text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                        >
                        <button
                            type="button"
                            @click="showLoginPassword = !showLoginPassword"
                            class="absolute right-2.5 top-2 text-neutral-500 hover:text-neutral-300 p-0.5 rounded cursor-pointer"
                        >
                            <i :data-lucide="showLoginPassword ? 'eye-off' : 'eye'" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="openLoginModal = false"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-medium text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800 transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="loginLoading"
                        class="px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5 transition-all shadow-md shadow-blue-600/20 cursor-pointer"
                    >
                        <template x-if="loginLoading">
                            <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                        </template>
                        <span x-text="loginLoading ? 'Memproses...' : 'Masuk'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAMBAH AKUN MODAL POPUP (Accessible when logged in) -->
    <div
        x-show="openRegisterModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
        @keydown.escape.window="openRegisterModal = false"
    >
        <div
            class="panel rounded-xl w-full max-w-md bg-neutral-900 border border-neutral-700/90 shadow-2xl overflow-hidden"
            @click.outside="openRegisterModal = false"
        >
            <!-- Modal Header -->
            <div class="px-5 py-4 border-b border-neutral-800 flex items-center justify-between bg-neutral-900/90">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-100">Tambah Akun Pengguna</h3>
                        <p class="text-[11px] text-neutral-400">Buat akun baru untuk rekan editor/administrator</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="openRegisterModal = false"
                    class="text-neutral-400 hover:text-neutral-200 p-1 rounded-md hover:bg-neutral-800 transition-colors cursor-pointer"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form @submit.prevent="registerUser()" class="p-5 space-y-4">
                <template x-if="registerError">
                    <div class="p-3 rounded-lg bg-red-950/40 border border-red-800/60 text-red-300 text-xs flex items-start gap-2.5">
                        <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-red-400 mt-0.5"></i>
                        <span x-text="registerError"></span>
                    </div>
                </template>

                <template x-if="registerSuccess">
                    <div class="p-3 rounded-lg bg-emerald-950/40 border border-emerald-800/60 text-emerald-300 text-xs flex items-start gap-2.5">
                        <i data-lucide="check-circle-2" class="w-4 h-4 shrink-0 text-emerald-400 mt-0.5"></i>
                        <span x-text="registerSuccess"></span>
                    </div>
                </template>

                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-neutral-300">Username <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <i data-lucide="at-sign" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-500"></i>
                        <input
                            type="text"
                            x-model="registerForm.username"
                            required
                            placeholder="misal: editor_klojen"
                            class="w-full pl-9 pr-3 py-2 text-xs bg-neutral-950/90 border border-neutral-700/80 rounded-lg text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                        >
                    </div>
                    <p class="text-[10px] text-neutral-500">Hanya huruf, angka, titik, strip, dan underscore (_)</p>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-neutral-300">Nama Lengkap / Catatan <span class="text-neutral-500 text-[10px]">(Opsional)</span></label>
                    <div class="relative">
                        <i data-lucide="user" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-500"></i>
                        <input
                            type="text"
                            x-model="registerForm.name"
                            placeholder="misal: Operator Wilayah Sukun"
                            class="w-full pl-9 pr-3 py-2 text-xs bg-neutral-950/90 border border-neutral-700/80 rounded-lg text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                        >
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-neutral-300">Password <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <i data-lucide="key-round" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-neutral-500"></i>
                        <input
                            :type="showRegisterPassword ? 'text' : 'password'"
                            x-model="registerForm.password"
                            required
                            minlength="4"
                            placeholder="Minimal 4 karakter"
                            class="w-full pl-9 pr-10 py-2 text-xs bg-neutral-950/90 border border-neutral-700/80 rounded-lg text-neutral-100 placeholder-neutral-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                        >
                        <button
                            type="button"
                            @click="showRegisterPassword = !showRegisterPassword"
                            class="absolute right-2.5 top-2 text-neutral-500 hover:text-neutral-300 p-0.5 rounded cursor-pointer"
                        >
                            <i :data-lucide="showRegisterPassword ? 'eye-off' : 'eye'" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="openRegisterModal = false"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-medium text-neutral-400 hover:text-neutral-200 hover:bg-neutral-800 transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="registerLoading"
                        class="px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5 transition-all shadow-md shadow-emerald-600/20 cursor-pointer"
                    >
                        <template x-if="registerLoading">
                            <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                        </template>
                        <span x-text="registerLoading ? 'Menyimpan...' : 'Simpan Akun'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TOAST NOTIFICATION -->
    <div
        x-show="showToast"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-3"
        class="fixed bottom-5 right-5 z-50 flex items-center gap-2.5 px-4 py-2.5 rounded-xl border shadow-2xl text-xs font-medium backdrop-blur-md"
        :class="{
            'bg-emerald-950/95 border-emerald-500/50 text-emerald-200 shadow-emerald-950/50': toastType === 'success',
            'bg-red-950/95 border-red-500/50 text-red-200 shadow-red-950/50': toastType === 'error',
            'bg-neutral-900/95 border-neutral-700/90 text-neutral-200 shadow-neutral-950/50': toastType === 'info'
        }"
    >
        <template x-if="toastType === 'success'">
            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400 shrink-0"></i>
        </template>
        <template x-if="toastType === 'error'">
            <i data-lucide="alert-circle" class="w-4 h-4 text-red-400 shrink-0"></i>
        </template>
        <template x-if="toastType === 'info'">
            <i data-lucide="info" class="w-4 h-4 text-blue-400 shrink-0"></i>
        </template>
        <span x-text="toastMessage"></span>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        function geoValidationApp() {
            return {
                csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                isAuthenticated: @json($isAuthenticated ?? false),
                authUser: @json($authUser ?? null),

                openLoginModal: false,
                openRegisterModal: false,
                showLoginPassword: false,
                showRegisterPassword: false,

                loginForm: {
                    username: '',
                    password: '',
                },
                loginLoading: false,
                loginError: '',

                registerForm: {
                    username: '',
                    name: '',
                    password: '',
                },
                registerLoading: false,
                registerError: '',
                registerSuccess: '',

                showToast: false,
                toastMessage: '',
                toastType: 'info',
                toastTimeout: null,

                map: null,
                geoJsonLayer: null,
                crossNeighborLayerGroup: null,

                activeBasemap: 'satellite',
                basemapLayers: {},

                kecamatanOptions: @json($kecamatanList),
                desaOptions: [],
                rwOptions: [],
                rtList: [],
                rtSearchQuery: '',

                globalSearchQuery: '',
                globalSearchResults: [],
                showSearchDropdown: false,
                showFilterSection: true,

                filters: {
                    kecamatan: '',
                    desa: '',
                    rw: '',
                },

                stats: {
                    total_regions: 0,
                    total_changed: 0,
                    total_relations: 0,
                    total_warnings: 0,
                },

                validationWarnings: [],
                selectedRegion: null,

                selectedId: null,
                neighborIdSet: new Set(),
                changedIdSet: new Set(),

                loadingMap: false,
                savingStatus: false,
                openValidationModal: false,
                openChangedModal: false,

                changedRegionsList: [],
                changedFilterKecamatan: 'ALL',
                changedFilterDesa: 'ALL',
                changedFilterStatus: 'ALL',
                changedSearchQuery: '',

                warningFilterKecamatan: 'ALL',
                warningFilterDesa: 'ALL',

                get changedKecamatanOptions() {
                    const counts = {};
                    for (const r of this.changedRegionsList) {
                        if (r.nmkec) {
                            counts[r.nmkec] = (counts[r.nmkec] || 0) + 1;
                        }
                    }
                    return Object.keys(counts).sort().map(name => ({
                        name,
                        count: counts[name]
                    }));
                },

                get changedDesaOptions() {
                    if (this.changedFilterKecamatan === 'ALL') return [];
                    const counts = {};
                    for (const r of this.changedRegionsList) {
                        if (r.nmkec === this.changedFilterKecamatan && r.nmdesa) {
                            counts[r.nmdesa] = (counts[r.nmdesa] || 0) + 1;
                        }
                    }
                    return Object.keys(counts).sort().map(name => ({
                        name,
                        count: counts[name]
                    }));
                },

                get changedSummary() {
                    let valid = 0;
                    let attention = 0;
                    for (const r of this.changedRegionsList) {
                        if (r.status === 'valid') {
                            valid++;
                        } else {
                            attention++;
                        }
                    }
                    return { valid, attention };
                },

                get filteredChangedRegions() {
                    return this.changedRegionsList.filter(r => {
                        if (this.changedFilterKecamatan !== 'ALL' && r.nmkec !== this.changedFilterKecamatan) {
                            return false;
                        }
                        if (this.changedFilterKecamatan !== 'ALL' && this.changedFilterDesa !== 'ALL' && r.nmdesa !== this.changedFilterDesa) {
                            return false;
                        }
                        if (this.changedFilterStatus !== 'ALL') {
                            if (this.changedFilterStatus === 'VALID' && r.status !== 'valid') return false;
                            if (this.changedFilterStatus === 'ATTENTION' && r.status === 'valid') return false;
                        }
                        if (this.changedSearchQuery.trim()) {
                            const q = this.changedSearchQuery.toLowerCase();
                            const matchName = (r.nmsls && r.nmsls.toLowerCase().includes(q)) || (r.nmsubsls && r.nmsubsls.toLowerCase().includes(q));
                            const matchId = (r.idsubsls && r.idsubsls.toLowerCase().includes(q)) || (r.idsls && r.idsls.toLowerCase().includes(q));
                            const matchDesa = r.nmdesa && r.nmdesa.toLowerCase().includes(q);
                            const matchKec = r.nmkec && r.nmkec.toLowerCase().includes(q);
                            const matchNotes = r.notes && r.notes.toLowerCase().includes(q);
                            const matchRw = r.rw && r.rw.toLowerCase().includes(q);
                            const matchPartners = r.partners && r.partners.some(p => (p.name && p.name.toLowerCase().includes(q)) || (p.nmdesa && p.nmdesa.toLowerCase().includes(q)));
                            if (!matchName && !matchId && !matchDesa && !matchKec && !matchNotes && !matchRw && !matchPartners) {
                                return false;
                            }
                        }
                        return true;
                    });
                },

                get warningKecamatanOptions() {
                    const counts = {};
                    for (const w of this.validationWarnings) {
                        const kecs = new Set();
                        if (w.kec_a) kecs.add(w.kec_a);
                        if (w.kec_b) kecs.add(w.kec_b);
                        kecs.forEach(k => {
                            counts[k] = (counts[k] || 0) + 1;
                        });
                    }
                    return Object.keys(counts).sort().map(name => ({
                        name,
                        count: counts[name]
                    }));
                },

                get warningDesaOptions() {
                    if (this.warningFilterKecamatan === 'ALL') return [];

                    const counts = {};
                    for (const w of this.validationWarnings) {
                        const matchKecA = (w.kec_a === this.warningFilterKecamatan);
                        const matchKecB = (w.kec_b === this.warningFilterKecamatan);

                        if (matchKecA && w.desa_a) {
                            counts[w.desa_a] = (counts[w.desa_a] || 0) + 1;
                        }
                        if (matchKecB && w.desa_b && (!matchKecA || w.desa_b !== w.desa_a)) {
                            counts[w.desa_b] = (counts[w.desa_b] || 0) + 1;
                        }
                    }
                    return Object.keys(counts).sort().map(name => ({
                        name,
                        count: counts[name]
                    }));
                },

                get filteredValidationWarnings() {
                    return this.validationWarnings.filter(w => {
                        if (this.warningFilterKecamatan !== 'ALL') {
                            const matchesKec = (w.kec_a === this.warningFilterKecamatan) || (w.kec_b === this.warningFilterKecamatan);
                            if (!matchesKec) return false;
                        }

                        if (this.warningFilterKecamatan !== 'ALL' && this.warningFilterDesa !== 'ALL') {
                            const matchesDesa = (w.desa_a === this.warningFilterDesa) || (w.desa_b === this.warningFilterDesa);
                            if (!matchesDesa) return false;
                        }

                        return true;
                    });
                },

                get filteredRtList() {
                    if (!this.rtSearchQuery.trim()) {
                        return this.rtList;
                    }
                    const q = this.rtSearchQuery.toLowerCase();
                    return this.rtList.filter(r =>
                        (r.nmsls && r.nmsls.toLowerCase().includes(q)) ||
                        (r.nmsubsls && r.nmsubsls.toLowerCase().includes(q)) ||
                        (r.rt && r.rt.toLowerCase().includes(q)) ||
                        (r.idsubsls && r.idsubsls.includes(q))
                    );
                },

                get selectedRelatedNeighbors() {
                    if (!this.selectedRegion || !this.selectedRegion.neighbors || !this.selectedRegion.related_region_ids) {
                        return [];
                    }
                    return this.selectedRegion.neighbors.filter(n => this.selectedRegion.related_region_ids.includes(n.id));
                },

                async init() {
                    this.initMap();
                    this.fetchValidationReport();
                    this.fetchChangedRegions();
                    this.$nextTick(() => {
                        if (window.lucide) lucide.createIcons();
                    });

                    this.$watch('openChangedModal', () => {
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    });

                    this.$watch('openValidationModal', () => {
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    });

                    this.$watch('openLoginModal', () => {
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    });

                    this.$watch('openRegisterModal', () => {
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    });

                    this.$watch('changedFilterKecamatan', () => {
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    });

                    this.$watch('changedFilterDesa', () => {
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    });

                    // Load all regions across Kota Malang by default
                    await this.loadGeoJson();
                },

                initMap() {
                    this.map = L.map('map', {
                        zoomControl: false,
                    }).setView([-7.9839, 112.6214], 13);

                    L.control.zoom({ position: 'bottomright' }).addTo(this.map);

                    const satelliteImagery = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                        attribution: 'Tiles &copy; Esri',
                        maxZoom: 19
                    });

                    const satelliteLabels = L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
                        attribution: '',
                        maxZoom: 19
                    });

                    const satelliteGroup = L.layerGroup([satelliteImagery, satelliteLabels]);

                    const darkBasemap = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
                        subdomains: 'abcd',
                        maxZoom: 20
                    });

                    this.basemapLayers = {
                        satellite: satelliteGroup,
                        dark: darkBasemap
                    };

                    satelliteGroup.addTo(this.map);
                    this.crossNeighborLayerGroup = L.featureGroup().addTo(this.map);
                },

                async resetToCityView() {
                    this.filters.kecamatan = '';
                    this.filters.desa = '';
                    this.filters.rw = '';
                    this.desaOptions = [];
                    this.rwOptions = [];
                    this.rtList = [];
                    this.rtSearchQuery = '';
                    this.globalSearchQuery = '';
                    this.globalSearchResults = [];
                    this.showSearchDropdown = false;
                    this.clearHighlights();
                    await this.loadGeoJson();
                    this.map.setView([-7.9839, 112.6214], 13);
                },

                switchBasemap(name) {
                    if (this.activeBasemap === name) return;

                    if (this.basemapLayers[this.activeBasemap]) {
                        this.map.removeLayer(this.basemapLayers[this.activeBasemap]);
                    }

                    this.activeBasemap = name;
                    if (this.basemapLayers[name]) {
                        this.basemapLayers[name].addTo(this.map);
                        this.basemapLayers[name].bringToBack();
                    }

                    this.refreshMapStyles();
                },

                async onGlobalSearchInput() {
                    const q = this.globalSearchQuery.trim();
                    if (!q) {
                        this.globalSearchResults = [];
                        return;
                    }
                    try {
                        const res = await fetch(`/api/search?q=${encodeURIComponent(q)}`);
                        this.globalSearchResults = await res.json();
                        this.showSearchDropdown = true;
                    } catch (err) {
                        console.error('Search error:', err);
                    }
                },

                async onKecamatanChange() {
                    this.filters.desa = '';
                    this.filters.rw = '';
                    this.desaOptions = [];
                    this.rwOptions = [];
                    this.rtList = [];

                    if (this.filters.kecamatan) {
                        const res = await fetch(`/api/filter-options?kecamatan=${encodeURIComponent(this.filters.kecamatan)}`);
                        const data = await res.json();
                        this.desaOptions = data.desas || [];

                        await this.loadGeoJson();
                    }
                    this.$nextTick(() => lucide.createIcons());
                },

                async onDesaChange() {
                    this.filters.rw = '';
                    this.rwOptions = [];
                    this.rtList = [];

                    if (this.filters.desa) {
                        const res = await fetch(`/api/filter-options?kecamatan=${encodeURIComponent(this.filters.kecamatan)}&desa=${encodeURIComponent(this.filters.desa)}`);
                        const data = await res.json();
                        this.rwOptions = data.rws || [];

                        await this.loadGeoJson();
                    }
                    this.$nextTick(() => lucide.createIcons());
                },

                async onRwChange() {
                    this.rtList = [];
                    if (this.filters.desa) {
                        let url = `/api/filter-options?kecamatan=${encodeURIComponent(this.filters.kecamatan)}&desa=${encodeURIComponent(this.filters.desa)}`;
                        if (this.filters.rw) {
                            url += `&rw=${encodeURIComponent(this.filters.rw)}`;
                        }
                        const res = await fetch(url);
                        const data = await res.json();
                        this.rtList = data.rts || [];

                        await this.loadGeoJson();
                    }
                    this.$nextTick(() => lucide.createIcons());
                },

                resetFilter() {
                    this.filters.kecamatan = '';
                    this.filters.desa = '';
                    this.filters.rw = '';
                    this.desaOptions = [];
                    this.rwOptions = [];
                    this.rtList = [];
                    this.loadGeoJson();
                },

                async loadGeoJson() {
                    this.loadingMap = true;
                    try {
                        const params = new URLSearchParams();
                        if (this.filters.kecamatan) params.append('kecamatan', this.filters.kecamatan);
                        if (this.filters.desa) params.append('desa', this.filters.desa);
                        if (this.filters.rw) params.append('rw', this.filters.rw);

                        const res = await fetch(`/api/regions?${params.toString()}`);
                        const geojsonData = await res.json();

                        if (this.geoJsonLayer) {
                            this.map.removeLayer(this.geoJsonLayer);
                        }

                        if (geojsonData.features) {
                            geojsonData.features.forEach(f => {
                                if (f.properties.is_changed) {
                                    this.changedIdSet.add(f.properties.id);
                                }
                            });
                        }

                        const self = this;
                        this.geoJsonLayer = L.geoJSON(geojsonData, {
                            style: function (feature) {
                                return self.getPolygonStyle(feature.properties);
                            },
                            onEachFeature: function (feature, layer) {
                                const p = feature.properties;
                                const popupContent = `
                                    <div class="p-3">
                                        <div class="font-semibold text-sm" style="color:#93c5fd">${p.nmsls || p.nmsubsls}</div>
                                        <div style="font-size:11px;color:#888;font-family:monospace;margin-top:2px">${p.idsubsls}</div>
                                        <div style="font-size:12px;color:#ccc;margin-top:8px">Kel. ${p.nmdesa || '-'} · Kec. ${p.nmkec || '-'}</div>
                                    </div>
                                `;
                                layer.bindPopup(popupContent);

                                layer.on('click', function (e) {
                                    L.DomEvent.stopPropagation(e);
                                    self.selectRegionById(p.id);
                                });
                            }
                        }).addTo(this.map);

                        if (geojsonData.features && geojsonData.features.length > 0) {
                            this.map.fitBounds(this.geoJsonLayer.getBounds(), { padding: [40, 40] });
                        }
                    } catch (err) {
                        console.error('Error loading GeoJSON:', err);
                    } finally {
                        this.loadingMap = false;
                    }
                },

                getPolygonStyle(props) {
                    const regionId = props.id;
                    const isSelected = (this.selectedId === regionId);
                    const isNeighbor = this.neighborIdSet.has(regionId);
                    const isChanged = this.changedIdSet.has(regionId) || props.is_changed;

                    if (isSelected) {
                        return {
                            fillColor: '#3b82f6',
                            weight: 3,
                            opacity: 1,
                            color: '#60a5fa',
                            fillOpacity: 0.6
                        };
                    }

                    if (isNeighbor) {
                        return {
                            fillColor: '#06b6d4',
                            weight: 2,
                            opacity: 1,
                            color: '#22d3ee',
                            fillOpacity: 0.4,
                            dashArray: '5, 5'
                        };
                    }

                    if (isChanged) {
                        return {
                            fillColor: '#f59e0b',
                            weight: 2,
                            opacity: 0.9,
                            color: '#fbbf24',
                            fillOpacity: 0.5
                        };
                    }

                    if (this.activeBasemap === 'satellite') {
                        return {
                            fillColor: '#000000',
                            weight: 1,
                            opacity: 0.7,
                            color: '#ffffff',
                            fillOpacity: 0.08
                        };
                    }

                    return {
                        fillColor: '#525252',
                        weight: 1,
                        opacity: 0.6,
                        color: '#737373',
                        fillOpacity: 0.15
                    };
                },

                async selectRegionById(id) {
                    this.selectedId = id;
                    this.neighborIdSet.clear();

                    try {
                        const res = await fetch(`/api/regions/${id}`);
                        this.selectedRegion = await res.json();

                        if (this.selectedRegion.neighbors) {
                            this.selectedRegion.neighbors.forEach(n => {
                                this.neighborIdSet.add(n.id);
                                if (n.is_changed) {
                                    this.changedIdSet.add(n.id);
                                }
                            });
                        }

                        if (this.selectedRegion.is_changed) {
                            this.changedIdSet.add(id);
                        } else {
                            this.changedIdSet.delete(id);
                        }

                        this.refreshMapStyles();

                        if (this.crossNeighborLayerGroup) {
                            this.crossNeighborLayerGroup.clearLayers();

                            const currentLayerIds = new Set();
                            if (this.geoJsonLayer) {
                                this.geoJsonLayer.eachLayer(layer => {
                                    if (layer.feature && layer.feature.properties) {
                                        currentLayerIds.add(layer.feature.properties.id);
                                    }
                                });
                            }

                            const self = this;
                            if (this.selectedRegion.neighbors) {
                                this.selectedRegion.neighbors.forEach(n => {
                                    if (!currentLayerIds.has(n.id) && n.geometry) {
                                        const neighborFeature = {
                                            type: 'Feature',
                                            properties: {
                                                id: n.id,
                                                nmsls: n.nmsls,
                                                nmsubsls: n.nmsubsls,
                                                nmdesa: n.nmdesa,
                                                nmkec: n.nmkec,
                                                is_changed: n.is_changed,
                                                is_cross_border: true,
                                            },
                                            geometry: n.geometry
                                        };

                                        const crossLayer = L.geoJSON(neighborFeature, {
                                            style: {
                                                fillColor: '#06b6d4',
                                                weight: 2,
                                                opacity: 1,
                                                color: '#22d3ee',
                                                fillOpacity: 0.4,
                                                dashArray: '5, 5'
                                            },
                                            onEachFeature: function (f, lyr) {
                                                const p = f.properties;
                                                const crossPopup = `
                                                    <div class="p-3">
                                                        <div style="font-size:10px;color:#c084fc;margin-bottom:4px">Tetangga lintas kelurahan</div>
                                                        <div class="font-semibold text-sm" style="color:#67e8f9">${p.nmsls || p.nmsubsls}</div>
                                                        <div style="font-size:12px;color:#ccc;margin-top:4px">Kel. ${p.nmdesa || '-'} · Kec. ${p.nmkec || '-'}</div>
                                                    </div>
                                                `;
                                                lyr.bindPopup(crossPopup);
                                                lyr.on('click', function (e) {
                                                    L.DomEvent.stopPropagation(e);
                                                    self.jumpToRegion(p.id, p.nmkec, p.nmdesa);
                                                });
                                            }
                                        });

                                        self.crossNeighborLayerGroup.addLayer(crossLayer);
                                    }
                                });
                            }
                        }

                        if (this.geoJsonLayer) {
                            this.geoJsonLayer.eachLayer(layer => {
                                if (layer.feature && layer.feature.properties.id === id) {
                                    this.map.flyToBounds(layer.getBounds(), { maxZoom: 18, duration: 1.0, padding: [60, 60] });
                                    layer.openPopup();
                                }
                            });
                        }
                    } catch (err) {
                        console.error('Error selecting region:', err);
                    } finally {
                        this.$nextTick(() => lucide.createIcons());
                    }
                },

                refreshMapStyles() {
                    if (!this.geoJsonLayer) return;
                    const self = this;
                    this.geoJsonLayer.eachLayer(layer => {
                        if (layer.feature && layer.feature.properties) {
                            const style = self.getPolygonStyle(layer.feature.properties);
                            layer.setStyle(style);
                        }
                    });
                },

                clearHighlights() {
                    this.selectedRegion = null;
                    this.selectedId = null;
                    this.neighborIdSet.clear();
                    if (this.crossNeighborLayerGroup) {
                        this.crossNeighborLayerGroup.clearLayers();
                    }
                    this.refreshMapStyles();
                },

                async toggleChangeStatus(isChanged) {
                    if (!this.selectedRegion) return;
                    if (!this.isAuthenticated) {
                        this.openLoginModal = true;
                        this.notify('Silakan login terlebih dahulu untuk mengubah status RT.', 'info');
                        return;
                    }
                    this.savingStatus = true;

                    try {
                        let res;
                        if (isChanged) {
                            res = await fetch(`/api/regions/${this.selectedRegion.id}/change`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrfToken,
                                },
                                body: JSON.stringify({ notes: '' })
                            });
                        } else {
                            res = await fetch(`/api/regions/${this.selectedRegion.id}/change`, {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrfToken,
                                },
                                body: JSON.stringify({ cleanup_relations: false })
                            });
                        }

                        if (res.status === 401) {
                            this.isAuthenticated = false;
                            this.authUser = null;
                            this.openLoginModal = true;
                            this.notify('Sesi login telah berakhir. Silakan login kembali.', 'error');
                            return;
                        }

                        const data = await res.json();
                        if (data.success) {
                            this.selectedRegion.is_changed = isChanged;
                            this.selectedRegion.warnings = data.warnings || [];

                            if (isChanged) {
                                this.changedIdSet.add(this.selectedRegion.id);
                            } else {
                                this.changedIdSet.delete(this.selectedRegion.id);
                            }

                            const found = this.rtList.find(r => r.id === this.selectedRegion.id);
                            if (found) found.is_changed = isChanged;

                            if (this.geoJsonLayer) {
                                this.geoJsonLayer.eachLayer(layer => {
                                    if (layer.feature && layer.feature.properties.id === this.selectedRegion.id) {
                                        layer.feature.properties.is_changed = isChanged;
                                    }
                                });
                            }

                            this.refreshMapStyles();
                            await this.fetchValidationReport();
                            this.notify(isChanged ? 'RT berhasil ditandai berubah.' : 'Status RT berhasil direset.', 'success');
                        } else {
                            this.notify(data.message || 'Gagal mengubah status RT.', 'error');
                        }
                    } catch (err) {
                        console.error('Error toggling status:', err);
                        this.notify('Terjadi kesalahan jaringan.', 'error');
                    } finally {
                        this.savingStatus = false;
                        this.$nextTick(() => lucide.createIcons());
                    }
                },

                isRelationWith(neighborId) {
                    return this.selectedRegion &&
                           this.selectedRegion.related_region_ids &&
                           this.selectedRegion.related_region_ids.includes(neighborId);
                },

                async toggleRelation(neighborId, shouldRelate) {
                    if (!this.selectedRegion) return;
                    if (!this.isAuthenticated) {
                        this.openLoginModal = true;
                        this.notify('Silakan login terlebih dahulu untuk mengubah relasi.', 'info');
                        return;
                    }

                    try {
                        let res;
                        if (shouldRelate) {
                            res = await fetch(`/api/regions/${this.selectedRegion.id}/relations`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrfToken,
                                },
                                body: JSON.stringify({ target_region_id: neighborId })
                            });
                        } else {
                            res = await fetch(`/api/regions/${this.selectedRegion.id}/relations`, {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrfToken,
                                },
                                body: JSON.stringify({ target_region_id: neighborId })
                            });
                        }

                        if (res.status === 401) {
                            this.isAuthenticated = false;
                            this.authUser = null;
                            this.openLoginModal = true;
                            this.notify('Sesi login telah berakhir. Silakan login kembali.', 'error');
                            return;
                        }

                        const data = await res.json();
                        if (data.success) {
                            if (shouldRelate) {
                                if (!this.selectedRegion.related_region_ids.includes(neighborId)) {
                                    this.selectedRegion.related_region_ids.push(neighborId);
                                }
                            } else {
                                this.selectedRegion.related_region_ids = this.selectedRegion.related_region_ids.filter(id => id !== neighborId);
                            }

                            this.selectedRegion.warnings = data.warnings || [];
                            await this.fetchValidationReport();
                        } else {
                            alert(data.message || 'Gagal mengubah relasi');
                        }
                    } catch (err) {
                        console.error('Error toggling relation:', err);
                    } finally {
                        this.$nextTick(() => lucide.createIcons());
                    }
                },

                async fetchValidationReport() {
                    try {
                        const res = await fetch('/api/validation');
                        const data = await res.json();
                        this.stats = data.summary || this.stats;
                        this.validationWarnings = data.warnings || [];
                    } catch (err) {
                        console.error('Error fetching validation report:', err);
                    } finally {
                        await this.fetchChangedRegions();
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    }
                },

                async fetchChangedRegions() {
                    try {
                        const res = await fetch('/api/changed-regions');
                        const data = await res.json();
                        this.changedRegionsList = data.data || [];
                    } catch (err) {
                        console.error('Error fetching changed regions:', err);
                    } finally {
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    }
                },

                async jumpToRegion(regionId, kec, desa, rw) {
                    this.openValidationModal = false;
                    this.openChangedModal = false;

                    if (kec && this.filters.kecamatan !== kec) {
                        this.filters.kecamatan = kec;
                        await this.onKecamatanChange();
                    }
                    if (desa && this.filters.desa !== desa) {
                        this.filters.desa = desa;
                        await this.onDesaChange();
                    }
                    if (rw && this.filters.rw !== rw) {
                        this.filters.rw = rw;
                        await this.onRwChange();
                    }

                    await this.selectRegionById(regionId);
                },

                exportWarningsCsv() {
                    if (!this.validationWarnings || this.validationWarnings.length === 0) {
                        alert('Tidak ada data warning untuk diexport.');
                        return;
                    }

                    // Headers: ID SLS, Kecamatan, Kelurahan, RW, RT, Status, Dengan ID SLS, Dengan RW, Dengan RT, Dengan Kelurahan, Warning Message
                    const headers = ['ID SLS', 'Kecamatan', 'Kelurahan', 'RW', 'RT', 'Status', 'Dengan ID SLS', 'Dengan RW', 'Dengan RT', 'Dengan Kelurahan', 'Warning Message'];
                    const rows = this.validationWarnings.map(w => {
                        const idsls = (w.idsubsls_a || w.idsls_a || '').toString().replace(/"/g, '""');
                        const kec = (w.kec_a || '').toString().replace(/"/g, '""');
                        const desa = (w.desa_a || '').toString().replace(/"/g, '""');
                        const rw = (w.rw_a || '').toString().replace(/"/g, '""');
                        const rt = (w.rt_a || '').toString().replace(/"/g, '""');

                        let statusText = 'Warning';
                        if (w.type === 'unreciprocated_relation') statusText = 'Pasangan Belum Cocok';
                        else if (w.type === 'no_relations_selected') statusText = 'Belum Pilih Pasangan';
                        else if (w.type === 'partner_not_changed') statusText = 'Pasangan Belum Berubah';
                        else if (w.type === 'self_not_changed') statusText = 'Wilayah Belum Berubah';
                        else if (w.type === 'both_not_changed') statusText = 'Kedua Wilayah Belum Berubah';

                        const idslsB = (w.idsubsls_b || w.idsls_b || '-').toString().replace(/"/g, '""');
                        const rwB = (w.rw_b || '-').toString().replace(/"/g, '""');
                        const rtB = (w.rt_b || '-').toString().replace(/"/g, '""');
                        const desaB = (w.desa_b || '-').toString().replace(/"/g, '""');
                        const msg = (w.message || '').toString().replace(/"/g, '""');

                        return `"${idsls}","${kec}","${desa}","${rw}","${rt}","${statusText}","${idslsB}","${rwB}","${rtB}","${desaB}","${msg}"`;
                    });

                    const csvContent = "\uFEFF" + [headers.join(','), ...rows].join("\r\n");
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.setAttribute('href', url);
                    link.setAttribute('download', `warning_validasi_geoma_${new Date().toISOString().slice(0, 10)}.csv`);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                },

                exportChangedCsv() {
                    if (!this.changedRegionsList || this.changedRegionsList.length === 0) {
                        alert('Tidak ada data perubahan untuk diexport.');
                        return;
                    }

                    // Headers: ID SLS, Kecamatan, Kelurahan, RT RW Berubah, RT RW Berubah Dengan, Status
                    const headers = ['ID SLS', 'Kecamatan', 'Kelurahan', 'RT RW Berubah', 'RT RW Berubah Dengan', 'Status'];
                    const rows = this.changedRegionsList.map(item => {
                        const idsls = (item.idsubsls || item.idsls || '').toString().replace(/"/g, '""');
                        const kec = (item.nmkec || '').toString().replace(/"/g, '""');
                        const desa = (item.nmdesa || '').toString().replace(/"/g, '""');

                        const rtRwBerubah = (item.nmsls || item.nmsubsls || ('RT ' + (item.rt || '-') + ' RW ' + (item.rw || '-'))).toString().replace(/"/g, '""');

                        let berubahDengan = '-';
                        if (item.partners && item.partners.length > 0) {
                            const partnerNames = item.partners.map(p => {
                                let pName = p.name || ('RT ' + (p.rt || '-') + ' RW ' + (p.rw || '-'));
                                if (p.nmdesa && p.nmdesa !== item.nmdesa) {
                                    pName += ` [Kel. ${p.nmdesa}]`;
                                }
                                return pName;
                            });
                            berubahDengan = partnerNames.join('; ').replace(/"/g, '""');
                        }

                        let statusText = 'Berubah';
                        if (item.status === 'valid') statusText = 'Lengkap (2 Arah)';
                        else if (item.status === 'no_relations') statusText = 'Belum Pilih Pasangan';
                        else if (item.status === 'partner_not_marked') statusText = 'Pasangan Belum Ditandai';
                        else if (item.status === 'pending_reciprocal') statusText = 'Menunggu Timbal Balik (1 Arah)';

                        return `"${idsls}","${kec}","${desa}","${rtRwBerubah}","${berubahDengan}","${statusText}"`;
                    });

                    const csvContent = "\uFEFF" + [headers.join(','), ...rows].join("\r\n");
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.setAttribute('href', url);
                    link.setAttribute('download', `wilayah_berubah_geoma_${new Date().toISOString().slice(0, 10)}.csv`);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                },

                notify(message, type = 'info') {
                    this.toastMessage = message;
                    this.toastType = type;
                    this.showToast = true;
                    if (this.toastTimeout) clearTimeout(this.toastTimeout);
                    this.toastTimeout = setTimeout(() => {
                        this.showToast = false;
                    }, 4000);
                    this.$nextTick(() => {
                        if (window.lucide) lucide.createIcons();
                    });
                },

                async login() {
                    this.loginLoading = true;
                    this.loginError = '';

                    try {
                        const res = await fetch('/api/auth/login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify(this.loginForm)
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.isAuthenticated = true;
                            this.authUser = data.user;
                            this.openLoginModal = false;
                            this.loginForm = { username: '', password: '' };
                            this.notify(`Selamat datang kembali, @${data.user.username}!`, 'success');
                        } else {
                            this.loginError = data.message || 'Login gagal. Periksa username dan password.';
                        }
                    } catch (err) {
                        console.error('Error logging in:', err);
                        this.loginError = 'Terjadi kesalahan saat menghubung ke server.';
                    } finally {
                        this.loginLoading = false;
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    }
                },

                async logout() {
                    try {
                        const res = await fetch('/api/auth/logout', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            }
                        });
                        const data = await res.json();
                        if (data && data.csrf_token) {
                            this.csrfToken = data.csrf_token;
                            const meta = document.querySelector('meta[name="csrf-token"]');
                            if (meta) meta.setAttribute('content', data.csrf_token);
                        }
                    } catch (err) {
                        console.error('Error logging out:', err);
                    } finally {
                        this.isAuthenticated = false;
                        this.authUser = null;
                        this.notify('Anda telah logout dari mode editor.', 'info');
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    }
                },

                async registerUser() {
                    this.registerLoading = true;
                    this.registerError = '';
                    this.registerSuccess = '';

                    try {
                        const res = await fetch('/api/auth/register', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                            },
                            body: JSON.stringify(this.registerForm)
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            const newUsername = data.user.username;
                            this.registerSuccess = `Akun @${newUsername} berhasil dibuat.`;
                            this.registerForm = { username: '', name: '', password: '' };
                            this.notify(`Akun baru @${newUsername} berhasil ditambahkan!`, 'success');
                            setTimeout(() => {
                                this.openRegisterModal = false;
                                this.registerSuccess = '';
                            }, 1800);
                        } else {
                            if (data.errors) {
                                const firstKey = Object.keys(data.errors)[0];
                                this.registerError = data.errors[firstKey][0] || data.message;
                            } else {
                                this.registerError = data.message || 'Gagal menambahkan akun.';
                            }
                        }
                    } catch (err) {
                        console.error('Error registering user:', err);
                        this.registerError = 'Terjadi kesalahan koneksi server.';
                    } finally {
                        this.registerLoading = false;
                        this.$nextTick(() => {
                            if (window.lucide) lucide.createIcons();
                        });
                    }
                }
            }
        }
    </script>

</body>
</html>
