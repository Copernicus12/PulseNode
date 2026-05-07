<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import BrandLogoImage from '@/components/BrandLogoImage.vue'
import ThemeModeSwitcher from '@/components/ThemeModeSwitcher.vue'
import { dashboard, login, register } from '@/routes'

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        telemetry: {
            voltage: number;
            power: number;
            energy_total: number;
            active_relays: number;
        };
        energyUsage: {
            week: { date: string, day_short: string, total: number }[];
            today_progress_kwh: number;
        };
        isOnline: boolean;
        stats: {
            users: number;
            devices: number;
            detections: number;
        };
    }>(),
    {
        canRegister: true,
    },
)

const sparklinePoints = computed(() => {
    const week = props.energyUsage?.week || [];
    if (week.length === 0) return 'M0,150 L800,150';
    
    const totals = week.map(d => d.total);
    const maxVal = Math.max(...totals, 0.1); 
    const minVal = 0;
    
    // Create soft curve path points
    const points = totals.map((val, i) => {
        const x = (800 / Math.max(week.length - 1, 1)) * i;
        // Y goes from 250 (bottom) to 100 (top)
        const y = 250 - ((val - minVal) / maxVal) * 150;
        return { x, y };
    });
    
    // Simple smooth curve calculation
    let path = `M ${points[0].x} ${points[0].y}`;
    for (let i = 1; i < points.length - 2; i++) {
        const xc = (points[i].x + points[i+1].x) / 2;
        const yc = (points[i].y + points[i+1].y) / 2;
        path += ` Q ${points[i].x} ${points[i].y}, ${xc} ${yc}`;
    }
    // Connect to the last two points
    if (points.length > 2) {
        path += ` Q ${points[points.length-2].x} ${points[points.length-2].y}, ${points[points.length-1].x} ${points[points.length-1].y}`;
    } else if (points.length === 2) {
        path += ` L ${points[1].x} ${points[1].y}`;
    }

    return path;
});

const sparklineFill = computed(() => {
    const week = props.energyUsage?.week || [];
    if (week.length === 0) return '';
    return `${sparklinePoints.value} L 800 300 L 0 300 Z`;
});

const thisWeekTotal = computed(() => {
    return (props.energyUsage?.week || []).reduce((acc, curr) => acc + curr.total, 0);
});

</script>

