<template>
    <AppLayout title="ESP32 Dashboard">
        <div class="min-h-screen bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 dark:from-gray-900 dark:via-gray-800 dark:to-gray-700">
            <!-- Header Section -->
            <div class="px-6 pt-8">
                <div class="mx-auto max-w-7xl">
                    <!-- Alert Component -->
                    <div v-if="showAlert" class="mb-6 relative overflow-hidden rounded-2xl border p-4 transition-all duration-300" :class="alertClasses">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0">
                                <svg v-if="alertType === 'info'" class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg v-if="alertType === 'success'" class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg v-if="alertType === 'error'" class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium" :class="alertTextClasses">{{ alertMessage }}</p>
                            </div>
                            <button @click="showAlert = false" class="flex-shrink-0 rounded-full p-1 transition-colors hover:bg-gray-100 dark:hover:bg-gray-800">
                                <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-white/60 p-6 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/60">
                        <div class="absolute inset-0 bg-gradient-to-r from-gray-500/5 via-gray-400/5 to-gray-600/5"></div>
                        <div class="relative flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="relative">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-gray-700 to-gray-900 shadow-lg">
                                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                        </svg>
                                    </div>
                                    <div class="absolute -bottom-1 -right-1 h-4 w-4 rounded-full ring-2 ring-white animate-pulse" :class="isConnected ? 'bg-green-400' : 'bg-red-400'"></div>
                                </div>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.3em] text-gray-600 dark:text-gray-400">
                                        ESP32 Dashboard
                                    </p>
                                    <p class="text-lg font-semibold bg-gradient-to-r from-slate-900 to-slate-600 bg-clip-text text-transparent dark:from-white dark:to-slate-300">Smart Home Control</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2 text-xs">
                                    <div class="flex items-center gap-2 rounded-full border border-white/40 bg-white/50 px-4 py-2 backdrop-blur-sm dark:border-slate-600/50 dark:bg-slate-700/50">
                                        <div class="h-2 w-2 rounded-full animate-pulse" :class="isConnected ? 'bg-green-500' : 'bg-red-500'"></div>
                                        <span class="font-medium" :class="isConnected ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'">
                                            {{ isConnected ? 'Connected' : 'Disconnected' }}
                                        </span>
                                    </div>
                                </div>
                                <div class="rounded-full border border-white/40 bg-white/50 px-4 py-2 backdrop-blur-sm dark:border-slate-600/50 dark:bg-slate-700/50">
                                    <span class="text-xs text-slate-600 dark:text-slate-400">Last sync: {{ lastUpdate }}</span>
                                </div>
                                <!-- Settings Dropdown -->
                                <div class="relative group">
                                    <button class="rounded-full border border-white/40 bg-white/50 p-2 backdrop-blur-sm transition-colors hover:bg-white/70 dark:border-slate-600/50 dark:bg-slate-700/50 dark:hover:bg-slate-700/70">
                                        <svg class="h-5 w-5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                    <!-- Dropdown Menu -->
                                    <div class="absolute right-0 top-12 w-48 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform translate-y-1 group-hover:translate-y-0">
                                        <div class="rounded-2xl border border-white/20 bg-white/90 p-2 backdrop-blur-xl shadow-xl dark:border-gray-700/50 dark:bg-gray-800/90">
                                            <button @click="refreshData" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                Refresh Data
                                            </button>
                                            <button @click="exportData" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                Export Data
                                            </button>
                                            <hr class="my-2 border-gray-200 dark:border-gray-600" />
                                            <button @click="showAlert = true; alertType = 'info'; alertMessage = 'Dashboard settings opened'" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
                                                </svg>
                                                Settings
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <!-- Settings Dropdown -->
                                <div class="relative group">
                                    <button class="rounded-full border border-white/40 bg-white/50 p-2 backdrop-blur-sm transition-colors hover:bg-white/70 dark:border-slate-600/50 dark:bg-slate-700/50 dark:hover:bg-slate-700/70">
                                        <svg class="h-5 w-5 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                    <!-- Dropdown Menu -->
                                    <div class="absolute right-0 top-12 w-48 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform translate-y-1 group-hover:translate-y-0">
                                        <div class="rounded-2xl border border-white/20 bg-white/90 p-2 backdrop-blur-xl shadow-xl dark:border-gray-700/50 dark:bg-gray-800/90">
                                            <button @click="refreshData" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                Refresh Data
                                            </button>
                                            <button @click="exportData" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                Export Data
                                            </button>
                                            <hr class="my-2 border-gray-200 dark:border-gray-600" />
                                            <button @click="showAlert = true; alertType = 'info'; alertMessage = 'Dashboard settings opened'" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
                                                </svg>
                                                Settings
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabs Navigation -->
                    <div class="mt-6">
                        <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-white/40 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/40">
                            <div class="flex p-1">
                                <button 
                                    @click="selectedTab = 'overview'"
                                    class="flex-1 relative rounded-2xl px-6 py-3 text-sm font-medium transition-all duration-200"
                                    :class="selectedTab === 'overview' ? 'bg-white text-gray-900 shadow-lg dark:bg-gray-700 dark:text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-white/50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700/50'"
                                >
                                    <div class="flex items-center justify-center gap-2">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                        </svg>
                                        <span>Overview</span>
                                    </div>
                                </button>
                                <button 
                                    @click="selectedTab = 'analytics'"
                                    class="flex-1 relative rounded-2xl px-6 py-3 text-sm font-medium transition-all duration-200"
                                    :class="selectedTab === 'analytics' ? 'bg-white text-gray-900 shadow-lg dark:bg-gray-700 dark:text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-white/50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700/50'"
                                >
                                    <div class="flex items-center justify-center gap-2">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        <span>Analytics</span>
                                    </div>
                                </button>
                                <button 
                                    @click="selectedTab = 'devices'"
                                    class="flex-1 relative rounded-2xl px-6 py-3 text-sm font-medium transition-all duration-200"
                                    :class="selectedTab === 'devices' ? 'bg-white text-gray-900 shadow-lg dark:bg-gray-700 dark:text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-white/50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700/50'"
                                >
                                    <div class="flex items-center justify-center gap-2">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        <span>Devices</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Content -->
            <div class="px-6 py-8">
                <div class="mx-auto max-w-7xl">
                    
                    <!-- Overview Tab -->
                    <div v-if="selectedTab === 'overview'" class="space-y-6">
                        <div class="grid gap-6 lg:grid-cols-3"></div>
                    </div>
                    
                    <!-- Analytics Tab -->
                    <div v-else-if="selectedTab === 'analytics'" class="space-y-6">
                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- Advanced Chart -->
                            <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-white/60 p-6 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/60">
                                <div class="absolute inset-0 bg-gradient-to-br from-gray-500/10 via-gray-400/5 to-gray-600/10"></div>
                                <div class="relative">
                                    <h3 class="text-xl font-semibold text-slate-900 dark:text-white mb-6">Energy Consumption</h3>
                                    <div class="h-64 relative">
                                        <!-- Chart SVG -->
                                        <svg class="w-full h-full" viewBox="0 0 400 200">
                                            <!-- Grid lines -->
                                            <defs>
                                                <pattern id="grid" width="40" height="20" patternUnits="userSpaceOnUse">
                                                    <path d="M 40 0 L 0 0 0 20" fill="none" stroke="rgb(156 163 175)" stroke-width="0.5" opacity="0.3"/>
                                                </pattern>
                                            </defs>
                                            <rect width="100%" height="100%" fill="url(#grid)" />
                                            
                                            <!-- Chart line -->
                                            <polyline
                                                fill="none"
                                                stroke="rgb(107 114 128)"
                                                stroke-width="3"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                :points="chartData.map((value, index) => `${(index * 50) + 25},${180 - (value * 2)}`).join(' ')"
                                            />
                                            
                                            <!-- Data points -->
                                            <circle 
                                                v-for="(value, index) in chartData" 
                                                :key="index"
                                                :cx="(index * 50) + 25" 
                                                :cy="180 - (value * 2)" 
                                                r="4" 
                                                fill="rgb(75 85 99)" 
                                                class="cursor-pointer hover:r-6 transition-all"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Devices Tab -->
                    <div v-else-if="selectedTab === 'devices'">
                        <div class="text-center py-12">
                            <div class="mx-auto h-12 w-12 text-gray-400">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Device Management</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Advanced device controls coming soon</p>
                        </div>
                    </div>
                </div>
            </div>"

            <!-- Tab Content -->
            <div class="px-6 py-8">
                <div class="mx-auto max-w-7xl">
                    
                    <!-- Overview Tab -->
                    <div v-if="selectedTab === 'overview'" class="space-y-6">
                        <div class="grid gap-6 lg:grid-cols-3">
                            
                            <!-- Power & Energy Analytics -->
                            <div class="lg:col-span-2">
                                <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-white/60 p-6 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/60">
                                    <div class="absolute inset-0 bg-gradient-to-br from-gray-500/10 via-gray-400/5 to-gray-600/10"></div>
                                    <div class="relative">
                                        <div class="flex items-center justify-between mb-6">
                                            <div>
                                                <h2 class="text-xl font-semibold bg-gradient-to-r from-gray-800 to-gray-900 bg-clip-text text-transparent dark:from-white dark:to-gray-300">Power Analytics</h2>
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Real-time energy monitoring</p>
                                            </div>
                                            <div class="flex items-center gap-2 rounded-xl bg-gray-100/80 px-3 py-1.5 dark:bg-gray-700/30">
                                                <div class="h-2 w-2 rounded-full bg-gray-500 animate-pulse"></div>
                                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Live Data</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Skeleton Loading for Power Cards -->
                                        <div v-if="isLoading" class="grid gap-6 md:grid-cols-3">
                                            <div v-for="n in 3" :key="n" class="animate-pulse">
                                                <div class="rounded-2xl border border-gray-200 bg-gray-100 p-5 dark:border-gray-700 dark:bg-gray-800">
                                                    <div class="flex items-center justify-between mb-3">
                                                        <div class="h-10 w-10 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
                                                        <div class="h-4 w-16 rounded bg-gray-200 dark:bg-gray-700"></div>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <div class="h-8 w-16 rounded bg-gray-200 dark:bg-gray-700"></div>
                                                        <div class="h-4 w-12 rounded bg-gray-200 dark:bg-gray-700"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Actual Power Cards -->
                                        <div v-else class="grid gap-6 md:grid-cols-3">
                                        <!-- Power Card -->
                                        <div class="group relative overflow-hidden rounded-2xl border border-white/40 bg-white/80 p-5 transition-all hover:scale-105 hover:shadow-xl dark:border-gray-600/50 dark:bg-gray-700/60">
                                            <div class="absolute inset-0 bg-gradient-to-br from-gray-500/5 to-gray-600/10 opacity-0 transition-opacity group-hover:opacity-100"></div>
                                            <div class="relative">
                                                <div class="flex items-center justify-between">
                                                    <div class="rounded-xl bg-gray-100 p-2.5 dark:bg-gray-800/30">
                                                        <svg class="h-5 w-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                        </svg>
                                                    </div>
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">Instant Power</span>
                                                </div>
                                                <div class="mt-3">
                                                    <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ power }}</div>
                                                    <div class="text-sm text-slate-600 dark:text-slate-400">Watts</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Voltage Card -->
                                        <div class="group relative overflow-hidden rounded-2xl border border-white/40 bg-white/80 p-5 transition-all hover:scale-105 hover:shadow-xl dark:border-gray-600/50 dark:bg-gray-700/60">
                                            <div class="absolute inset-0 bg-gradient-to-br from-gray-400/5 to-gray-500/10 opacity-0 transition-opacity group-hover:opacity-100"></div>
                                            <div class="relative">
                                                <div class="flex items-center justify-between">
                                                    <div class="rounded-xl bg-gray-200 p-2.5 dark:bg-gray-800/30">
                                                        <svg class="h-5 w-5 text-gray-700 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                                                        </svg>
                                                    </div>
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">Voltage</span>
                                                </div>
                                                <div class="mt-3">
                                                    <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ voltage }}</div>
                                                    <div class="text-sm text-slate-600 dark:text-slate-400">Volts</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Current Card -->
                                        <div class="group relative overflow-hidden rounded-2xl border border-white/40 bg-white/80 p-5 transition-all hover:scale-105 hover:shadow-xl dark:border-gray-600/50 dark:bg-gray-700/60">
                                            <div class="absolute inset-0 bg-gradient-to-br from-gray-400/5 to-gray-600/10 opacity-0 transition-opacity group-hover:opacity-100"></div>
                                            <div class="relative">
                                                <div class="flex items-center justify-between">
                                                    <div class="rounded-xl bg-gray-300 p-2.5 dark:bg-gray-800/30">
                                                        <svg class="h-5 w-5 text-gray-800 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                        </svg>
                                                    </div>
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">Current</span>
                                                </div>
                                                <div class="mt-3">
                                                    <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ current }}</div>
                                                    <div class="text-sm text-slate-600 dark:text-slate-400">Amperes</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Status -->
                        <div class="space-y-6">
                            <!-- Connection Status -->
                            <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-white/60 p-6 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/60">
                                <div class="absolute inset-0 bg-gradient-to-br from-gray-500/10 via-gray-400/5 to-gray-600/10"></div>
                                <div class="relative">
                                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">System Status</h3>
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-slate-600 dark:text-slate-400">Connection</span>
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 w-2 rounded-full animate-pulse" :class="isConnected ? 'bg-green-500' : 'bg-red-500'"></div>
                                                <span class="text-sm font-medium" :class="isConnected ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'">
                                                    {{ isConnected ? 'Online' : 'Offline' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-slate-600 dark:text-slate-400">Active Devices</span>
                                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ activeRelaysCount }}/3</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-slate-600 dark:text-slate-400">Update Rate</span>
                                            <span class="text-sm font-medium text-slate-900 dark:text-white">2s</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Stats -->
                            <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-white/60 p-6 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/60">
                                <div class="absolute inset-0 bg-gradient-to-br from-gray-500/10 via-gray-400/5 to-gray-600/10"></div>
                                <div class="relative">
                                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Today's Summary</h3>
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-slate-600 dark:text-slate-400">Energy Consumed</span>
                                            <span class="text-lg font-semibold bg-gradient-to-r from-gray-800 to-gray-900 bg-clip-text text-transparent dark:from-white dark:to-gray-300">{{ energy }} kWh</span>
                                        </div>
                                        <div class="h-2 rounded-full bg-gradient-to-r from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-800">
                                            <div class="h-full w-3/4 rounded-full bg-gradient-to-r from-gray-600 to-gray-800"></div>
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">75% of daily target</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Smart Relay Controls -->
            <div class="px-6 pb-12">
                <div class="mx-auto max-w-7xl">
                    <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-white/60 p-6 backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/60">
                        <div class="absolute inset-0 bg-gradient-to-br from-gray-500/5 via-gray-400/5 to-gray-600/5"></div>
                        <div class="relative">
                            <div class="mb-6">
                                <h2 class="text-2xl font-semibold bg-gradient-to-r from-gray-800 to-gray-900 bg-clip-text text-transparent dark:from-white dark:to-gray-300">Smart Relay Control</h2>
                                <p class="text-slate-600 dark:text-slate-400 mt-1">Manage your connected devices with real-time control</p>
                            </div>
                            
                            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                <div
                                    v-for="relayItem in relayCards"
                                    :key="relayItem.id"
                                    class="group relative overflow-hidden rounded-3xl border border-white/40 bg-white/80 p-6 transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl dark:border-gray-600/50 dark:bg-gray-700/60"
                                >
                                    <!-- Card Background Effects -->
                                    <div class="absolute inset-0 bg-gradient-to-br from-gray-500/5 via-gray-400/5 to-gray-600/5 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>
                                    <div class="absolute -top-12 -right-12 h-24 w-24 rounded-full bg-gradient-to-br from-gray-400/20 to-gray-500/20 blur-xl transition-transform duration-300 group-hover:scale-150"></div>
                                    
                                    <div class="relative">
                                        <!-- Header -->
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center gap-3">
                                                <div class="rounded-2xl bg-gradient-to-br from-gray-100 to-gray-200 p-3 dark:from-gray-800/30 dark:to-gray-700/30">
                                                    <svg class="h-6 w-6 text-gray-700 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Device {{ relayItem.id }}</div>
                                                    <div class="text-lg font-semibold text-slate-900 dark:text-white">{{ relayItem.title }}</div>
                                                </div>
                                            </div>
                                            
                                            <!-- Status Indicator -->
                                            <div class="flex items-center gap-2">
                                                <div class="rounded-xl px-3 py-1 text-xs font-medium transition-colors duration-200" :class="relayStatuses[relayItem.id] === 'PORNIT' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700/50 dark:text-gray-400'">
                                                    {{ relayStatuses[relayItem.id] }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Device Info -->
                                        <div class="mt-4">
                                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ relayItem.meta }}</p>
                                        </div>

                                        <!-- Power Usage Visualization -->
                                        <div class="mt-4">
                                            <div class="rounded-2xl bg-gradient-to-r from-slate-100 to-slate-50 p-4 dark:from-slate-800/50 dark:to-slate-700/50">
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="text-slate-600 dark:text-slate-400">Current Draw</span>
                                                    <span class="font-semibold text-slate-900 dark:text-white">{{ current }} A</span>
                                                </div>
                                                <div class="mt-3 h-2 rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div class="h-full rounded-full transition-all duration-500" :class="relayStatuses[relayItem.id] === 'PORNIT' ? 'bg-gradient-to-r from-green-500 to-green-600' : 'bg-gradient-to-r from-gray-300 to-gray-400'"
                                                         :style="`width: ${relayStatuses[relayItem.id] === 'PORNIT' ? (parseFloat(current) * 20) : 0}%`"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Control Toggle -->
                                        <div class="mt-6 flex items-center justify-between">
                                            <div class="text-sm font-medium text-slate-900 dark:text-white">
                                                {{ relayStatuses[relayItem.id] === 'PORNIT' ? 'Device Active' : 'Device Inactive' }}
                                            </div>
                                            
                                            <!-- Modern Toggle Switch -->
                                            <button
                                                class="relative h-8 w-14 rounded-full transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                                :class="relayStatuses[relayItem.id] === 'PORNIT'
                                                    ? 'bg-gradient-to-r from-green-500 to-green-600 shadow-lg shadow-green-500/25 focus:ring-green-500/50'
                                                    : 'bg-gradient-to-r from-gray-300 to-gray-400 dark:from-gray-600 dark:to-gray-700 focus:ring-gray-500/50'"
                                                :disabled="relayBusy"
                                                @click="toggleRelay(relayItem.id, relayStatuses[relayItem.id] === 'PORNIT' ? 'off' : 'on')"
                                            >
                                                <span
                                                    class="absolute top-1 h-6 w-6 rounded-full bg-white shadow-md transition-all duration-300 flex items-center justify-center"
                                                    :class="relayStatuses[relayItem.id] === 'PORNIT' ? 'left-7 shadow-lg' : 'left-1'"
                                                >
                                                    <div class="h-2 w-2 rounded-full transition-colors duration-300"
                                                         :class="relayStatuses[relayItem.id] === 'PORNIT' ? 'bg-green-600' : 'bg-gray-400'">
                                                    </div>
                                                </span>
                                            </button>
                                        </div>

                                        <!-- Loading State -->
                                        <div v-if="relayBusy && (relayStatuses[relayItem.id] === 'Pornesc...' || relayStatuses[relayItem.id] === 'Opresc...')" 
                                             class="absolute inset-0 flex items-center justify-center bg-white/80 backdrop-blur-sm rounded-3xl dark:bg-gray-800/80">
                                            <div class="flex items-center gap-3">
                                                <div class="h-5 w-5 animate-spin rounded-full border-2 border-gray-500/30 border-t-gray-500"></div>
                                                <span class="text-sm font-medium text-slate-900 dark:text-white">Processing...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import axios from 'axios'