<template>
    <Head title="PulseNode - Smart Home Control" />

    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 text-slate-800 dark:from-[#232323] dark:via-[#1f1f1f] dark:to-[#171717] dark:text-slate-200 selection:bg-emerald-500/30 font-sans transition-colors duration-300">
        <!-- Background Grid Pattern -->
        <div class="absolute inset-0 w-full overflow-hidden">
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#00000009_1px,transparent_1px),linear-gradient(to_bottom,#00000009_1px,transparent_1px)] dark:bg-[linear-gradient(to_right,#ffffff05_1px,transparent_1px),linear-gradient(to_bottom,#ffffff05_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_110%)]"></div>
        </div>
        
        <div class="relative mx-auto flex w-full max-w-6xl flex-col px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <header class="mt-8 rounded-full border border-slate-200/60 dark:border-gray-700/50 bg-white/70 dark:bg-gray-700/60 py-3 px-6 shadow-md dark:shadow-xl backdrop-blur-xl">
                <nav class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-white shadow-[0_0_15px_rgba(79,70,229,0.3)] ring-1 ring-black/5 dark:bg-white/95 dark:shadow-emerald-500/20">
                            <BrandLogoImage class="h-full w-full object-cover" />
                        </div>
                        <span class="font-semibold text-slate-900 dark:text-white tracking-wide">PulseNode</span>
                    </div>

                    <!-- Desktop Nav Links -->
                    <div class="hidden md:flex flex-1 items-center justify-center gap-10 text-[13px] font-medium text-slate-500 dark:text-slate-400">
                        <a href="#" class="hover:text-slate-900 dark:hover:text-white transition-colors">Devices</a>
                        <a href="#" class="hover:text-slate-900 dark:hover:text-white transition-colors">Energy Plans</a>
                        <a href="https://github.com/Copernicus12/PulseNode" target="_blank" rel="noreferrer" class="hover:text-slate-900 dark:hover:text-white transition-colors">Docs</a>
                    </div>

                    <!-- Right Auth Actions -->
                    <div class="flex items-center gap-3 text-[13px] font-medium">
                        <ThemeModeSwitcher 
                            compact 
                            button-class="border border-slate-200 dark:border-gray-700/60 bg-white dark:bg-gray-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-gray-700 rounded-full px-2.5 py-2" 
                        />
                        
                        <div class="w-px h-5 bg-slate-300 dark:bg-white/10 mx-1"></div>

                        <template v-if="$page.props.auth.user">
                            <Link :href="dashboard()" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors hidden sm:block">
                                Dashboard
                            </Link>
                            <Link :href="dashboard()" class="rounded-full border border-slate-300 dark:border-white/10 bg-slate-100 dark:bg-white/5 px-5 py-2 text-slate-800 dark:text-white transition hover:bg-slate-200 dark:hover:bg-white/10">
                                Open Hub
                            </Link>
                        </template>
                        <template v-else>
                            <Link :href="login()" class="text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors hidden sm:block font-semibold">
                                Login
                            </Link>
                            <Link v-if="canRegister" :href="register()" class="rounded-full bg-slate-900 dark:bg-white px-5 py-2.5 text-white dark:text-[#0D0D12] transition hover:bg-slate-800 dark:hover:bg-slate-200 shadow-lg dark:shadow-[0_0_20px_rgba(255,255,255,0.15)] font-semibold">
                                Request an account
                            </Link>
                        </template>
                    </div>
                </nav>
            </header>

            <!-- Hero Section -->
            <section class="mt-20 flex flex-col items-center text-center">

                <h1 class="text-balance text-5xl font-medium tracking-tight text-slate-900 dark:text-white sm:text-6xl md:text-7xl lg:text-[4.5rem] lg:leading-[1.15]">
                    Smart control that<br />shapes your home
                </h1>
                
                <p class="mt-6 max-w-2xl text-balance text-lg text-slate-600 dark:text-slate-400">
                    Unify your ESP32 devices. Manage power distribution, automate schedules, track energy consumption in real-time, and run a smarter living environment.
                </p>

                <div class="mt-10 mb-8 border border-slate-200 dark:border-white/10 rounded-full p-1 bg-white/50 dark:bg-white/5 backdrop-blur-md shadow-[0_0_40px_rgba(0,0,0,0.05)] dark:shadow-[0_0_40px_rgba(255,255,255,0.05)]">
                    <template v-if="$page.props.auth.user">
                        <Link :href="dashboard()" class="inline-flex items-center justify-center rounded-full bg-slate-900 dark:bg-white px-8 py-3.5 text-sm font-semibold text-white dark:text-[#0D0D12] transition hover:scale-[1.02] active:scale-95 shadow-md dark:shadow-[inset_0_-2px_4px_rgba(0,0,0,0.1)]">
                            Launch Dashboard
                        </Link>
                    </template>
                    <template v-else>
                         <Link :href="register()" class="inline-flex items-center justify-center rounded-full bg-slate-900 dark:bg-white px-8 py-3.5 text-sm font-semibold text-white dark:text-[#0D0D12] transition hover:scale-[1.02] active:scale-95 shadow-md dark:shadow-[inset_0_-2px_4px_rgba(0,0,0,0.1)]">
                            Connect your home
                        </Link>
                    </template>
                </div>
            </section>

            <!-- Bentobox Grid -->
            <section class="mt-12 mb-24 grid gap-6 md:grid-cols-3 md:grid-rows-2">
                <!-- Large card (Energy History) -->
                <div class="group relative overflow-hidden rounded-[2.5rem] border border-slate-200 dark:border-gray-700/50 bg-white/60 dark:bg-gray-700/60 p-8 shadow-xl dark:shadow-2xl backdrop-blur-sm md:col-span-2 md:row-span-2 flex flex-col transition-all hover:border-emerald-500/20 dark:hover:border-emerald-500/20 dark:hover:shadow-[0_0_80px_rgba(16,185,129,0.1)]">
                    <div class="absolute inset-x-0 -top-px h-px bg-gradient-to-r from-transparent via-slate-300 dark:via-white/20 to-transparent flex opacity-40"></div>
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                    <div class="pointer-events-none absolute -top-24 -right-24 h-64 w-64 rounded-full bg-emerald-500/12 opacity-0 blur-3xl transition-opacity duration-500 group-hover:opacity-100"></div>
                    
                    <div class="relative z-10 px-2 mt-2">
                        <h3 class="text-[1.7rem] font-medium text-slate-800 dark:text-white tracking-tight leading-tight">Monitor energy usage<br/>over time</h3>
                        <p class="mt-3 text-slate-600 dark:text-slate-400 max-w-sm text-[15px] leading-relaxed">Review your total aggregated power consumption directly from our interactive dashboards.</p>
                    </div>

                    <div class="relative mt-12 w-[110%] -ml-[5%] flex-1 rounded-t-3xl border-t border-l border-r border-slate-200 dark:border-gray-700/40 bg-gradient-to-b from-white/[0.4] dark:from-gray-700/20 to-transparent overflow-hidden group/chart h-[320px]">
                        
                        <!-- Top UI dots -->
                        <div class="absolute top-4 left-6 flex gap-2">
                            <div class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-white/10"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-white/10"></div>
                            <div class="w-2.5 h-2.5 rounded-full bg-slate-300 dark:bg-white/10"></div>
                        </div>

                        <!-- Real Database Chart (SVG) -->
                        <div class="absolute inset-0 flex items-end opacity-90 transition-opacity mt-8">
                            <svg class="h-full w-full" preserveAspectRatio="none" viewBox="0 0 800 300" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <!-- Grid Lines -->
                                <line x1="0" y1="50" x2="800" y2="50" stroke="currentColor" class="text-slate-300 dark:text-white" stroke-opacity="0.1" stroke-dasharray="4 4" />
                                <line x1="0" y1="150" x2="800" y2="150" stroke="currentColor" class="text-slate-300 dark:text-white" stroke-opacity="0.1" stroke-dasharray="4 4" />
                                <line x1="0" y1="250" x2="800" y2="250" stroke="currentColor" class="text-slate-300 dark:text-white" stroke-opacity="0.1" stroke-dasharray="4 4" />
                                
                                <!-- Dynamic Usage Curve -->
                                <path v-if="energyUsage?.week" :d="sparklinePoints" stroke="#10b981" stroke-width="4" stroke-linecap="round" style="filter: drop-shadow(0 8px 16px rgba(16,185,129,0.3))"/>
                                <path v-if="energyUsage?.week" :d="sparklineFill" fill="url(#emerald_grad)" />
                                
                                <!-- Secondary Static Curve -->
                                <path d="M-50 220 C 100 240, 250 180, 350 170 C 500 160, 650 200, 850 140" stroke="#06b6d4" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="8 8" opacity="0.6"/>
                                
                                <defs>
                                    <linearGradient id="emerald_grad" x1="400" y1="0" x2="400" y2="300" gradientUnits="userSpaceOnUse">
                                        <stop stop-color="#10b981" stop-opacity="0.25"/>
                                        <stop offset="1" stop-color="#10b981" stop-opacity="0"/>
                                    </linearGradient>
                                </defs>
                            </svg>
                        </div>
                        
                        <!-- Floating Overlay 1: Energy Used -->
                        <div class="absolute top-16 left-[10%] rounded-2xl border border-slate-200 dark:border-gray-700/50 bg-white/95 dark:bg-gray-700/90 backdrop-blur-xl p-5 shadow-xl min-w-[220px] transition-transform duration-500 group-hover/chart:-translate-y-2">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 uppercase tracking-widest font-semibold flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" class="stroke-slate-400" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                    7-Day Total
                                </span>
                                <span class="flex h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 relative">
                                    <span class="absolute inset-0 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-ping opacity-75"></span>
                                </span>
                            </div>
                            <p class="text-[2rem] font-medium text-slate-900 dark:text-white tracking-tight">{{ thisWeekTotal.toFixed(2) }} kWh</p>
                            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-white/5 flex justify-between items-center">
                                <p class="text-xs text-slate-500 dark:text-slate-400">vs last week</p>
                                <p class="text-xs text-emerald-600 dark:text-emerald-400 font-medium px-2 py-1 bg-emerald-50 dark:bg-emerald-400/10 rounded-md">
                                    Tracked
                                </p>
                            </div>
                        </div>
                        
                        <!-- Floating Overlay 2: System State -->
                        <div class="absolute bottom-12 right-[15%] rounded-2xl border border-slate-200 dark:border-gray-700/50 bg-white/95 dark:bg-gray-700/90 backdrop-blur-xl p-4 shadow-[0_20px_40px_rgba(0,0,0,0.1)] dark:shadow-[0_20px_40px_rgba(0,0,0,0.4)] flex items-center gap-4 transition-transform duration-500 group-hover/chart:-translate-y-2">
                            <div class="flex -space-x-3">
                                <div class="w-10 h-10 rounded-full border-2 border-white dark:border-gray-800 bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center"><span class="text-white text-xs font-bold">{{ stats?.users > 0 ? 'US' : '...' }}</span></div>
                                <div class="w-10 h-10 rounded-full border-2 border-white dark:border-gray-800 bg-gradient-to-br from-emerald-500 to-green-700 flex items-center justify-center"><span class="text-white text-xs font-bold">{{ telemetry?.active_relays || 0 }}R</span></div>
                                <div class="w-10 h-10 rounded-full border-2 border-white dark:border-gray-800 bg-slate-200 dark:bg-white/10 backdrop-blur flex items-center justify-center text-xs font-bold text-slate-600 dark:text-white">+{{ stats?.devices || 0 }}</div>
                            </div>
                            <div>
                                <p class="text-[13px] font-medium text-slate-900 dark:text-white">{{ telemetry?.power || 0 }}W Current Load</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ stats?.devices || 0 }} Active Devices</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Card 1 (System Hardware) -->
                <div class="group relative overflow-hidden rounded-[2.5rem] border border-slate-200 dark:border-gray-700/50 bg-white/60 dark:bg-gray-700/60 p-8 shadow-lg dark:shadow-xl backdrop-blur-sm transition-all hover:border-emerald-500/20 dark:hover:border-emerald-500/20 hover:-translate-y-1 hover:shadow-xl dark:hover:shadow-[0_20px_40px_rgba(0,0,0,0.3)] flex flex-col">
                    <div class="absolute inset-x-0 -top-px h-px bg-gradient-to-r from-transparent via-slate-300 dark:via-white/20 to-transparent opacity-40"></div>
                    <div class="absolute inset-0 bg-gradient-to-bl from-emerald-500/10 via-transparent to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                    <div class="pointer-events-none absolute -top-16 -right-16 h-44 w-44 rounded-full bg-emerald-500/10 opacity-0 blur-3xl transition-opacity duration-500 group-hover:opacity-100"></div>
                    
                    <div class="relative z-10 flex flex-col h-full">
                        <div class="mb-6 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-b from-slate-100 to-slate-50 dark:from-white/10 dark:to-white/5 border border-slate-200 dark:border-white/10 shadow-sm dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.1)]">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-500 dark:text-emerald-400 dark:drop-shadow-[0_0_10px_rgba(16,185,129,0.5)]"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>
                        </div>
                        <h3 class="text-xl font-medium text-slate-800 dark:text-white tracking-tight leading-snug">Hardware powered by<br/>the flexible ESP32.</h3>
                        <p class="mt-4 text-[14px] text-slate-600 dark:text-slate-400 leading-relaxed">Monitor voltage, current, and relay states connected directly to your network securely.</p>
                        
                        <div class="mt-auto pt-8">
                            <a href="#" class="inline-flex items-center text-[13px] font-semibold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors">
                                Hardware specs
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1.5 transition-transform group-hover:translate-x-1"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Secondary Card 2 (Integrations / Sync) -->
                <div class="group relative overflow-hidden rounded-[2.5rem] border border-slate-200 dark:border-gray-700/50 bg-white/60 dark:bg-gray-700/60 p-8 shadow-lg dark:shadow-xl backdrop-blur-sm transition-all hover:border-emerald-500/20 dark:hover:border-emerald-500/20 hover:-translate-y-1 hover:shadow-xl dark:hover:shadow-[0_20px_40px_rgba(0,0,0,0.3)] flex flex-col">
                    <div class="absolute inset-x-0 -top-px h-px bg-gradient-to-r from-transparent via-slate-300 dark:via-white/20 to-transparent opacity-40"></div>
                    <div class="absolute inset-0 bg-gradient-to-tl from-emerald-500/10 via-transparent to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                    <div class="pointer-events-none absolute -top-16 -right-16 h-44 w-44 rounded-full bg-emerald-500/10 opacity-0 blur-3xl transition-opacity duration-500 group-hover:opacity-100"></div>
                    
                    <div class="relative z-10 flex flex-col h-full">
                        <div class="mb-6 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-b from-slate-100 to-slate-50 dark:from-white/10 dark:to-white/5 border border-slate-200 dark:border-white/10 shadow-sm dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.1)]">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-500 dark:text-emerald-400 dark:drop-shadow-[0_0_10px_rgba(16,185,129,0.5)]"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><line x1="3" x2="21" y1="9" y2="9"/><line x1="9" x2="9" y1="21" y2="9"/></svg>
                        </div>
                        <h3 class="text-xl font-medium text-slate-800 dark:text-white tracking-tight leading-snug">Seamlessly synchronized<br/>in real-time.</h3>
                        <p class="mt-4 text-[14px] text-slate-600 dark:text-slate-400 leading-relaxed">Live telemetry with {{ telemetry?.voltage || 230 }} V readings, pushed directly through MQTT updates.</p>
                        
                        <div class="mt-auto pt-8 flex gap-2 w-full">
                            <span class="rounded-full border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-3 py-1.5 text-[11px] font-semibold tracking-wide text-slate-600 dark:text-slate-300 shadow-sm">{{ stats?.detections || 0 }} Detections</span>
                            <span class="rounded-full border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-3 py-1.5 text-[11px] font-semibold tracking-wide text-slate-600 dark:text-slate-300 shadow-sm">MQTT Sync</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