// Shadcn-vue inspired components data
const selectedTab = ref('overview')
const showAlert = ref(false)
const alertMessage = ref('')
const alertType = ref('info')
const chartData = ref([10, 25, 15, 40, 30, 45, 35, 55])
const isLoading = ref(true)

// Data
const rawData = ref({})
const lastUpdate = ref('Never')
const updateInterval = ref(null)
const isConnected = ref(false)

// Computed values
const voltage = computed(() => rawData.value.voltage?.toFixed(2) || '0.00')
const current = computed(() => rawData.value.current?.toFixed(2) || '0.00')
const power = computed(() => rawData.value.power?.toFixed(2) || '0.00')
const relayState = computed(() => ({
    1: Boolean(rawData.value.relay_1),
    2: Boolean(rawData.value.relay_2),
    3: Boolean(rawData.value.relay_3),
}))
const energy = computed(() => rawData.value.energy?.toFixed(2) || '0.00')

// Alert styling computed property
const alertClasses = computed(() => {
    if (alertType.value === 'info') {
        return 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/50'
    } else if (alertType.value === 'success') {
        return 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/50'
    } else if (alertType.value === 'error') {
        return 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/50'
    }
    return ''
})

const alertTextClasses = computed(() => {
    if (alertType.value === 'info') {
        return 'text-blue-900 dark:text-blue-200'
    } else if (alertType.value === 'success') {
        return 'text-green-900 dark:text-green-200'
    } else if (alertType.value === 'error') {
        return 'text-red-900 dark:text-red-200'
    }
    return ''
})

// New computed property for active relays count
const activeRelaysCount = computed(() => {
    return Object.values(relayState.value).filter(Boolean).length
})

const relayStatuses = ref({
    1: 'Standby',
    2: 'Standby',
    3: 'Standby',
})
const relayBusy = ref(false)
const relayCards = [
    { id: 1, title: 'Living Room', meta: 'Priza A · 220V' },
    { id: 2, title: 'Kitchen Circuit', meta: 'Priza B · 220V' },
    { id: 3, title: 'Office Desk', meta: 'Priza C · 220V' },
]

// Methods
const fetchData = async () => {
    try {
        isLoading.value = true
        const response = await axios.get('/api/latest')
        rawData.value = response.data
        lastUpdate.value = new Date().toLocaleTimeString()
        isConnected.value = true
        relayCards.forEach((relay) => {
            relayStatuses.value[relay.id] = relayState.value[relay.id] ? 'PORNIT' : 'OPRIT'
        })
        
        // Update chart data with some randomness for demo
        chartData.value = chartData.value.map(val => Math.max(10, Math.min(60, val + (Math.random() - 0.5) * 10)))
        
        setTimeout(() => {
            isLoading.value = false
        }, 1000)
    } catch (error) {
        console.error('Failed to fetch data:', error)
        isConnected.value = false
        isLoading.value = false
        showAlert.value = true
        alertType.value = 'error'
        alertMessage.value = 'Failed to connect to ESP32 device'
    }
}

const refreshData = () => {
    showAlert.value = true
    alertType.value = 'info'
    alertMessage.value = 'Refreshing data...'
    fetchData()
}

const exportData = () => {
    const data = {
        timestamp: new Date().toISOString(),
        power: power.value,
        voltage: voltage.value,
        current: current.value,
        energy: energy.value,
        relays: relayState.value
    }
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `esp32-data-${Date.now()}.json`
    a.click()
    URL.revokeObjectURL(url)
    
    showAlert.value = true
    alertType.value = 'success'
    alertMessage.value = 'Data exported successfully'
}

const toggleRelay = async (relayId, state) => {
    if (relayBusy.value) {
        return
    }

    try {
        relayBusy.value = true
        relayStatuses.value[relayId] = state === 'on' ? 'Pornesc...' : 'Opresc...'
        await axios.get(`/api/relay/${relayId}/${state}`)
        if (!rawData.value) {
            rawData.value = {}
        }
        rawData.value[`relay_${relayId}`] = state === 'on'
        relayStatuses.value[relayId] = state === 'on' ? 'PORNIT' : 'OPRIT'
        
        // Show success notification
        showAlert.value = true
        alertType.value = 'success'
        alertMessage.value = `Device ${relayId} ${state === 'on' ? 'activated' : 'deactivated'} successfully`
    } catch (error) {
        console.error('Failed to toggle relay:', error)
        relayStatuses.value[relayId] = 'Eroare'
        
        // Show error notification
        showAlert.value = true
        alertType.value = 'error'
        alertMessage.value = `Failed to control device ${relayId}`
    } finally {
        relayBusy.value = false
    }
}

// Lifecycle
onMounted(() => {
    fetchData()
    updateInterval.value = setInterval(fetchData, 5000) // Update every 5 seconds
    
    // Show welcome message
    setTimeout(() => {
        showAlert.value = true
        alertType.value = 'info'
        alertMessage.value = 'ESP32 Dashboard loaded successfully'
    }, 2000)
})

onUnmounted(() => {
    if (updateInterval.value) {
        clearInterval(updateInterval.value)
    }
})
</script>
